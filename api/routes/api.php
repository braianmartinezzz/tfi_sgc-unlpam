<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConvenioController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\VencimientoController;
use App\Http\Controllers\Api\EstadisticaController;
use App\Http\Controllers\Api\ConvenioExportController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/convenios', [ConvenioController::class, 'index']);
    Route::get('/convenios/{id}', [ConvenioController::class, 'show'])->whereNumber('id');
    Route::put('/convenios/{id}', [ConvenioController::class, 'update'])->whereNumber('id');
    Route::delete('/convenios/{id}', [ConvenioController::class, 'destroy'])->whereNumber('id');
    Route::post('/convenios', [ConvenioController::class, 'store']);
    Route::get('/convenios/vencimientos', [VencimientoController::class, 'index']);
    Route::get('/estadisticas', [EstadisticaController::class, 'index']);
    Route::get('/convenios/exportar', [ConvenioExportController::class, 'index']);
});
