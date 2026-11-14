<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConvenioController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\VencimientoController;
use App\Http\Controllers\Api\EstadisticaController;
use App\Http\Controllers\Api\ConvenioExportController;
use App\Http\Controllers\Api\UnidadAcademicaController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\AuditoriaController;

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
    Route::get('/unidades-academicas', [UnidadAcademicaController::class, 'index']);
    Route::post('/unidades-academicas', [UnidadAcademicaController::class, 'store']);
    Route::get('/unidades-academicas/{id}', [UnidadAcademicaController::class, 'show'])->whereNumber('id');
    Route::put('/unidades-academicas/{id}', [UnidadAcademicaController::class, 'update'])->whereNumber('id');
    Route::delete('/unidades-academicas/{id}', [UnidadAcademicaController::class, 'destroy'])->whereNumber('id');
    Route::get('/usuarios', [UsuarioController::class, 'index']);
    Route::post('/usuarios', [UsuarioController::class, 'store']);
    Route::get('/usuarios/{id}', [UsuarioController::class, 'show'])->whereNumber('id');
    Route::put('/usuarios/{id}', [UsuarioController::class, 'update'])->whereNumber('id');
    Route::delete('/usuarios/{id}', [UsuarioController::class, 'destroy'])->whereNumber('id');
    Route::get('/auditoria', [AuditoriaController::class, 'index']);
});
