<?php

namespace App\Services;

use App\Models\Instrumento;
use Carbon\Carbon;

class VencimientoService
{
    public const DIAS_POR_DEFECTO = 90;

    public function proximos(array $filters): array
    {
        $dias = (int) ($filters['dias'] ?? self::DIAS_POR_DEFECTO);
        $hoy = Carbon::today();
        $limite = $hoy->copy()->addDays($dias);

        $query = Instrumento::query()
            ->whereNotNull('vencimiento')
            ->whereDate('vencimiento', '>=', $hoy->toDateString())
            ->whereDate('vencimiento', '<=', $limite->toDateString())
            ->whereHas('convenio', function ($convenioQuery) use ($filters) {
                if (isset($filters['id_unidad_academica']) && $filters['id_unidad_academica'] !== '') {
                    $convenioQuery->where('id_unidad_academica', $filters['id_unidad_academica']);
                }
            })
            ->with(['convenio.unidadAcademica', 'convenio.institucionesFirmantes'])
            ->orderBy('vencimiento');

        if (isset($filters['estado']) && $filters['estado'] !== '') {
            $query->where('estado', $filters['estado']);
        }

        return [
            'dias' => $dias,
            'desde' => $hoy->toDateString(),
            'hasta' => $limite->toDateString(),
            'items' => $query->get()
                ->map(fn (Instrumento $instrumento) => $this->formatear($instrumento, $hoy))
                ->values()
                ->all(),
        ];
    }

    private function formatear(Instrumento $instrumento, Carbon $hoy): array
    {
        $convenio = $instrumento->convenio;
        $vencimiento = $instrumento->vencimiento->copy()->startOfDay();
        $preaviso = $instrumento->dias_preaviso;

        return [
            'id_instrumento' => $instrumento->id_instrumento,
            'titulo' => $instrumento->titulo,
            'estado' => $instrumento->estado,
            'tipo' => $convenio->tipo,
            'vencimiento' => $vencimiento->toDateString(),
            'dias_restantes' => (int) $hoy->diffInDays($vencimiento, false),
            'dias_preaviso' => $preaviso,
            'en_preaviso' => $preaviso !== null
                && $hoy->greaterThanOrEqualTo($vencimiento->copy()->subDays($preaviso)),
            'unidad_academica' => $convenio->unidadAcademica?->nombre,
            'instituciones_firmantes' => $convenio->institucionesFirmantes
                ->pluck('razon_social')
                ->all(),
        ];
    }
}