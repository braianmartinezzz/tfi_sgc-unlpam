<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegistrarPushTokenRequest;
use App\Models\Notificacion;
use App\Models\PushToken;
use App\Services\ExpoPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    public function registrarPush(RegistrarPushTokenRequest $request, ExpoPushService $push): JsonResponse
    {
        $token = $request->validated('push_token');

        if (! $push->tokenValido($token)) {
            return response()->json(['message' => 'El token de notificaciones no es válido.'], 422);
        }

        PushToken::query()->updateOrCreate(
            ['token' => $token],
            [
                'id_usuario' => $request->user()->id_usuario,
                'dispositivo' => $request->validated('dispositivo'),
                'ultimo_uso' => now(),
            ]
        );

        return response()->json(['message' => 'Notificaciones push registradas.']);
    }

    public function eliminarPush(Request $request): JsonResponse
    {
        $token = (string) $request->input('push_token', '');

        PushToken::query()
            ->where('id_usuario', $request->user()->id_usuario)
            ->where('token', $token)
            ->delete();

        return response()->json(['message' => 'Token de notificaciones eliminado.']);
    }

    public function marcarLeida(Request $request, int $id): JsonResponse
    {
        $notificacion = Notificacion::query()
            ->whereKey($id)
            ->where('id_usuario', $request->user()->id_usuario)
            ->first();

        if (! $notificacion) {
            return response()->json(['message' => 'Notificación no encontrada.'], 404);
        }

        $notificacion->forceFill(['leida' => true])->save();

        return response()->json([
            'message' => 'Notificación marcada como leída.',
            'data' => $notificacion,
        ]);
    }

    public function marcarTodasLeidas(Request $request): JsonResponse
    {
        $cantidad = Notificacion::query()
            ->where('id_usuario', $request->user()->id_usuario)
            ->where('leida', false)
            ->update(['leida' => true]);

        return response()->json([
            'message' => 'Notificaciones marcadas como leídas.',
            'cantidad' => $cantidad,
        ]);
    }
}
