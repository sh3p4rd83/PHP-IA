<?php

namespace App\Http\Controllers;

use App\Http\Requests\PartieRequest;
use App\Http\Resources\PartieResource;
use App\Models\Partie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PartieController extends Controller
{

    public function create(PartieRequest $request): PartieResource
    {
        $this->authorize('create', Partie::class);

        $attributes = $request->validated();
        $bateaux = $this->placerBateauxAleatoire();
        $attributes['bateaux'] = json_encode($bateaux);

        $partie = Auth::user()->parties()->create($attributes);

        return new PartieResource($partie);
    }

    public function destroy(Request $request): PartieResource|JsonResponse
    {
        $partie = Partie::all()->where('id', $request->partie_id);
        if($partie->count() == 0) {
            return response()->json([
                'message' => 'La ressource n’existe pas.'
            ], 404);
        }

        $this->authorize('delete', [Partie::class, $partie->first()]);

        $partie = $partie->first();
        $partie->delete();

        return new PartieResource($partie);
    }

    public function placerBateauxAleatoire() : array
    {
        $positions = array();
        $positions = array_merge($positions, $this->genererBateau($positions, 5));
        $positions = array_merge($positions, $this->genererBateau($positions, 4));
        $positions = array_merge($positions, $this->genererBateau($positions, 3));
        $positions = array_merge($positions, $this->genererBateau($positions, 3));
        $positions = array_merge($positions, $this->genererBateau($positions, 2));

        $bateaux = array();
        $bateaux['porte-avions'] = array_slice($positions, 0, 5);
        $bateaux['cuirasse'] = array_slice($positions, 5, 4);
        $bateaux['destroyer'] = array_slice($positions, 9, 3);
        $bateaux['sous-marin'] = array_slice($positions, 12, 3);
        $bateaux['patrouilleur'] = array_slice($positions, 15, 2);

        return $bateaux;
    }

    private function genererBateau($arr, $taille): array|bool
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
                } elseif (!in_array(chr($l + ($j - $taille)) . $e, $arr) && $l - $j > 64) {
                    array_push($newBoat, chr($l + ($j - $taille)) . $e);
                } else {
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
                } else {
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
