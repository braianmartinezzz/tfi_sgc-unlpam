<?php

namespace App\Services;

use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Login de la app móvil: contraseña + MFA, ingreso con biometría y sesión que
 * vence por inactividad. El login de la web (AuthService) no se toca.
 */
class AuthAppService
{
    public const TOKEN_SESION = 'app-movil';

    public const TOKEN_BIOMETRICO = 'app-biometrico';

    /** Habilidad exclusiva del token del dispositivo: solo sirve para abrir sesión. */
    public const HABILIDAD_BIOMETRICA = 'biometrico';

    public function __construct(private readonly MfaService $mfa) {}

    /** Paso 1: valida la contraseña y abre el desafío MFA. Null si las credenciales no son válidas. */
    public function iniciarLogin(array $credenciales): ?array
    {
        $usuario = Usuario::query()->where('mail', $credenciales['mail'])->first();

        if (
            ! $usuario
            || ! $usuario->activo
            || ! Hash::check($credenciales['password'], $usuario->password)
        ) {
            return null;
        }

        return [
            'mfa_token' => $this->mfa->crearDesafio($usuario),
            'canales' => MfaService::CANALES,
        ];
    }

    /** Abre una sesión de la app: el token vence tras N minutos sin uso. */
    public function abrirSesion(Usuario $usuario): array
    {
        $minutos = (int) config('sgc.sesion_app.inactividad_minutos');

        $token = $usuario
            ->createToken(self::TOKEN_SESION, ['*'], now()->addMinutes($minutos))
            ->plainTextToken;

        $usuario->load(['rol', 'unidadAcademica']);

        return [
            'access_token' => $token,
            'user' => $usuario,
            'sesion_expira_en_minutos' => $minutos,
        ];
    }

    /** Ingreso con biometría: el token del dispositivo se canjea por una sesión nueva. */
    public function loginBiometrico(string $tokenDispositivo): ?array
    {
        $token = PersonalAccessToken::findToken($tokenDispositivo);

        if (
            ! $token
            || $token->name !== self::TOKEN_BIOMETRICO
            || ! $token->can(self::HABILIDAD_BIOMETRICA)
            || $token->can('*')
            || ($token->expires_at !== null && $token->expires_at->isPast())
        ) {
            return null;
        }

        $usuario = $token->tokenable;

        if (! $usuario instanceof Usuario || ! $usuario->activo) {
            return null;
        }

        $token->forceFill(['last_used_at' => now()])->save();

        return $this->abrirSesion($usuario);
    }

    /** Activa la biometría en un dispositivo: pide la contraseña y entrega su token. */
    public function activarBiometria(Usuario $usuario, string $password): ?string
    {
        if (! Hash::check($password, $usuario->password)) {
            return null;
        }

        return $usuario
            ->createToken(
                self::TOKEN_BIOMETRICO,
                [self::HABILIDAD_BIOMETRICA],
                now()->addDays((int) config('sgc.biometria.dias_validez'))
            )
            ->plainTextToken;
    }

    /** Desactiva la biometría: el token indicado, o todos los del usuario si no se indica. */
    public function desactivarBiometria(Usuario $usuario, ?string $tokenDispositivo): void
    {
        $tokens = $usuario->tokens()->where('name', self::TOKEN_BIOMETRICO);

        if ($tokenDispositivo !== null && $tokenDispositivo !== '') {
            $token = PersonalAccessToken::findToken($tokenDispositivo);

            // Un token inexistente no borra nada: solo se revoca el que se indicó.
            if (! $token) {
                return;
            }

            $tokens->whereKey($token->getKey());
        }

        $tokens->delete();
    }
}
