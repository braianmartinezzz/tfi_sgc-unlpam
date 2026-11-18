<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class EstadisticaService
{
    /**
     * @param  array{id_unidad_academica: ?int, estados: ?array}  $scope
     * @param  array{fecha_desde?: ?string, fecha_hasta?: ?string, tipo?: ?string}  $filters
     */
    public function resumen(int $dias, array $scope, array $filters = []): array
    {
        $hoy = Carbon::today();
        $hasta = $hoy->copy()->addDays($dias);
        $idUnidad = $scope['id_unidad_academica'];
        $estados = $scope['estados'];
        $fechaDesde = $filters['fecha_desde'] ?? null;
        $fechaHasta = $filters['fecha_hasta'] ?? null;
        $tipo = $filters['tipo'] ?? null;

        $base = fn (): Builder => DB::table('convenio as c')
            ->join('instrumento as i', 'i.id_instrumento', '=', 'c.id_instrumento')
            ->when(
                $idUnidad !== null,
                fn ($q) => $q->where('c.id_unidad_academica', $idUnidad)
            )
            ->when(
                $estados !== null,
                fn ($q) => $q->whereIn('i.estado', $estados)
            )
            ->when(
                $fechaDesde !== null,
                fn ($q) => $q->whereDate('i.fecha_firma', '>=', $fechaDesde)
            )
            ->when(
                $fechaHasta !== null,
                fn ($q) => $q->whereDate('i.fecha_firma', '<=', $fechaHasta)
            )
            ->when(
                $tipo !== null,
                fn ($q) => $q->where('c.tipo', $tipo)
            );

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

        $yearExpression = match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST(strftime('%Y', i.fecha_firma) AS INTEGER)",
            'mysql', 'mariadb' => 'YEAR(i.fecha_firma)',
            'pgsql' => 'CAST(EXTRACT(YEAR FROM i.fecha_firma) AS INTEGER)',
            default => throw new \RuntimeException(
                'El motor de base de datos no admite agrupar fechas de firma por año.'
            ),
        };
        $porAnio = $base()
            ->whereNotNull('i.fecha_firma')
            ->selectRaw($yearExpression.' as anio, COUNT(*) as total')
            ->groupBy('anio')
            ->orderBy('anio')
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

        $conveniosVigentes = $base()
            ->where('i.estado', 'Firmado')
            ->whereNotNull('i.inicio')
            ->whereNotNull('i.vencimiento')
            ->whereDate('i.inicio', '<=', $hoy->toDateString())
            ->whereDate('i.vencimiento', '>=', $hoy->toDateString())
            ->count();

        $conveniosEnTramite = $base()
            ->whereIn('i.estado', [
                'Borrador',
                'EnCorreccion',
                'Revisión',
                'Aprobado',
            ])
            ->count();

        $firmadosUltimoAnio = $base()
            ->whereNotNull('i.fecha_firma')
            ->whereDate('i.fecha_firma', '>=', $hoy->copy()->subMonthsNoOverflow(12)->toDateString())
            ->whereDate('i.fecha_firma', '<=', $hoy->toDateString())
            ->count();

        $personasUnicas = $base()
            ->join('firma as f', 'f.id_instrumento', '=', 'c.id_instrumento')
            ->distinct()
            ->count('f.id_persona');

        $pasantesUnicos = $base()
            ->join('especifico as es', 'es.id_instrumento', '=', 'c.id_instrumento')
            ->join('pasantia as p', 'p.id_convenio_especifico', '=', 'es.id_instrumento')
            ->join('estudiante as e', 'e.id_persona', '=', 'p.id_estudiante')
            ->distinct()
            ->count('p.id_estudiante');

        return [
            'totales' => [
                'convenios' => $total,
                'marco' => $marco,
                'especificos' => $total - $marco,
            ],
            'por_estado' => $porEstado,
            'por_tipo' => $porTipo,
            'por_anio' => $porAnio,
            'por_unidad_academica' => $porUnidad,
            'vencimientos' => [
                'dias' => $dias,
                'vencidos' => $vencidos,
                'proximos' => $proximos->count(),
                'en_preaviso' => $enPreaviso,
            ],
            'indicadores' => [
                'convenios_vigentes' => $conveniosVigentes,
                'convenios_en_tramite' => $conveniosEnTramite,
                'firmados_ultimo_anio' => $firmadosUltimoAnio,
                'personas_unicas' => $personasUnicas,
                'pasantes_unicos' => $pasantesUnicos,
            ],
        ];
    }
}
