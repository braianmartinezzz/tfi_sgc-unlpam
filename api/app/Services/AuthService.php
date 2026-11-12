<?php

namespace App\Services;

use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    /**
     * @return array{access_token: string, user: Usuario}|null
     */
    public function login(array $credentials): ?array
    {
        $usuario = Usuario::query()
            ->where('mail', $credentials['mail'])
            ->first();

        if (
            ! $usuario
            || ! $usuario->activo
            || ! Hash::check($credentials['password'], $usuario->password)
        ) {
            return null;
        }

        $accessToken = $usuario->createToken('auth-token')->plainTextToken;
        $usuario->load(['rol', 'unidadAcademica']);

        return [
            'access_token' => $accessToken,
            'user' => $usuario,
        ];
    }

    public function logout(Usuario $usuario): void
    {
        $token = $usuario->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }
}
