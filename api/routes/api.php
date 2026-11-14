<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConvenioController;
use App\Http\Controllers\Api\DocenteController;
use App\Http\Controllers\Api\DocumentoController;
use App\Http\Controllers\Api\EstudianteController;
use App\Http\Controllers\Api\InstitucionFirmanteController;
use App\Http\Controllers\Api\RepresentanteController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/convenios', [ConvenioController::class, 'index']);
    Route::get('/convenios/{id}', [ConvenioController::class, 'show'])->whereNumber('id');
    Route::get('/convenios/{id}/observaciones', [ConvenioController::class, 'observaciones'])
        ->whereNumber('id');
    Route::post(
        '/convenios/{id}/observaciones/{idObservacion}/responder',
        [ConvenioController::class, 'responderObservacion']
    )->whereNumber('id')->whereNumber('idObservacion');
    Route::put('/convenios/{id}', [ConvenioController::class, 'update'])->whereNumber('id');
    Route::delete('/convenios/{id}', [ConvenioController::class, 'destroy'])->whereNumber('id');
    Route::post('/convenios', [ConvenioController::class, 'store']);
    Route::post('/convenios/{id}/enviar-revision', [ConvenioController::class, 'enviarRevision'])
        ->whereNumber('id');
    Route::post('/convenios/{id}/registrar-firma', [ConvenioController::class, 'registrarFirma'])
        ->whereNumber('id');
    Route::post('/convenios/{id}/rescindir', [ConvenioController::class, 'rescindir'])
        ->whereNumber('id');
    Route::post('/convenios/{id}/documentos', [DocumentoController::class, 'store'])->whereNumber('id');
    Route::get('/convenios/{id}/documentos', [DocumentoController::class, 'index'])->whereNumber('id');
    Route::get('/documentos/{id}', [DocumentoController::class, 'show'])->whereNumber('id');
    Route::put('/documentos/{id}', [DocumentoController::class, 'update'])->whereNumber('id');
    Route::delete('/documentos/{id}', [DocumentoController::class, 'destroy'])->whereNumber('id');
    Route::get('/documentos/{id}/descargar', [DocumentoController::class, 'downloadLatest'])->whereNumber('id');
    Route::get('/documentos/versiones/{idVersion}/descargar', [DocumentoController::class, 'downloadSpecific'])
        ->whereNumber('idVersion');
    Route::get('/instituciones-firmantes', [InstitucionFirmanteController::class, 'index']);
    Route::get('/instituciones-firmantes/{id}', [InstitucionFirmanteController::class, 'show'])->whereNumber('id');
    Route::post('/instituciones-firmantes', [InstitucionFirmanteController::class, 'store']);
    Route::put('/instituciones-firmantes/{id}', [InstitucionFirmanteController::class, 'update'])
        ->whereNumber('id');
    Route::delete('/instituciones-firmantes/{id}', [InstitucionFirmanteController::class, 'destroy'])
        ->whereNumber('id');
    Route::get('/representantes', [RepresentanteController::class, 'index']);
    Route::get('/representantes/{id}', [RepresentanteController::class, 'show'])->whereNumber('id');
    Route::post('/representantes', [RepresentanteController::class, 'store']);
    Route::put('/representantes/{id}', [RepresentanteController::class, 'update'])->whereNumber('id');
    Route::delete('/representantes/{id}', [RepresentanteController::class, 'destroy'])->whereNumber('id');
    Route::get('/estudiantes', [EstudianteController::class, 'index']);
    Route::get('/estudiantes/{id}', [EstudianteController::class, 'show'])->whereNumber('id');
    Route::post('/estudiantes', [EstudianteController::class, 'store']);
    Route::put('/estudiantes/{id}', [EstudianteController::class, 'update'])->whereNumber('id');
    Route::delete('/estudiantes/{id}', [EstudianteController::class, 'destroy'])->whereNumber('id');
    Route::get('/docentes', [DocenteController::class, 'index']);
    Route::get('/docentes/{id}', [DocenteController::class, 'show'])->whereNumber('id');
    Route::post('/docentes', [DocenteController::class, 'store']);
    Route::put('/docentes/{id}', [DocenteController::class, 'update'])->whereNumber('id');
    Route::delete('/docentes/{id}', [DocenteController::class, 'destroy'])->whereNumber('id');
});
