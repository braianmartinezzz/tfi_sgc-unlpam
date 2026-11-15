<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AprobarConvenioService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AprobarConvenioController extends Controller
{
    public function aprobar(
        Request $request,
        string $id,
        AprobarConvenioService $service
    ): JsonResponse {
        try {
            $convenio = $service->aprobar($id, $request->user());
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Convenio no encontrado.',
            ], 404);
        } catch (AuthorizationException) {
            return response()->json([
                'message' => 'No tiene permisos para aprobar este convenio.',
            ], 403);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        }

        return response()->json([
            'message' => 'Convenio aprobado correctamente.',
            'data' => [
                'instrumento' => $convenio->instrumento,
                'convenio' => $convenio,
                'subtipo' => $convenio->marco ?? $convenio->especifico,
                'instituciones_firmantes' => $convenio->institucionesFirmantes,
            ],
        ]);
    }
}
