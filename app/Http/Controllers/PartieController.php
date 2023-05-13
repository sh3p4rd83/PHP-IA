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

    /**
     * Création d'une nouvelle partie.
     *
     * @param PartieRequest $request La requete de la partie
     * @return PartieResource Réponse Json de la nouvelle partie
     */
    public function create(PartieRequest $request): PartieResource
    {
        $attributes = $request->validated();
        $bateaux = $this->placerBateauxAleatoire();
        $attributes['bateaux'] = json_encode($bateaux);

        $partie = Auth::user()->parties()->create($attributes);

        return new PartieResource($partie);
    }

    /**
     * Supprime une partie terminée.
     *
     * @param Request $request La partie concernée
     * @return PartieResource|JsonResponse Une réponse json de validation de suppression|erreur d'autorisation
     * @throws \Illuminate\Auth\Access\AuthorizationException Erreur d'autorisation
     */
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

    /**
     * Génere une liste de positions aléatoire de bateaux, qui ne se croisent pas et ne sortent pas
     * du tableau.
     *
     * @return array de bateaux comprenant chacun un array de positions.
     */
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

    /**
     * Génere les positions pour un bateau. Recursif.
     *
     * @param $arr array les positions déjà occupées.
     * @param $taille int la taille du bateau
     * @return array un array de positions.
     */
    private function genererBateau($arr, $taille): array
    {
        $newBoat = array();

        do {
            $pos = $this->getRandomPosition();
        } while (in_array($pos, $arr));

        array_push($newBoat, $pos);
        $sens = rand(1, 2);
        for ($j = 1; $j < $taille; $j++) {
            if ($sens == 1) {
                $line = ord($pos[0]);
                $subPosition = substr($pos, 1);
                if (!in_array(chr($line + $j) . $subPosition, $arr) && $line + $j < 75) {
                    array_push($newBoat, chr($line + $j) . $subPosition);
                } elseif (!in_array(chr($line + ($j - $taille)) . $subPosition, $arr) && $line - $j > 64) {
                    array_push($newBoat, chr($line + ($j - $taille)) . $subPosition);
                } else {
                    $newBoat = $this->genererBateau($arr, $taille);
                    break;
                }
            } else {
                $col = intval(substr($pos, 2));
                $subPosition = substr($pos, 0, 2);
                if (!in_array($subPosition . $col + $j, $arr) && $col + $j < 11) {
                    array_push($newBoat, $subPosition . $col + $j);
                } elseif (!in_array($subPosition . $col - $j, $arr) && $col - $j > 11) {
                    array_push($newBoat, $subPosition . $col - $j);
                } else {
                    $newBoat = $this->genererBateau($arr, $taille);
                    break;
                }
            }
        }

        return $newBoat;
    }

    /**
     * Génere une position aléatoire du début de bateau.
     *
     * @return string Une position.
     */
    private function getRandomPosition(): string
    {
        $cln = chr(rand(65, 74));
        $line = rand(1, 10);
        return $cln . '-' . $line;
    }
}
