<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(LoginRequest $request, AuthService $service): JsonResponse
    {
        $result = $service->login($request->validated());

        if ($result === null) {
            return response()->json([
                'message' => 'Credenciales inválidas.',
            ], 401);
        }

        return response()->json([
            'message' => 'Inicio de sesión exitoso.',
            'access_token' => $result['access_token'],
            'token_type' => 'Bearer',
            'user' => $result['user'],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $usuario = $request->user()->load(['rol', 'unidadAcademica']);

        return response()->json([
            'user' => $usuario,
        ]);
    }

    public function logout(Request $request, AuthService $service): JsonResponse
    {
        $service->logout($request->user());

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }
}
