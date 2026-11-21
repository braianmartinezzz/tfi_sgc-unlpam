<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ArchivarConvenioRequest;
use App\Services\ArchivarConvenioService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class ArchivarConvenioController extends Controller
{
    public function archivar(
        ArchivarConvenioRequest $request,
        string $id,
        ArchivarConvenioService $service
    ): JsonResponse {
        try {
            $convenio = $service->archivar(
                $id,
                $request->validated()['motivo'] ?? null,
                $request->user()
            );
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Convenio no encontrado.',
            ], 404);
        } catch (AuthorizationException) {
            return response()->json([
                'message' => 'No tiene permisos para archivar este convenio.',
            ], 403);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        }

        return response()->json([
            'message' => 'Convenio archivado correctamente.',
            'data' => [
                'instrumento' => $convenio->instrumento,
                'convenio' => $convenio,
                'subtipo' => $convenio->marco ?? $convenio->especifico,
                'instituciones_firmantes' => $convenio->institucionesFirmantes,
            ],
        ]);
    }
}
