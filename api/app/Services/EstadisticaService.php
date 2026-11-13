<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class EstadisticaService
{
    public function resumen(int $dias = 90, ?int $idUnidad = null): array
    {
        $hoy = Carbon::today();
        $hasta = $hoy->copy()->addDays($dias);

        $base = fn (): Builder => DB::table('convenio as c')
            ->join('instrumento as i', 'i.id_instrumento', '=', 'c.id_instrumento')
            ->when($idUnidad, fn ($q) => $q->where('c.id_unidad_academica', $idUnidad));

        $total = $base()->count();
        $marco = $base()
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('marco as m')
                ->whereColumn('m.id_instrumento', 'c.id_instrumento'))
            ->count();

        $porEstado = $base()
            ->select('i.estado', DB::raw('COUNT(*) as total'))
            ->groupBy('i.estado')
            ->orderByDesc('total')
            ->get();

        $porTipo = $base()
            ->select('c.tipo', DB::raw('COUNT(*) as total'))
            ->groupBy('c.tipo')
            ->orderByDesc('total')
            ->get();

        $porUnidad = $base()
            ->leftJoin('unidad_academica as u', 'u.id_unidad_academica', '=', 'c.id_unidad_academica')
            ->select('u.id_unidad_academica', 'u.sigla', 'u.nombre', DB::raw('COUNT(*) as total'))
            ->groupBy('u.id_unidad_academica', 'u.sigla', 'u.nombre')
            ->orderByDesc('total')
            ->get();

        $vencidos = $base()
            ->whereNotNull('i.vencimiento')
            ->whereDate('i.vencimiento', '<', $hoy->toDateString())
            ->count();

        // Se calcula el preaviso en PHP para no depender de funciones de fecha del motor (sqlite/mysql).
        $proximos = $base()
            ->whereNotNull('i.vencimiento')
            ->whereDate('i.vencimiento', '>=', $hoy->toDateString())
            ->whereDate('i.vencimiento', '<=', $hasta->toDateString())
            ->get(['i.vencimiento', 'i.dias_preaviso']);

        $enPreaviso = $proximos->filter(function ($r) use ($hoy) {
            $restantes = $hoy->diffInDays(Carbon::parse($r->vencimiento), false);
            return $r->dias_preaviso !== null && $restantes <= (int) $r->dias_preaviso;
        })->count();

        return [
            'totales' => [
                'convenios' => $total,
                'marco' => $marco,
                'especificos' => $total - $marco,
            ],
            'por_estado' => $porEstado,
            'por_tipo' => $porTipo,
            'por_unidad_academica' => $porUnidad,
            'vencimientos' => [
                'dias' => $dias,
                'vencidos' => $vencidos,
                'proximos' => $proximos->count(),
                'en_preaviso' => $enPreaviso,
            ],
        ];
    }
}