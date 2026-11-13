<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UsuarioRequest;
use App\Services\UsuarioService;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsuarioController extends Controller
{
    public function index(Request $request, UsuarioService $service): JsonResponse
    {
        $usuarios = $service->paginate($request->only([
            'id_rol',
            'id_unidad',
            'activo',
            'buscar',
        ]));

        return response()->json($usuarios);
    }

    public function show(string $id, UsuarioService $service): JsonResponse
    {
        $usuario = $service->findById($id);

        if ($usuario === null) {
            return response()->json([
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        return response()->json([
            'data' => $usuario,
        ]);
    }

    public function store(UsuarioRequest $request, UsuarioService $service): JsonResponse
    {
        $usuario = $service->store($request->validated());

        return response()->json([
            'message' => 'Usuario creado correctamente.',
            'data' => $usuario,
        ], 201);
    }

    public function update(UsuarioRequest $request, string $id, UsuarioService $service): JsonResponse
    {
        try {
            $usuario = $service->update(
                $id,
                $request->validated(),
                (int) $request->user()->id_usuario
            );
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Usuario no encontrado.',
            ], 404);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        }

        return response()->json([
            'message' => 'Usuario actualizado correctamente.',
            'data' => $usuario,
        ]);
    }

    public function destroy(Request $request, string $id, UsuarioService $service): JsonResponse
    {
        try {
            $service->destroy($id, (int) $request->user()->id_usuario);
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Usuario no encontrado.',
            ], 404);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        }

        return response()->json([
            'message' => 'Usuario desactivado correctamente.',
        ]);
    }
}
