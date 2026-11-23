<?php

use App\Http\Controllers\Api\AprobarConvenioController;
use App\Http\Controllers\Api\ArchivarConvenioController;
use App\Http\Controllers\Api\AuditoriaController;
use App\Http\Controllers\Api\AuthAppController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConfiguracionController;
use App\Http\Controllers\Api\ConvenioController;
use App\Http\Controllers\Api\ConvenioExportController;
use App\Http\Controllers\Api\DocenteController;
use App\Http\Controllers\Api\DocumentoController;
use App\Http\Controllers\Api\EstadisticaController;
use App\Http\Controllers\Api\EstudianteController;
use App\Http\Controllers\Api\InstitucionFirmanteController;
use App\Http\Controllers\Api\InstitucionUnificacionController;
use App\Http\Controllers\Api\ObservacionTrazabilidadController;
use App\Http\Controllers\Api\RechazarConvenioController;
use App\Http\Controllers\Api\RepresentanteController;
use App\Http\Controllers\Api\RolController;
use App\Http\Controllers\Api\UnidadAcademicaController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\VencimientoController;
use App\Http\Controllers\Api\ComentarioFirmadorController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PanelController;
use App\Http\Controllers\Api\FirmaController;

Route::post('/login', [AuthController::class, 'login']);

// Login de la app móvil: contraseña + MFA (mail, SMS o push) y biometría.
// El login de la web (/login) no cambia.
Route::prefix('app')->middleware('throttle:20,1')->group(function () {
    Route::post('/login', [AuthAppController::class, 'login']);
    Route::post('/mfa/enviar', [AuthAppController::class, 'enviarCodigo']);
    Route::post('/mfa/verificar', [AuthAppController::class, 'verificarCodigo']);
    Route::post('/login/biometrico', [AuthAppController::class, 'loginBiometrico']);
});

Route::middleware(['auth:sanctum', 'sesion.app'])->group(function () {
    Route::post('/app/biometria', [AuthAppController::class, 'activarBiometria']);
    Route::delete('/app/biometria', [AuthAppController::class, 'desactivarBiometria']);

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Convenios
    Route::get('/convenios', [ConvenioController::class, 'index']);
    Route::get('/convenios/{id}', [ConvenioController::class, 'show'])->whereNumber('id');
    Route::post('/convenios', [ConvenioController::class, 'store']);
    Route::put('/convenios/{id}', [ConvenioController::class, 'update'])->whereNumber('id');
    Route::delete('/convenios/{id}', [ConvenioController::class, 'destroy'])->whereNumber('id');

    // Revisión, observaciones, firma y rescisión
    Route::get('/convenios/{id}/observaciones', [ConvenioController::class, 'observaciones'])
        ->whereNumber('id');
    Route::post('/convenios/{id}/observaciones', [ConvenioController::class, 'crearObservacion'])
        ->whereNumber('id');
    Route::put('/observaciones/{idObservacion}', [ConvenioController::class, 'actualizarObservacion'])
        ->whereNumber('idObservacion');
    Route::delete('/observaciones/{idObservacion}', [ConvenioController::class, 'eliminarObservacion'])
        ->whereNumber('idObservacion');
    Route::post(
        '/convenios/{id}/observaciones/{idObservacion}/responder',
        [ConvenioController::class, 'responderObservacion']
    )->whereNumber('id')->whereNumber('idObservacion');

    Route::post('/convenios/{id}/enviar-revision', [ConvenioController::class, 'enviarRevision'])
        ->whereNumber('id');
    Route::post('/convenios/{id}/devolver', [ConvenioController::class, 'devolver'])
        ->whereNumber('id');
    Route::post('/convenios/{id}/registrar-firma', [ConvenioController::class, 'registrarFirma'])
        ->whereNumber('id');
    Route::post('/convenios/{id}/rescindir', [ConvenioController::class, 'rescindir'])
        ->whereNumber('id');

    // Vencimientos, estadísticas y exportación
    Route::get('/convenios/vencimientos', [VencimientoController::class, 'index']);
    Route::get('/estadisticas', [EstadisticaController::class, 'index']);
    Route::get('/convenios/exportar', [ConvenioExportController::class, 'index']);

    // Documentos
    Route::post('/convenios/{id}/documentos', [DocumentoController::class, 'store'])
        ->whereNumber('id');
    Route::get('/convenios/{id}/documentos', [DocumentoController::class, 'index'])
        ->whereNumber('id');
    Route::get('/documentos/{id}', [DocumentoController::class, 'show'])->whereNumber('id');
    Route::get('/documentos/{id}/verificar-hash', [DocumentoController::class, 'verifyHash'])
        ->whereNumber('id');
    Route::put('/documentos/{id}', [DocumentoController::class, 'update'])->whereNumber('id');
    Route::delete('/documentos/{id}', [DocumentoController::class, 'destroy'])->whereNumber('id');
    Route::get('/documentos/{id}/descargar', [DocumentoController::class, 'downloadLatest'])
        ->whereNumber('id');
    Route::get(
        '/documentos/versiones/{idVersion}/descargar',
        [DocumentoController::class, 'downloadSpecific']
    )->whereNumber('idVersion');

    // Instituciones firmantes
    Route::get('/instituciones-firmantes', [InstitucionFirmanteController::class, 'index']);
    Route::get('/instituciones-firmantes/{id}', [InstitucionFirmanteController::class, 'show'])
        ->whereNumber('id');
    Route::post('/instituciones-firmantes', [InstitucionFirmanteController::class, 'store'])
        ->middleware('can:administrar-catalogos');
    Route::put('/instituciones-firmantes/{id}', [InstitucionFirmanteController::class, 'update'])
        ->whereNumber('id');
    Route::patch('/instituciones-firmantes/{id}/estado', [InstitucionFirmanteController::class, 'cambiarEstado'])
        ->whereNumber('id')->middleware('can:administrar-catalogos');

    // Representantes
    Route::get('/representantes', [RepresentanteController::class, 'index']);
    Route::get('/representantes/{id}', [RepresentanteController::class, 'show'])->whereNumber('id');
    Route::post('/representantes', [RepresentanteController::class, 'store']);
    Route::put('/representantes/{id}', [RepresentanteController::class, 'update'])->whereNumber('id');
    Route::patch('/representantes/{id}/estado', [RepresentanteController::class, 'cambiarEstado'])
        ->whereNumber('id')->middleware('can:administrar-catalogos');

    // Estudiantes
    Route::get('/estudiantes', [EstudianteController::class, 'index']);
    Route::get('/estudiantes/{id}', [EstudianteController::class, 'show'])->whereNumber('id');
    Route::post('/estudiantes', [EstudianteController::class, 'store']);
    Route::put('/estudiantes/{id}', [EstudianteController::class, 'update'])->whereNumber('id');
    Route::delete('/estudiantes/{id}', [EstudianteController::class, 'destroy'])->whereNumber('id');

    // Docentes
    Route::get('/docentes', [DocenteController::class, 'index']);
    Route::get('/docentes/{id}', [DocenteController::class, 'show'])->whereNumber('id');
    Route::post('/docentes', [DocenteController::class, 'store']);
    Route::put('/docentes/{id}', [DocenteController::class, 'update'])->whereNumber('id');
    Route::delete('/docentes/{id}', [DocenteController::class, 'destroy'])->whereNumber('id');

    // Unidades académicas
    Route::get('/unidades-academicas', [UnidadAcademicaController::class, 'index']);
    Route::post('/unidades-academicas', [UnidadAcademicaController::class, 'store'])
        ->middleware('can:administrar-catalogos');
    Route::get('/unidades-academicas/{id}', [UnidadAcademicaController::class, 'show'])
        ->whereNumber('id');
    Route::put('/unidades-academicas/{id}', [UnidadAcademicaController::class, 'update'])
        ->whereNumber('id');
    Route::patch('/unidades-academicas/{id}/estado', [UnidadAcademicaController::class, 'cambiarEstado'])
        ->whereNumber('id')->middleware('can:administrar-catalogos');

    // Usuarios
    Route::middleware('can:gestionar-usuarios')->group(function () {
        Route::get('/roles', [RolController::class, 'index']);
        Route::get('/usuarios', [UsuarioController::class, 'index']);
        Route::post('/usuarios', [UsuarioController::class, 'store']);
        Route::get('/usuarios/{id}', [UsuarioController::class, 'show'])->whereNumber('id');
        Route::put('/usuarios/{id}', [UsuarioController::class, 'update'])->whereNumber('id');
        Route::delete('/usuarios/{id}', [UsuarioController::class, 'destroy'])->whereNumber('id');
    });

    // Auditoría
    Route::get('/auditoria', [AuditoriaController::class, 'index']);

    // Configuración
    Route::get('/configuracion/umbrales', [ConfiguracionController::class, 'umbrales']);
    Route::put('/configuracion/umbrales', [ConfiguracionController::class, 'actualizarUmbrales']);

    // Unificación de instituciones
    Route::post('/instituciones-firmantes/unificar', [InstitucionUnificacionController::class, 'unificar'])
        ->middleware('can:administrar-catalogos');

    Route::get('/convenios/{id}/trazabilidad', [ObservacionTrazabilidadController::class, 'show'])
        ->whereNumber('id');

    Route::post('/convenios/{id}/archivar', [ArchivarConvenioController::class, 'archivar'])
        ->whereNumber('id');

    Route::post('/convenios/{id}/rechazar', [RechazarConvenioController::class, 'rechazar'])
        ->whereNumber('id');

    Route::post('/convenios/{id}/aprobar', [AprobarConvenioController::class, 'aprobar'])
        ->whereNumber('id');

    Route::get('/firmas/pendientes', [FirmaController::class, 'pendientes']);
    Route::get('/firmas/{id}/documento', [FirmaController::class, 'documento'])->whereNumber('id');
    Route::post('/convenios/{id}/firmar', [FirmaController::class, 'firmar'])->whereNumber('id');
    Route::get('/convenios/{id}/firmas', [FirmaController::class, 'index'])->whereNumber('id');
    Route::put('/convenios/{id}/firmantes', [FirmaController::class, 'definirFirmantes'])->whereNumber('id');
    Route::get('/convenios/{id}/firmas/{idPersona}/trazo', [FirmaController::class, 'trazo'])
        ->whereNumber(['id', 'idPersona']);

    Route::get('/panel', [PanelController::class, 'index']);

    // Comentarios firmador
    Route::get('/convenios/{id}/comentarios-firmador', [ComentarioFirmadorController::class, 'index'])->whereNumber('id');
    Route::post('/convenios/{id}/comentarios-firmador', [ComentarioFirmadorController::class, 'store'])->whereNumber('id');

    Route::get('/convenios/{id}/constancia-firma', [FirmaController::class, 'constanciaPdf'])->whereNumber('id');
});
