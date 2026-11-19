<?php

namespace App\Services;

use App\Models\HistorialEstado;
use App\Models\Instrumento;
use App\Models\Notificacion;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PanelService
{
    public const MOVIMIENTOS_MAX = 10;

    public const PENDIENTES_MAX = 10;

    public const NOTIFICACIONES_MAX = 10;

    public const ALERTAS_MAX = 5;

    /** Horas sin firmar a partir de las cuales un convenio aprobado se considera demorado. */
    public const HORAS_FIRMA_DEMORADA = 72;

    private const ESTADOS_EN_TRAMITE = ['Borrador', 'EnCorreccion', 'Revisión', 'Aprobado'];

    /**
     * Datos de la pantalla principal de la app, limitados a lo que el usuario puede ver.
     */
    public function resumen(Usuario $usuario): array
    {
        $hoy = Carbon::today();
        $visibles = fn (): Builder => $this->instrumentosVisibles($usuario);

        $porEstado = $visibles()
            ->reorder()
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->map(fn ($total) => (int) $total)
            ->all();

        $firmadosVigentes = $visibles()
            ->where('estado', 'Firmado')
            ->with('convenio:id_instrumento,id_unidad_academica')
            ->get();

        $vigentes = $firmadosVigentes->filter(
            fn (Instrumento $i) => $i->vencimiento === null || ! $i->vencimiento->lt($hoy)
        );

        $enPreaviso = $vigentes
            ->filter(fn (Instrumento $i) => $this->estaEnPreaviso($i, $hoy))
            ->sortBy('vencimiento')
            ->values();

        $pendientes = $this->pendientesDeFirma($usuario, $visibles, $hoy);

        return [
            'resumen' => [
                'firmados_ultimo_anio' => $visibles()
                    ->whereNotNull('fecha_firma')
                    ->whereDate('fecha_firma', '>=', $hoy->copy()->subYear()->toDateString())
                    ->count(),
                'vigentes' => $vigentes->count(),
                'en_tramite' => (int) collect($porEstado)
                    ->only(self::ESTADOS_EN_TRAMITE)
                    ->sum(),
                'en_preaviso' => $enPreaviso->count(),
                'vencidos' => $firmadosVigentes->count() - $vigentes->count(),
                'por_estado' => $porEstado,
            ],
            'por_unidad' => $this->porUnidad($visibles),
            'ultimos_movimientos' => $this->ultimosMovimientos($visibles),
            'pendientes_firma' => $pendientes,
            'notificaciones' => $this->notificaciones($usuario),
            'alertas' => $this->alertas($enPreaviso, $pendientes, $hoy),
            'generado_el' => now()->toIso8601String(),
        ];
    }

    /**
     * Mismas reglas de visibilidad que el listado de convenios (ConvenioService::paginate).
     * Si se cambian allá, hay que mantenerlas en sincronía acá.
     */
    private function instrumentosVisibles(Usuario $usuario): Builder
    {
        $query = Instrumento::query()->whereHas('convenio');

        $limitarAUnidad = fn () => $query->whereHas(
            'convenio',
            fn ($convenio) => $convenio->where('id_unidad_academica', $usuario->id_unidad)
        );

        switch ($usuario->rol?->nombre) {
            case 'Administración':
                break;

            case 'Gestión':
                $limitarAUnidad();
                $query->whereIn('estado', [
                    'Borrador', 'EnCorreccion', 'Revisión', 'Aprobado', 'Firmado', 'Rescindido',
                ]);
                break;

            case 'Revisión':
                if (! $usuario->alcance_global) {
                    $limitarAUnidad();
                }
                $query->whereIn('estado', [
                    'Revisión', 'EnCorreccion', 'Aprobado', 'Rechazado', 'Rescindido', 'Archivado',
                ]);
                break;

            case 'Consulta':
                if (! $usuario->alcance_global) {
                    $limitarAUnidad();
                }
                $query->whereIn('estado', ['Firmado', 'Rescindido', 'Archivado']);
                break;

            default:
                $query->whereRaw('1 = 0');
                break;
        }

        return $query;
    }

    private function estaEnPreaviso(Instrumento $instrumento, Carbon $hoy): bool
    {
        if ($instrumento->vencimiento === null || $instrumento->dias_preaviso === null) {
            return false;
        }

        return $hoy->diffInDays($instrumento->vencimiento->copy()->startOfDay(), false)
            <= (int) $instrumento->dias_preaviso;
    }

    private function porUnidad(callable $visibles): array
    {
        return DB::table('convenio as c')
            ->join('unidad_academica as u', 'u.id_unidad_academica', '=', 'c.id_unidad_academica')
            ->whereIn('c.id_instrumento', $visibles()->select('instrumento.id_instrumento'))
            ->select('u.id_unidad_academica', 'u.sigla', 'u.nombre', DB::raw('COUNT(*) as total'))
            ->groupBy('u.id_unidad_academica', 'u.sigla', 'u.nombre')
            ->orderByDesc('total')
            ->orderBy('u.sigla')
            ->get()
            ->map(fn ($fila) => [
                'id_unidad_academica' => (int) $fila->id_unidad_academica,
                'sigla' => $fila->sigla,
                'nombre' => $fila->nombre,
                'total' => (int) $fila->total,
            ])
            ->all();
    }

    /**
     * Convenios con actividad reciente: un renglón por convenio, con su último movimiento.
     */
    private function ultimosMovimientos(callable $visibles): array
    {
        return HistorialEstado::query()
            ->whereIn('id_instrumento', $visibles()->select('instrumento.id_instrumento'))
            ->with(['usuario:id_usuario,nombre', 'convenio.instrumento:id_instrumento,titulo'])
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id_historial')
            ->limit(self::MOVIMIENTOS_MAX * 10)
            ->get()
            ->unique('id_instrumento')
            ->take(self::MOVIMIENTOS_MAX)
            ->map(fn (HistorialEstado $movimiento) => [
                'id_instrumento' => $movimiento->id_instrumento,
                'titulo' => $movimiento->convenio?->instrumento?->titulo,
                'estado_anterior' => $movimiento->estado_anterior,
                'estado_nuevo' => $movimiento->estado_nuevo,
                'fecha_hora' => $movimiento->fecha_hora,
                'motivo' => $movimiento->motivo,
                'usuario' => $movimiento->usuario?->nombre,
            ])
            ->values()
            ->all();
    }

    /**
     * Convenios aprobados a la espera de firma, del más antiguo al más reciente.
     * Solo para quienes pueden registrar la firma (Gestión de la unidad y Administración).
     */
    private function pendientesDeFirma(Usuario $usuario, callable $visibles, Carbon $hoy): array
    {
        if (! in_array($usuario->rol?->nombre, ['Gestión', 'Administración'], true)) {
            return [];
        }

        // Si el usuario representa a una persona firmante, "mis pendientes" son los convenios
        // que él mismo tiene que firmar (los del firmador), no todos los de su unidad.
        if ($usuario->id_persona !== null) {
            return $this->pendientesAsignados($usuario);
        }

        $aprobados = $visibles()
            ->where('estado', 'Aprobado')
            ->with(['convenio.unidadAcademica:id_unidad_academica,sigla,nombre', 'convenio.institucionesFirmantes'])
            ->get();

        $aprobadoEl = HistorialEstado::query()
            ->where('estado_nuevo', 'Aprobado')
            ->whereIn('id_instrumento', $aprobados->pluck('id_instrumento'))
            ->selectRaw('id_instrumento, MAX(fecha_hora) as aprobado_el')
            ->groupBy('id_instrumento')
            ->pluck('aprobado_el', 'id_instrumento');

        return $aprobados
            ->map(function (Instrumento $instrumento) use ($aprobadoEl) {
                $fecha = $aprobadoEl->has($instrumento->id_instrumento)
                    ? Carbon::parse($aprobadoEl[$instrumento->id_instrumento])
                    : null;

                return ['instrumento' => $instrumento, 'aprobado_el' => $fecha];
            })
            ->sortBy([
                fn ($a, $b) => ($a['aprobado_el']?->timestamp ?? PHP_INT_MAX)
                    <=> ($b['aprobado_el']?->timestamp ?? PHP_INT_MAX),
                fn ($a, $b) => $a['instrumento']->id_instrumento <=> $b['instrumento']->id_instrumento,
            ])
            ->take(self::PENDIENTES_MAX)
            ->map(function (array $fila) {
                /** @var Instrumento $instrumento */
                $instrumento = $fila['instrumento'];
                $horas = $fila['aprobado_el'] ? (int) $fila['aprobado_el']->diffInHours(now()) : null;

                return [
                    'id_instrumento' => $instrumento->id_instrumento,
                    'titulo' => $instrumento->titulo,
                    'unidad_academica' => $instrumento->convenio?->unidadAcademica?->sigla,
                    'firmantes' => $instrumento->convenio?->institucionesFirmantes
                        ->pluck('razon_social')->values()->all() ?? [],
                    'aprobado_el' => $fila['aprobado_el']?->toIso8601String(),
                    'horas_pendiente' => $horas,
                    'demorado' => $horas !== null && $horas >= self::HORAS_FIRMA_DEMORADA,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Convenios aprobados que la persona vinculada al usuario todavía tiene que firmar.
     */
    private function pendientesAsignados(Usuario $usuario): array
    {
        return array_map(fn (array $pendiente) => [
            'id_instrumento' => $pendiente['id_instrumento'],
            'titulo' => $pendiente['titulo'],
            'unidad_academica' => $pendiente['unidad_academica'],
            'firmantes' => array_map(
                fn (array $firmante) => trim($firmante['nombre'].' '.$firmante['apellido']),
                $pendiente['firmantes']
            ),
            'aprobado_el' => $pendiente['aprobado_el'],
            'horas_pendiente' => $pendiente['horas_pendiente'],
            'demorado' => $pendiente['demorado'],
        ], app(FirmaService::class)->pendientes($usuario));
    }

    private function notificaciones(Usuario $usuario): array
    {
        $propias = fn (): Builder => Notificacion::query()->where('id_usuario', $usuario->id_usuario);

        return [
            'no_leidas' => $propias()->where('leida', false)->count(),
            'items' => $propias()
                ->orderByDesc('fecha_generacion')
                ->orderByDesc('id_notificacion')
                ->limit(self::NOTIFICACIONES_MAX)
                ->get()
                ->map(fn (Notificacion $notificacion) => [
                    'id_notificacion' => $notificacion->id_notificacion,
                    'tipo' => $notificacion->tipo,
                    'mensaje' => $notificacion->mensaje,
                    'fecha_generacion' => $notificacion->fecha_generacion,
                    'leida' => (bool) $notificacion->leida,
                    'id_instrumento' => $notificacion->id_instrumento,
                ])
                ->all(),
        ];
    }

    /**
     * Avisos derivados de los datos: convenios en preaviso y firmas demoradas.
     */
    private function alertas(Collection $enPreaviso, array $pendientes, Carbon $hoy): array
    {
        $preaviso = $enPreaviso->take(self::ALERTAS_MAX)->map(function (Instrumento $instrumento) use ($hoy) {
            $dias = (int) $hoy->diffInDays($instrumento->vencimiento->copy()->startOfDay(), false);

            return [
                'tipo' => 'PREAVISO',
                'id_instrumento' => $instrumento->id_instrumento,
                'titulo' => $instrumento->titulo,
                'mensaje' => $dias <= 0
                    ? 'Vence hoy.'
                    : 'Vence en '.$dias.' '.($dias === 1 ? 'día' : 'días').'.',
                'dias_restantes' => $dias,
            ];
        });

        $demoradas = collect($pendientes)
            ->where('demorado', true)
            ->take(self::ALERTAS_MAX)
            ->map(fn (array $pendiente) => [
                'tipo' => 'FIRMA_DEMORADA',
                'id_instrumento' => $pendiente['id_instrumento'],
                'titulo' => $pendiente['titulo'],
                'mensaje' => 'Lleva '.$pendiente['horas_pendiente'].' horas aprobado sin firmarse.',
                'horas_pendiente' => $pendiente['horas_pendiente'],
            ]);

        return $demoradas->concat($preaviso)->values()->all();
    }
}
