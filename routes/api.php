<?php

use App\Http\Controllers\MissileController;
use App\Http\Controllers\PartieController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('battleship-ia/parties')
    ->controller(PartieController::class)
    ->group(function () {
        Route::post('/', 'createGame');
        Route::delete('/{partie}', 'deleteGame');
    });

Route::prefix('battleship-ia/parties/{partie_id}/missiles')
    ->controller(MissileController::class)
    ->group(function () {
        Route::post('/', 'fireMissile');
        Route::put('/{coordonnées}', 'reponseMissile');
    });
