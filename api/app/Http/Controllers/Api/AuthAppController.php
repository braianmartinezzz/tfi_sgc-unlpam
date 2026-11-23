<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\MfaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ActivarBiometriaRequest;
use App\Http\Requests\AppLoginRequest;
use App\Http\Requests\DesactivarBiometriaRequest;
use App\Http\Requests\EnviarCodigoMfaRequest;
use App\Http\Requests\VerificarCodigoMfaRequest;
use App\Services\AuthAppService;
use App\Services\MfaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Login de la app móvil: contraseña + MFA, biometría y sesión por inactividad. */
class AuthAppController extends Controller
{
    /** Paso 1: contraseña. Si es correcta devuelve el desafío MFA (todavía no hay token de sesión). */
    public function login(AppLoginRequest $request, AuthAppService $service): JsonResponse
    {
        $resultado = $service->iniciarLogin($request->validated());

        if ($resultado === null) {
            return response()->json(['message' => 'Credenciales inválidas.'], 401);
        }

        return response()->json([
            'message' => 'Ingresá el código de verificación.',
            'requiere_mfa' => true,
            'mfa_token' => $resultado['mfa_token'],
            'canales' => $resultado['canales'],
        ]);
    }

    /** Paso 2: el usuario elige cómo recibir el código (también sirve para reenviarlo). */
    public function enviarCodigo(EnviarCodigoMfaRequest $request, MfaService $mfa): JsonResponse
    {
        try {
            $envio = $mfa->enviarCodigo(
                $request->validated('mfa_token'),
                $request->validated('canal'),
                $request->validated('push_token')
            );
        } catch (MfaException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return response()->json(['message' => 'Código enviado.'] + $envio);
    }

    /** Paso 3: con el código correcto se abre la sesión. */
    public function verificarCodigo(
        VerificarCodigoMfaRequest $request,
        MfaService $mfa,
        AuthAppService $service
    ): JsonResponse {
        try {
            $usuario = $mfa->verificarCodigo(
                $request->validated('mfa_token'),
                $request->validated('codigo')
            );
        } catch (MfaException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return $this->respuestaSesion($service->abrirSesion($usuario));
    }

    /** Ingreso con biometría: llega con el token del dispositivo en el Authorization. */
    public function loginBiometrico(Request $request, AuthAppService $service): JsonResponse
    {
        $token = $request->bearerToken();
        $resultado = $token ? $service->loginBiometrico($token) : null;

        if ($resultado === null) {
            return response()->json([
                'message' => 'La biometría ya no está habilitada. Ingresá con tu contraseña.',
            ], 401);
        }

        return $this->respuestaSesion($resultado);
    }

    /** Activa la biometría en este dispositivo (se pide la contraseña). */
    public function activarBiometria(ActivarBiometriaRequest $request, AuthAppService $service): JsonResponse
    {
        $token = $service->activarBiometria($request->user(), $request->validated('password'));

        if ($token === null) {
            return response()->json([
                'message' => 'La contraseña no es correcta.',
                'errors' => ['password' => ['La contraseña no es correcta.']],
            ], 422);
        }

        return response()->json([
            'message' => 'Ingreso con biometría activado.',
            'token_dispositivo' => $token,
            'dias_validez' => (int) config('sgc.biometria.dias_validez'),
        ], 201);
    }

    public function desactivarBiometria(DesactivarBiometriaRequest $request, AuthAppService $service): JsonResponse
    {
        $service->desactivarBiometria($request->user(), $request->validated('token_dispositivo'));

        return response()->json(['message' => 'Ingreso con biometría desactivado.']);
    }

    private function respuestaSesion(array $sesion): JsonResponse
    {
        return response()->json([
            'message' => 'Inicio de sesión exitoso.',
            'access_token' => $sesion['access_token'],
            'token_type' => 'Bearer',
            'user' => $sesion['user'],
            'sesion_expira_en_minutos' => $sesion['sesion_expira_en_minutos'],
        ]);
    }
}
