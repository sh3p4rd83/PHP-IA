<?php

namespace App\Http\Controllers;

use App\Http\Requests\MissileRequest;
use App\Http\Resources\MissileResource;
use App\Models\Missile;
use App\Models\Partie;
use Illuminate\Http\Request;

class MissileController extends Controller
{
    public function fireMissile(MissileRequest $request): MissileResource
    {
        $shot = $this->findBestShot($request->partie);
        dd($shot);
        return new MissileResource($request);
    }

    public function reponseMissile(MissileRequest $request, Missile $missile): MissileResource
    {
        dd();
    }

    private function findBestShot($partieId): string
    {
        $possibleSpot = $this->evaluatePossibleSpot($partieId);
        $bestSpots =  $this->getFrequencies($possibleSpot);
        return $bestSpots[array_rand($bestSpots)];
    }

    /**
     * Permet de retourner l'emplacement possible de tout les bateaux encore actif.
     *
     * @param $partieId int l'ID de la partie, nécessaire pour retrouver les missiles déja tirés
     * @return array un array d'arrays de positions.
     */
    private function evaluatePossibleSpot($partieId): array
    {
        $possibleSpot = array();
        $playedShots = $this->getMissedShots($partieId);

        foreach ($this->getRemainingShips($partieId) as $ship) {
            $possibleSpot[$ship] = array();
            for ($l = 65; $l < 75; $l++) {
                for ($c = 1; $c < 11; $c++) {
                    $boatLength = $this->getBoatSize($ship);
                    // les boucles permettent l'isolation d'un sens, pour permettre d'exclure uniquement une seule orientation.
                    // Vérification de l'implantation d'un bateau en position verticale
                    while (true) {
                        if ($l + $boatLength - 1 > 74) {
                            break;
                        }
                        for ($b = 0; $b < $boatLength; $b++) {
                            if (in_array($this->intToPos($l + $b, $c), $playedShots)) {
                                break 2;
                            }
                        }
                        $possibleSpot[$ship][] = $this->concatBoat($l, $c, 1, $boatLength);

                        break;
                    }
                    // Vérification de l'implantation d'un bateau en position horizontale
                    while (true) {
                        if ($c + $boatLength - 1 > 10) {
                            break;
                        }
                        for ($b = 0; $b < $boatLength; $b++) {
                            if (in_array($this->intToPos($l, $c + $b), $playedShots)) {
                                break 2;
                            }
                        }
                        $possibleSpot[$ship][] = $this->concatBoat($l, $c, 2, $boatLength);
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
     * @return array Un array de la fréquence des bateaux.
     */
    private function getFrequencies($possibleSpot): array
    {
        $locations = array();
        $data = 0;
        foreach ($possibleSpot as $ship) {
            foreach ($ship as $shipLocation) {
                if (count($possibleSpot) == 1) {
                    foreach ($shipLocation as $spot) {
                        if (!array_key_exists($spot, $locations)) {
                            $locations[$spot] = 1;
                        } else {
                            $locations[$spot]++;
                        }
                        $data++;
                    }
                } else {
                    foreach ($possibleSpot as $otherShip) {
                        if ($otherShip != $ship) {
                            foreach ($otherShip as $otherShipLocation) {
                                foreach ($otherShipLocation as $spot) {
                                    if (!in_array($spot, $shipLocation)) {
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
     * @param $l int la ligne du bateau, convertie en char
     * @param $c int la colonne du bateau
     * @param $s int le sens du bateau, 1 pour Vertical, 2 pour horizontal
     * @param $b int la longueur du bateau
     * @return array un array de positions pour un bateau a un emplacement
     */
    private function concatBoat($l, $c, $s, $b): array
    {
        $boat = array();
        if ($s == 1) {
            for ($i = 0; $i < $b; $i++) {
                $boat[] = $this->intToPos($l + $i, $c);
            }
        } else {
            for ($i = 0; $i < $b; $i++) {
                $boat[] = $this->intToPos($l, $c + $i);
            }
        }
        return $boat;
    }

    /**
     * Transforme des positions en string
     *
     * @param $l int la ligne, de A a J
     * @param $c int la colonne, de 1 a 10
     * @return string la position traduite
     */
    private function intToPos($l, $c): string
    {
        return chr($l) . '-' . $c;
    }

    /**
     * Retourne la longueur d'un bateau
     *
     * @param $boat string le nom du bateau
     * @return int sa longueur.
     */
    private function getBoatSize($boat): int
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
    private function getMissedShots($partieId): array
    {
        $partie = Partie::all()->where('id', $partieId);
        $missiles = Missile::whereBelongsTo($partie)->where('resultat', 1)->pluck('coordonnées')->toArray();

        return $missiles;
    }

    /**
     * Cherche dans les coups joués pour les bateaux qui n'ont pas été coulés.
     *
     * @param $partieId integer la partie concernée
     * @return array un array de bateau
     */
    private function getRemainingShips($partieId): array
    {
        $bateauxRestants = array();
        $partie = Partie::all()->where('id', $partieId);
        $missiles = Missile::whereBelongsTo($partie)->pluck('resultat')->toArray();

        if (!in_array(2, $missiles)) {
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
}
