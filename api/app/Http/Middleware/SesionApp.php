<?php

namespace App\Http\Middleware;

use App\Services\AuthAppService;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reglas de los tokens de la app móvil sobre las rutas autenticadas:
 *
 * - El token de sesión de la app vence tras N minutos sin uso: cada pedido
 *   autenticado corre el vencimiento (el pedido vencido ya lo rechaza Sanctum con 401).
 * - El token de biometría del dispositivo no sirve para usar la API: solo para
 *   abrir una sesión en /api/app/login/biometrico.
 */
class SesionApp
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            if ($token->can(AuthAppService::HABILIDAD_BIOMETRICA) && ! $token->can('*')) {
                return response()->json([
                    'message' => 'Este token solo sirve para ingresar con biometría.',
                ], 403);
            }

            if ($token->name === AuthAppService::TOKEN_SESION) {
                $token->forceFill([
                    'expires_at' => now()->addMinutes((int) config('sgc.sesion_app.inactividad_minutos')),
                ])->save();
            }
        }

        return $next($request);
    }
}
