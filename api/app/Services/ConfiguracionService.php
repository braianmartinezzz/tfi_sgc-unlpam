<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfiguracionService
{
    /**
     * Umbrales de alerta configurables y su valor por defecto (si el administrador nunca los cambió).
     *
     * - dias_preaviso_defecto: días de preaviso que se asignan a un convenio nuevo.
     * - dias_ventana_vencimientos: ventana (en días) con la que se consultan los vencimientos próximos.
     */
    public const UMBRALES = [
        'dias_preaviso_defecto' => 30,
        'dias_ventana_vencimientos' => 90,
    ];

    public function umbrales(): array
    {
        $guardados = DB::table('configuracion')
            ->whereIn('clave', array_keys(self::UMBRALES))
            ->pluck('valor', 'clave');

        $umbrales = [];

        foreach (self::UMBRALES as $clave => $porDefecto) {
            $umbrales[$clave] = isset($guardados[$clave]) ? (int) $guardados[$clave] : $porDefecto;
        }

        return $umbrales;
    }

    public function diasPreavisoDefecto(): int
    {
        return $this->umbrales()['dias_preaviso_defecto'];
    }

    public function diasVentanaVencimientos(): int
    {
        return $this->umbrales()['dias_ventana_vencimientos'];
    }

    public function actualizarUmbrales(array $data): array
    {
        $cambios = array_intersect_key($data, self::UMBRALES);
        $resultado = array_merge($this->umbrales(), $cambios);

        if ($resultado['dias_preaviso_defecto'] > $resultado['dias_ventana_vencimientos']) {
            throw ValidationException::withMessages([
                'dias_preaviso_defecto' => [
                    'Los días de preaviso por defecto no pueden superar la ventana de vencimientos.',
                ],
            ]);
        }

        DB::transaction(function () use ($cambios) {
            foreach ($cambios as $clave => $valor) {
                DB::table('configuracion')->updateOrInsert(
                    ['clave' => $clave],
                    ['valor' => (string) $valor]
                );
            }
        });

        return $this->umbrales();
    }
}
