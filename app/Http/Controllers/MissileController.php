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
        return new MissileResource($request);
    }

    public function reponseMissile(MissileRequest $request, Missile $missile): MissileResource
    {
        dd();
    }

    private function findBestShot($partieId): string
    {
        $possibleSpot = $this->evaluatePossibleSpot($partieId);
        $incompatibleLocations = $this->evaluateIncompatibleSpot();
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
        $playedShots = $this->getPlayedShots($partieId);

        foreach ($this->getRemainingShips($partieId) as $ship) {
            $possibleSpot[] = $ship;
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
                        $possibleSpot[] = $this->concatBoat($l, $c, 1, $boatLength);
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
                        $possibleSpot[] = $this->concatBoat($l, $c, 2, $boatLength);
                        break;
                    }
                }
            }
        }

        return $possibleSpot;
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
    private function concatBoat($l, $c, $s, $b) : array
    {
        $boat = array();
        if ($s == 1) {
            for ($i = 0; $i < $b; $i++)
            {
                $boat[] = $this->intToPos($l + $i, $c);
            }
        } else {
            for ($i = 0; $i < $b; $i++)
            {
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
    private function getPlayedShots($partieId): array
    {
        $partie = Partie::all()->where('id', $partieId);
        $missiles = Missile::whereBelongsTo($partie)->pluck('coordonnées')->toArray();

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
        $missiles = array(Missile::whereBelongsTo($partie)->pluck('resultat'));

        if (!in_array('2', $missiles)) {
            $bateauxRestants[] = 'porte-avions';
        }
        if (!in_array('3', $missiles)) {
            $bateauxRestants[] = 'cuirasse';
        }
        if (!in_array('4', $missiles)) {
            $bateauxRestants[] = 'destroyer';
        }
        if (!in_array('5', $missiles)) {
            $bateauxRestants[] = 'sous-marin';
        }
        if (!in_array('6', $missiles)) {
            $bateauxRestants[] = 'patrouilleur';
        }

        return $bateauxRestants;
    }
}
