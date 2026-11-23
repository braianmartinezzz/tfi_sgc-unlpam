<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ComentarioFirmadorRequest;
use App\Services\ComentarioFirmadorService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComentarioFirmadorController extends Controller
{
    public function index(Request $request, string $id, ComentarioFirmadorService $service): JsonResponse
    {
        try {
            return response()->json([
                'data' => $service->listar((int) $id, $request->user()),
            ]);
        } catch (AuthorizationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }
    }

    public function store(ComentarioFirmadorRequest $request, string $id, ComentarioFirmadorService $service): JsonResponse
    {
        try {
            $comentario = $service->crear(
                (int) $id,
                $request->validated('contenido'),
                $request->user()
            );

            return response()->json([
                'message' => $comentario['ofensivo']
                    ? 'Se reemplazó el texto por “comentario ofensivo”.'
                    : 'Comentario publicado.',
                'data' => $comentario,
            ], 201);
        } catch (AuthorizationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
