<?php

namespace App\Http\Controllers;

use App\Http\Requests\MissileRequest;
use App\Http\Resources\MissileResource;
use App\Models\Missile;
use App\Models\Partie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MissileController extends Controller
{


    /**
     * Tire un missile.
     *
     * @param MissileRequest $request La demande de tir venant d'une partie en cours.
     * @return MissileResource|JsonResponse La réponse de l'IA
     */
    public function create(MissileRequest $request): MissileResource|JsonResponse
    {
        $partie = Partie::all()->where('id', $request->partie_id);
        if ($partie->count() == 0) {
            return response()->json([
                'message' => 'La ressource n’existe pas.'
            ], 404);
        }
        $this->authorize('create', [Missile::class, $partie->first()]);
        $attributes = $request->validated();

        $attributes["coordonnées"] = self::findBestShot($request->partie_id);
        $attributes["partie_id"] = $request->partie_id;

        $tir = Missile::create($attributes);
        return new MissileResource($tir);
    }

    /**
     * Met a jour une ressource selon la partie et la coordonnée.
     *
     * @param MissileRequest $request Le missile
     * @return MissileResource|JsonResponse Une réponse json de réussite ou d'erreur.
     */
    public function update(MissileRequest $request): MissileResource|JsonResponse
    {
        $partie = Partie::all()->where('id', $request->partie_id);

        if ($partie->count() == 0) {
            return response()->json([
                'message' => 'La ressource n’existe pas.'
            ], 404);
        }

        $this->authorize('update', [Missile::class, $partie->first()]);

        $attributes = $request->validate([
            "resultat" => "required|integer|between:0,6",
        ]);

        if (Auth::user()->id != $partie->first()->user_id) {
            return response()->json([
                "message" => 'Cette action n\'est pas autorisée.'
            ], 403);
        }

        $missile = Missile::whereBelongsTo($partie)
            ->where('coordonnées', $request->coordonnées)
            ->first();

        if ($missile == null) {
            return response()->json([
                'message' => 'La ressource n’existe pas.'
            ], 404);
        }

        $missile->resultat = $attributes["resultat"];
        $missile->save();

        self::searchUniqueShipPosition($request->partie_id);

        return new MissileResource($missile);
    }

    /**
     * Vient vérifier si un bateau est coulé, avec certitude, afin d'éviter de considerer comme coulé le mauvais bateau.
     * Si une seule position pour un bateau coulé est possible, vient mettre a jour la BD.
     *
     * @param $partieId int La partie en cours.
     * @return void
     */
    private static function searchUniqueShipPosition($partieId): void
    {
        $partie = Partie::all()->where('id', $partieId);
        $missiles = Missile::whereBelongsTo($partie)->pluck('resultat')->toArray();

        $resultats = array_count_values($missiles);

        $hitNotSunk = array();

        if (array_key_exists(2, $resultats) && $resultats[2] != 5) {
            $hitNotSunk[] = 'porte-avions';
        }
        if (array_key_exists(3, $resultats) && $resultats[3] != 4) {
            $hitNotSunk[] = 'cuirasse';
        }
        if (array_key_exists(4, $resultats) && $resultats[4] != 3) {
            $hitNotSunk[] = 'destroyer';
        }
        if (array_key_exists(5, $resultats) && $resultats[5] != 3) {
            $hitNotSunk[] = 'sous-marin';
        }
        if (array_key_exists(6, $resultats) && $resultats[6] != 2) {
            $hitNotSunk[] = 'patrouilleur';
        }

        $positionsHitsNotSunk = self::evaluatePossibleSpot($partieId, $hitNotSunk);
        $refineHitNotSunk = self::refineSpots($positionsHitsNotSunk, self::getSunk($partieId));
        $allHits = array_merge(self::getHits($partieId), self::getSunk($partieId));

        foreach ($refineHitNotSunk as $name => $ship) {
            foreach ($ship as $possibleLoc) {
                if (count(array_intersect($possibleLoc, $allHits)) == self::getBoatSize($name)) {
                    $possibleLocs[] = $possibleLoc;
                }
            }
            if (isset($possibleLocs) && count($possibleLocs) == 1) {
                self::updateSunkShips($possibleLocs[0], $partieId, self::getShipId($name));
            }
        }
    }

    /**
     * Met a jour la BD selon la position d'un bateau coulé.
     *
     * @param $shipPos array les positions du bateau
     * @param $partieId int la partie en cours
     * @param $sunkShipId int l'id du bateau coulé
     * @return void
     */
    private static function updateSunkShips($shipPos, $partieId, $sunkShipId)
    {
        $partie = Partie::all()->where('id', $partieId);
        Missile::whereBelongsTo($partie)->whereIn('coordonnées', $shipPos)->update(['resultat' => $sunkShipId]);
    }

    /**
     * Coeur de l'IA, fait les appels selon l'état actuel de la partie.
     *
     * @param $partieId int la partie concernée
     * @return string une coordonnée aléatoire dans la liste des plus hautes probabilités de touches.
     */
    private static function findBestShot($partieId): string
    {
        $remainingShip = self::getRemainingShips($partieId);
        $possibleSpot = self::evaluatePossibleSpot($partieId, $remainingShip);

        $hits = self::getHits($partieId);
        if (count($hits) == 0) {
            $bestSpots = self::getFrequencies($possibleSpot);
        } else {
            $bestSpots = self::getFrequencies(self::refineSpots($possibleSpot, $hits, $partieId), $hits);
        }

        return $bestSpots[array_rand($bestSpots)];
    }


    /**
     * Vient rafiner les positions possible de chaque bateau selon les tirs qui ont déjà touché.
     * Permet a l'IA de se concentrer uniquement sur la traque de bateaux endommagés.
     *
     * @param $possibleSpot array multidimensionnel des positions possible de chaque bateau
     * @param $hits array des tirs qui ont touchés
     * @return array multidimensionnel de positions rafinés pour chaque bateau.
     */
    private static function refineSpots($possibleSpot, $hits): array
    {
        foreach ($possibleSpot as $name => $ship) {
            foreach ($ship as $index => $shipLocation) {
                if (count(array_intersect($shipLocation, $hits)) <= 0) {
                    unset($possibleSpot[$name][$index]);
                }
            }
        }
        return $possibleSpot;
    }

    /**
     * Cherche dans une partie l'ensemble des tirs qui ont touchés.
     *
     * @param $partieId int la partie concernée
     * @return array un array de coordonnées qui ont eu un hit.
     */
    private static function getHits($partieId): array
    {
        $partie = Partie::all()->where('id', $partieId);
        return Missile::whereBelongsTo($partie)->where('resultat', 1)->pluck('coordonnées')->toArray();
    }

    /**
     * Cherche dans une partie les bateaux qui ont déjà été coulés.
     *
     * @param $partieId int la partie concernée
     * @return array un array des bateaux coulés.
     */
    private static function getSunk($partieId): array
    {
        $partie = Partie::all()->where('id', $partieId);
        return Missile::whereBelongsTo($partie)->whereIn('resultat', array(2, 3, 4, 5, 6))->pluck(
            'coordonnées'
        )->toArray();
    }

    /**
     * Permet de retourner l'emplacement possible de tout les bateaux encore actif.
     *
     * @param $partieId int l'ID de la partie, nécessaire pour retrouver les missiles déja tirés
     * @return array un array d'arrays de positions.
     */
    private static function evaluatePossibleSpot($partieId, $remainingShip): array
    {
        $possibleSpot = array();
        $playedShots = array_merge(self::getShots($partieId), self::getSunk($partieId));

        foreach ($remainingShip as $ship) {
            $possibleSpot[$ship] = array();
            for ($line = 65; $line < 75; $line++) {
                for ($col = 1; $col < 11; $col++) {
                    $boatLength = self::getBoatSize($ship);
                    // les boucles permettent l'isolation d'un sens, pour permettre d'exclure uniquement une seule orientation.
                    // Vérification de l'implantation d'un bateau en position verticale
                    while (true) {
                        if ($line + $boatLength - 1 > 74) {
                            break;
                        }
                        for ($b = 0; $b < $boatLength; $b++) {
                            if (in_array(self::intToPos($line + $b, $col), $playedShots)) {
                                break 2;
                            }
                        }
                        $possibleSpot[$ship][] = self::concatBoat($line, $col, 1, $boatLength);

                        break;
                    }
                    // Vérification de l'implantation d'un bateau en position horizontale
                    while (true) {
                        if ($col + $boatLength - 1 > 10) {
                            break;
                        }
                        for ($b = 0; $b < $boatLength; $b++) {
                            if (in_array(self::intToPos($line, $col + $b), $playedShots)) {
                                break 2;
                            }
                        }
                        $possibleSpot[$ship][] = self::concatBoat($line, $col, 2, $boatLength);
                        break;
                    }
                }
            }
        }

        return $possibleSpot;
    }

    /**
     * Grosse fonction, vient chercher la fréquence a laquelle un emplacement est le probable de contenir un bateau.
     * Pour ca, on vient passer dans la liste de chaque emplacement de chaque bateau, que l'on compare avec la position
     * possible de tout les autres bateaux, et si il y a un "overlap", on vient passer ce type de configuration.
     * Cela permet de raffiner les probabilités et ainsi d'augmenter les zones pouvant être considérés comme étant des
     * "bons coups a tirer".
     * Dans le cas où il ne reste qu'un bateau, il ne se compare pas a lui même, il vient chercher les zones ayant la
     * plus haute probabilité de toucher.
     *
     * @param $possibleSpot array la liste des emplacements de chaque bateaux.
     * @return array Un array des meilleurs tirs possible.
     */
    private static function getFrequencies($possibleSpot, $hits = array()): array
    {
        $locations = array();
        $data = 0;
        foreach ($possibleSpot as $ship) {
            foreach ($ship as $shipLocation) {
                foreach ($shipLocation as $spot) {
                    if (!in_array($spot, $hits)) {
                        if (!array_key_exists($spot, $locations)) {
                            $locations[$spot] = 1;
                        } else {
                            $locations[$spot]++;
                        }
                        $data++;
                    }
                }
            }
        }
        $frequencies = array_map(fn($value) => round(($value * 100 / $data), 4), $locations);
        return array_keys($frequencies, max($frequencies));
    }

    /**
     * Permet la concatenation des positions des bateaux
     *
     * @param $line int la ligne du bateau, convertie en char
     * @param $col int la colonne du bateau
     * @param $orientation int le sens du bateau, 1 pour Vertical, 2 pour horizontal
     * @param $boatlenght int la longueur du bateau
     * @return array un array de positions pour un bateau a un emplacement
     */
    private static function concatBoat($line, $col, $orientation, $boatlenght): array
    {
        $boat = array();
        if ($orientation == 1) {
            for ($i = 0; $i < $boatlenght; $i++) {
                $boat[] = self::intToPos($line + $i, $col);
            }
        } else {
            for ($i = 0; $i < $boatlenght; $i++) {
                $boat[] = self::intToPos($line, $col + $i);
            }
        }
        return $boat;
    }

    /**
     * Transforme des positions en string
     *
     * @param $line int la ligne, de A a J
     * @param $col int la colonne, de 1 a 10
     * @return string la position traduite
     */
    private static function intToPos($line, $col): string
    {
        return chr($line) . '-' . $col;
    }

    /**
     * Retourne la longueur d'un bateau
     *
     * @param $boat string le nom du bateau
     * @return int sa longueur.
     */
    private static function getBoatSize($boat): int
    {
        $size = 0;
        switch ($boat) {
            case ('porte-avions'):
                $size = 5;
                break;
            case ('cuirasse'):
                $size = 4;
                break;
            case ('sous-marin'):
            case ('destroyer'):
                $size = 3;
                break;
            default:
                $size = 2;
                break;
        }

        return $size;
    }

    /**
     * Va chercher et retourne l'ensemble des coups qui ont déjà été joués
     *
     * @param $partieId integer la partie concernée
     * @return array un array de coups joués
     */
    private static function getShots($partieId): array
    {
        $partie = Partie::all()->where('id', $partieId);
        $missiles = Missile::whereBelongsTo($partie)->where('resultat', 0)->pluck('coordonnées')->toArray();

        return $missiles;
    }

    /**
     * Cherche dans les coups joués pour les bateaux qui n'ont pas été coulés.
     *
     * @param $partieId integer la partie concernée
     * @return array un array de bateau
     */
    private static function getRemainingShips($partieId): array
    {
        $bateauxRestants = array();
        $partie = Partie::all()->where('id', $partieId);
        $missiles = Missile::whereBelongsTo($partie)->pluck('resultat')->toArray();

        if (!in_array('2', $missiles)) {
            $bateauxRestants[] = 'porte-avions';
        }
        if (!in_array(3, $missiles)) {
            $bateauxRestants[] = 'cuirasse';
        }
        if (!in_array(4, $missiles)) {
            $bateauxRestants[] = 'destroyer';
        }
        if (!in_array(5, $missiles)) {
            $bateauxRestants[] = 'sous-marin';
        }
        if (!in_array(6, $missiles)) {
            $bateauxRestants[] = 'patrouilleur';
        }

        return $bateauxRestants;
    }

    /**
     * Cherche et retourne le code de destruction d'un bateau suivant son nom
     *
     * @param $shipName string le nom du bateau
     * @return int Son code de destruction
     */
    private static function getShipId($shipName): int
    {
        switch ($shipName) {
            case "porte-avions":
                return 2;
            case "cuirasse" :
                return 3;
            case "destroyer" :
                return 4;
            case "sous-marin" :
                return 5;
            case "patrouilleur" :
                return 6;
            default :
                return 0;
        }
    }
}
