<?php

namespace App\Services;

use App\Models\ComentarioFirmador;
use App\Models\Usuario;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ComentarioFirmadorService
{
    private const PALABRAS_OFENSIVAS = [
        'idiota', 'imbecil', 'imbécil', 'tarado', 'pelotudo', 'estupido', 'estúpido',
        'boludo', 'mogolico', 'mogólico', 'mierda', 'maldito', 'hdp', 'hijo de puta',
    ];

    public function listar(int $idInstrumento, Usuario $usuario): array
    {
        $this->autorizarFirmante($idInstrumento, $usuario);

        return ComentarioFirmador::query()
            ->with('usuario:id_usuario,nombre')
            ->where('id_instrumento', $idInstrumento)
            ->orderByDesc('fecha')
            ->get()
            ->map(fn (ComentarioFirmador $comentario) => [
                'id_comentario' => $comentario->id_comentario,
                'contenido' => $comentario->contenido,
                'ofensivo' => $comentario->ofensivo,
                'fecha' => $comentario->fecha?->toIso8601String(),
                'usuario' => [
                    'id_usuario' => $comentario->usuario->id_usuario,
                    'nombre' => $comentario->usuario->nombre,
                ],
            ])
            ->values()
            ->all();
    }

    public function crear(int $idInstrumento, string $contenido, Usuario $usuario): array
    {
        $this->autorizarFirmante($idInstrumento, $usuario);

        $contenido = trim($contenido);
        if ($contenido === '') {
            throw new \InvalidArgumentException('El comentario no puede estar vacío.');
        }

        $ofensivo = $this->detectarOfensivo($contenido);
        $comentario = ComentarioFirmador::query()->create([
            'id_instrumento' => $idInstrumento,
            'id_usuario' => $usuario->id_usuario,
            'contenido' => $ofensivo ? 'comentario ofensivo' : mb_substr($contenido, 0, 1000),
            'ofensivo' => $ofensivo,
            'fecha' => now(),
        ]);

        $comentario->refresh();

        $comentario->load('usuario:id_usuario,nombre');

        return [
            'id_comentario' => $comentario->id_comentario,
            'contenido' => $comentario->contenido,
            'ofensivo' => $comentario->ofensivo,
            'fecha' => $comentario->fecha?->toIso8601String(),
            'usuario' => [
                'id_usuario' => $comentario->usuario->id_usuario,
                'nombre' => $comentario->usuario->nombre,
            ],
        ];
    }

    private function autorizarFirmante(int $idInstrumento, Usuario $usuario): void
    {
        if ($usuario->id_persona === null) {
            throw new AuthorizationException('Tu usuario no está vinculado a una persona firmante.');
        }

        $habilitado = DB::table('firma')
            ->where('id_instrumento', $idInstrumento)
            ->where('id_persona', $usuario->id_persona)
            ->exists();

        if (! $habilitado) {
            throw new AuthorizationException('No participás como firmante de este convenio.');
        }
    }

    private function detectarOfensivo(string $contenido): bool
    {
        $texto = mb_strtolower(trim($contenido));
        $texto = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $texto);

        foreach (self::PALABRAS_OFENSIVAS as $palabra) {
            $palabra = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], mb_strtolower($palabra));
            if (str_contains($texto, $palabra)) return true;
        }

        return false;
    }
}
