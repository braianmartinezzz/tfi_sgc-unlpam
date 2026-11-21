<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RechazarConvenioRequest;
use App\Services\RechazarConvenioService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class RechazarConvenioController extends Controller
{
    public function rechazar(
        RechazarConvenioRequest $request,
        string $id,
        RechazarConvenioService $service
    ): JsonResponse {
        try {
            $convenio = $service->rechazar(
                $id,
                $request->validated()['motivo'],
                $request->user()
            );
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Convenio no encontrado.',
            ], 404);
        } catch (AuthorizationException) {
            return response()->json([
                'message' => 'No tiene permisos para rechazar este convenio.',
            ], 403);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        }

        return response()->json([
            'message' => 'Convenio rechazado correctamente.',
            'data' => [
                'instrumento' => $convenio->instrumento,
                'convenio' => $convenio,
                'subtipo' => $convenio->marco ?? $convenio->especifico,
                'instituciones_firmantes' => $convenio->institucionesFirmantes,
            ],
        ]);
    }
}
