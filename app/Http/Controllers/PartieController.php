<?php

namespace App\Http\Controllers;

use App\Http\Requests\PartieRequest;
use App\Http\Resources\PartieResource;
use App\Models\Partie;
use Illuminate\Http\Request;

class PartieController extends Controller
{
    public function createGame(PartieRequest $request): PartieResource
    {
        $attributes = $request->validated();
        $attributes['bateaux'] = $this->placerBateauxAleatoire();

        $partie = Partie::create($attributes);

        return new PartieResource($partie);
    }

    public function placerBateauxAleatoire() : string
    {
        $positions = array();
        $positions = array_merge($positions,  $this->genererBateau($positions, 5));
        $positions = array_merge($positions,  $this->genererBateau($positions, 4));
        $positions = array_merge($positions,  $this->genererBateau($positions, 3));
        $positions = array_merge($positions,  $this->genererBateau($positions, 3));
        $positions = array_merge($positions,  $this->genererBateau($positions, 2));

        $bateaux = array();
        $bateaux['Porte-Avions'] = array_slice($positions, 0, 5);
        $bateaux['Cuirasse'] = array_slice($positions, 5, 4);
        $bateaux['Destroyer'] = array_slice($positions, 9, 3);
        $bateaux['Sous-marin'] = array_slice($positions, 12, 3);
        $bateaux['Patrouilleur'] = array_slice($positions, 15, 2);


        return json_encode($bateaux);
    }

    private function genererBateau($arr, $taille) : array | bool
    {
        $newBoat = array();

        do {
            $pos = $this->getRandomPosition();
        } while (in_array($pos, $arr));

        array_push($newBoat, $pos);
        $sens = rand(1, 2);
        for ($j = 1; $j < $taille; $j++) {
            if ($sens == 1) {
                $l = ord($pos[0]);
                $e = substr($pos, 1);
                if (!in_array(chr($l + $j) . $e, $arr) && $l + $j < 75) {
                    array_push($newBoat, chr($l + $j) . $e);
                } elseif (!in_array(chr($l + ($j - $taille) ) . $e, $arr) && $l - $j > 64) {
                    array_push($newBoat, chr($l + ($j - $taille)) . $e);
                } else
                {
                    $newBoat = $this->genererBateau($arr, $taille);
                    break;
                }
            } else {
                $c = intval(substr($pos, 2));
                $e = substr($pos, 0, 2);
                if (!in_array($e . $c + $j, $arr) && $c + $j < 11) {
                    array_push($newBoat, $e . $c + $j);
                } elseif (!in_array($e . $c - $j, $arr) && $c - $j > 11) {
                    array_push($newBoat, $e . $c - $j);
                } else
                {
                    $newBoat = $this->genererBateau($arr, $taille);
                    break;
                }
            }
        }

        return $newBoat;
    }

    private function getRandomPosition(): string
    {
        $clm = chr(rand(65, 74));
        $line = rand(1, 10);
        return $clm . '-' . $line;
    }
}
