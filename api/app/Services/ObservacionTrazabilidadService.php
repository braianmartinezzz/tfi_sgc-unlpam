<?php

namespace App\Services;

use App\Models\Observacion;
use App\Models\RegistroAuditoria;
use App\Models\Usuario;

class ObservacionTrazabilidadService
{
    /**
     * Acciones de auditoría que dejan rastro sobre las observaciones de un convenio.
     */
    public const ACCIONES = [
        'CREAR_OBSERVACION',
        'MODIFICAR_OBSERVACION',
        'ELIMINAR_OBSERVACION',
        'RESPUESTA_OBSERVACION',
    ];

    public function __construct(private ConvenioService $convenios)
    {
    }

    /**
     * Devuelve el historial de observaciones de un convenio junto con los movimientos
     * (alta, modificación, baja y respuesta) registrados en la auditoría.
     *
     * Reutiliza los permisos del listado de observaciones: puede lanzar
     * ModelNotFoundException (convenio inexistente) o AuthorizationException.
     */
    public function consultar(string $id, Usuario $usuario): array
    {
        $observaciones = $this->convenios->listarObservaciones($id, $usuario);

        $movimientos = RegistroAuditoria::query()
            ->with('usuario:id_usuario,nombre')
            ->where('id_instrumento', $id)
            ->whereIn('accion', self::ACCIONES)
            ->orderBy('fecha_hora')
            ->orderBy('id_registro')
            ->get();

        return [
            'id_instrumento' => (int) $id,
            'resumen' => [
                'total' => $observaciones->count(),
                'pendientes' => $observaciones->where('estado_tramite', 'Pendiente')->count(),
                'resueltas' => $observaciones->where('estado_tramite', 'Resuelta')->count(),
            ],
            'observaciones' => $observaciones
                ->map(fn (Observacion $observacion): array => [
                    'id_observacion' => $observacion->id_observacion,
                    'contenido' => $observacion->contenido,
                    'fecha' => $observacion->fecha,
                    'estado_tramite' => $observacion->estado_tramite,
                    'resolucion' => $observacion->resolucion,
                    'respuesta' => $observacion->respuesta,
                    'fecha_resolucion' => $observacion->fecha_resolucion,
                    'usuario' => [
                        'id_usuario' => $observacion->usuario?->id_usuario,
                        'nombre' => $observacion->usuario?->nombre,
                    ],
                ])
                ->values()
                ->all(),
            'movimientos' => $movimientos
                ->map(fn (RegistroAuditoria $registro): array => [
                    'id_registro' => $registro->id_registro,
                    'accion' => $registro->accion,
                    'fecha_hora' => $registro->fecha_hora,
                    'detalle' => $registro->detalle,
                    'usuario' => [
                        'id_usuario' => $registro->usuario?->id_usuario,
                        'nombre' => $registro->usuario?->nombre,
                    ],
                ])
                ->values()
                ->all(),
        ];
    }
}
