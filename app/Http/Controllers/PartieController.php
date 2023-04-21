<?php

namespace App\Http\Controllers;

use App\Http\Requests\PartieRequest;
use App\Http\Resources\PartieResource;
use App\Models\Partie;
use Illuminate\Http\Request;

class PartieController extends Controller
{
    public function createGame(PartieRequest $request) : PartieResource
    {
        $bateaux = array();
        $bateaux["porte-avions"] = 'test';
        $this->placerBateauxAleatoire();

        $request['bateaux'] = $board;
    }

    public function placerBateauxAleatoire()
    {
        $board = array_fill(1, 10, array_fill(1, 10, -1));
        $bateaux = array();

        for ($i = 5; $i > 1; $i--)
        {
            $rndLine = array_rand($board);
            $rndClm = array_rand($board[$rndLine]);
            dd($rndLine . ' ' . $rndClm);
        }
    }
}
