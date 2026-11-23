<?php

namespace App\Services;

use App\Exceptions\MfaException;
use App\Mail\CodigoMfaMail;
use App\Models\Usuario;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Verificación en dos pasos del login de la app.
 *
 * El "desafío" se guarda en la caché (sin tablas nuevas): usuario, hash del
 * código, vencimiento e intentos. El código nunca se guarda en claro.
 */
class MfaService
{
    public const CANAL_MAIL = 'mail';

    public const CANAL_SMS = 'sms';

    public const CANAL_PUSH = 'push';

    public const CANALES = [self::CANAL_MAIL, self::CANAL_SMS, self::CANAL_PUSH];

    public function __construct(private readonly ExpoPushService $expoPush) {}

    /** Abre un desafío para un usuario que ya validó su contraseña. Devuelve el mfa_token. */
    public function crearDesafio(Usuario $usuario): string
    {
        $id = Str::random(48);

        $this->guardar($id, [
            'id_usuario' => $usuario->getKey(),
            'hash' => null,
            'codigo_vence' => null,
            'intentos' => 0,
            'envios' => 0,
            'vence' => now()->addMinutes((int) config('sgc.mfa.desafio_expira_minutos'))->getTimestamp(),
        ]);

        return $id;
    }

    /**
     * Genera un código nuevo (invalida el anterior) y lo envía por el canal elegido.
     *
     * @return array{canal: string, destino: string, expira_en_segundos: int, codigo_simulado?: string}
     *
     * @throws MfaException
     */
    public function enviarCodigo(string $id, string $canal, ?string $pushToken = null): array
    {
        $desafio = $this->obtener($id);

        if ($desafio['envios'] >= (int) config('sgc.mfa.max_envios')) {
            $this->olvidar($id);
            throw new MfaException('Pediste demasiados códigos. Volvé a iniciar sesión.', 429);
        }

        $usuario = Usuario::query()->find($desafio['id_usuario']);

        if (! $usuario || ! $usuario->activo) {
            $this->olvidar($id);
            throw new MfaException('La verificación venció. Volvé a iniciar sesión.');
        }

        if ($canal === self::CANAL_PUSH && ! $this->expoPush->tokenValido($pushToken)) {
            throw new MfaException(
                'Este dispositivo no tiene habilitadas las notificaciones push. Elegí otro canal.',
                422
            );
        }

        $minutos = (int) config('sgc.mfa.codigo_expira_minutos');
        $codigo = $this->generarCodigo();

        $destino = match ($canal) {
            self::CANAL_MAIL => $this->enviarPorMail($usuario, $codigo, $minutos),
            self::CANAL_SMS => $this->enviarPorSms($usuario, $codigo, $minutos),
            self::CANAL_PUSH => $this->enviarPorPush($pushToken, $codigo, $minutos),
        };

        // Solo se guarda (y se cuenta el envío) si el canal entregó el código.
        $desafio['hash'] = $this->hashear($codigo);
        $desafio['codigo_vence'] = now()->addMinutes($minutos)->getTimestamp();
        $desafio['intentos'] = 0;
        $desafio['envios']++;
        $this->guardar($id, $desafio);

        $respuesta = [
            'canal' => $canal,
            'destino' => $destino,
            'expira_en_segundos' => $minutos * 60,
        ];

        // Mail y SMS son simulados: el código se expone solo si la config lo permite.
        if (config('sgc.mfa.exponer_codigo_simulado') && $canal !== self::CANAL_PUSH) {
            $respuesta['codigo_simulado'] = $codigo;
        }

        return $respuesta;
    }

    /**
     * Valida el código. Si es correcto consume el desafío y devuelve el usuario.
     *
     * @throws MfaException
     */
    public function verificarCodigo(string $id, string $codigo): Usuario
    {
        $desafio = $this->obtener($id);

        if ($desafio['hash'] === null) {
            throw new MfaException('Primero pedí el código por mail, SMS o push.', 422);
        }

        if (now()->getTimestamp() > $desafio['codigo_vence']) {
            throw new MfaException('El código venció. Pedí uno nuevo.');
        }

        $maximo = (int) config('sgc.mfa.max_intentos');

        if (! hash_equals($desafio['hash'], $this->hashear($codigo))) {
            $desafio['intentos']++;

            if ($desafio['intentos'] >= $maximo) {
                $this->olvidar($id);
                throw new MfaException('Demasiados intentos fallidos. Volvé a iniciar sesión.', 429);
            }

            $this->guardar($id, $desafio);
            $restantes = $maximo - $desafio['intentos'];

            throw new MfaException(
                "Código incorrecto. Te quedan {$restantes} ".($restantes === 1 ? 'intento.' : 'intentos.')
            );
        }

        $usuario = Usuario::query()->find($desafio['id_usuario']);
        $this->olvidar($id);

        if (! $usuario || ! $usuario->activo) {
            throw new MfaException('La verificación venció. Volvé a iniciar sesión.');
        }

        return $usuario;
    }

    private function enviarPorMail(Usuario $usuario, string $codigo, int $minutos): string
    {
        Mail::to($usuario->mail)->send(new CodigoMfaMail($codigo, $minutos));

        return $this->enmascararMail($usuario->mail);
    }

    private function enviarPorSms(Usuario $usuario, string $codigo, int $minutos): string
    {
        // SMS simulado: no hay teléfono cargado ni proveedor; queda registrado en el log.
        Log::info("[SMS simulado] Usuario #{$usuario->getKey()}: tu código de verificación es {$codigo} (vence en {$minutos} minutos).");

        return 'Teléfono registrado (simulado)';
    }

    private function enviarPorPush(string $pushToken, string $codigo, int $minutos): string
    {
        $enviado = $this->expoPush->enviar(
            $pushToken,
            'Código de verificación',
            "Tu código es {$codigo}. Vence en {$minutos} minutos.",
            ['tipo' => 'mfa']
        );

        if (! $enviado) {
            throw new MfaException('No se pudo enviar la notificación push. Probá con otro canal.', 502);
        }

        return 'Este dispositivo';
    }

    private function generarCodigo(): string
    {
        $longitud = (int) config('sgc.mfa.longitud');

        return str_pad((string) random_int(0, (10 ** $longitud) - 1), $longitud, '0', STR_PAD_LEFT);
    }

    private function hashear(string $codigo): string
    {
        return hash_hmac('sha256', $codigo, (string) config('app.key'));
    }

    private function enmascararMail(string $mail): string
    {
        [$usuario, $dominio] = array_pad(explode('@', $mail, 2), 2, '');

        return mb_substr($usuario, 0, 1).'***@'.$dominio;
    }

    private function clave(string $id): string
    {
        return 'mfa:'.hash('sha256', $id);
    }

    /** @throws MfaException */
    private function obtener(string $id): array
    {
        $desafio = Cache::get($this->clave($id));

        if (! is_array($desafio) || now()->getTimestamp() > $desafio['vence']) {
            throw new MfaException('La verificación venció. Volvé a iniciar sesión.');
        }

        return $desafio;
    }

    private function guardar(string $id, array $desafio): void
    {
        Cache::put($this->clave($id), $desafio, now()->setTimestamp($desafio['vence']));
    }

    private function olvidar(string $id): void
    {
        Cache::forget($this->clave($id));
    }
}
