<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConvenioController;
use App\Http\Controllers\Api\DocumentoController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/convenios', [ConvenioController::class, 'index']);
    Route::get('/convenios/{id}', [ConvenioController::class, 'show'])->whereNumber('id');
    Route::put('/convenios/{id}', [ConvenioController::class, 'update'])->whereNumber('id');
    Route::delete('/convenios/{id}', [ConvenioController::class, 'destroy'])->whereNumber('id');
    Route::post('/convenios', [ConvenioController::class, 'store']);
    Route::post('/convenios/{id}/documentos', [DocumentoController::class, 'store'])->whereNumber('id');
    Route::get('/convenios/{id}/documentos', [DocumentoController::class, 'index'])->whereNumber('id');
    Route::get('/documentos/{id}', [DocumentoController::class, 'show'])->whereNumber('id');
    Route::put('/documentos/{id}', [DocumentoController::class, 'update'])->whereNumber('id');
    Route::get('/documentos/{id}/descargar', [DocumentoController::class, 'downloadLatest'])->whereNumber('id');
    Route::get('/documentos/versiones/{idVersion}/descargar', [DocumentoController::class, 'downloadSpecific'])
        ->whereNumber('idVersion');
});
