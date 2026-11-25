<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Envío de notificaciones push reales a través del servicio de Expo. */
class ExpoPushService
{
    public function tokenValido(?string $token): bool
    {
        return is_string($token)
            && preg_match('/^(Exponent|Expo)PushToken\[[^\]\s]+\]$/', $token) === 1;
    }

    public function enviar(string $token, string $titulo, string $cuerpo, array $datos = []): bool
    {
        try {
            $peticion = Http::acceptJson()->timeout(5);

            if ($accessToken = config('sgc.expo_push.access_token')) {
                $peticion = $peticion->withToken($accessToken);
            }

            $respuesta = $peticion->post(config('sgc.expo_push.url'), [
                'to' => $token,
                'title' => $titulo,
                'body' => $cuerpo,
                'data' => (object) $datos,
                'sound' => 'default',
                'priority' => 'high',
            ]);
        } catch (Throwable $e) {
            Log::warning('Expo Push: no se pudo conectar.', ['error' => $e->getMessage()]);

            return false;
        }

        if (! $respuesta->successful()) {
            Log::warning('Expo Push: respuesta con error.', ['status' => $respuesta->status()]);

            return false;
        }

        $data = $respuesta->json('data');
        $ticket = is_array($data) && array_key_exists('status', $data) ? $data : ($data[0] ?? []);

        return ($ticket['status'] ?? null) === 'ok';
    }

    public function enviarAUsuario(int $idUsuario, string $titulo, string $cuerpo, array $datos = []): int
    {
        $enviadas = 0;

        foreach (
            \App\Models\PushToken::query()->where('id_usuario', $idUsuario)->get(['token']) as $registro
        ) {
            if ($this->enviar($registro->token, $titulo, $cuerpo, $datos)) {
                $enviadas++;
            }
        }

        return $enviadas;
    }
}
