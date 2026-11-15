<?php

namespace App\Services;

use App\Models\Convenio;
use App\Models\HistorialEstado;
use App\Models\Instrumento;
use App\Models\Notificacion;
use App\Models\Usuario;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class RechazarConvenioService
{
    public function __construct(
        private ConvenioService $convenios,
        private AuditoriaService $auditoria
    ) {
    }

    /**
     * Rechaza un convenio que está en Revisión. El rechazo es definitivo: desde Rechazado
     * el convenio solo puede archivarse. Notifica a los usuarios de Gestión activos de la
     * unidad académica del convenio.
     */
    public function rechazar(string $id, string $motivo, Usuario $usuario): Convenio
    {
        return DB::transaction(function () use ($id, $motivo, $usuario) {
            $instrumento = Instrumento::query()
                ->lockForUpdate()
                ->findOrFail($id);
            $convenio = $instrumento->convenio()->firstOrFail();
            $rol = $usuario->rol?->nombre;

            if (! in_array($rol, ['Revisión', 'Administración'], true)) {
                throw new AuthorizationException(
                    'No tiene permisos para rechazar este convenio.'
                );
            }

            if ($rol === 'Revisión') {
                $this->convenios->assertCanViewWithinRevisionUnitScope($convenio, $usuario);
            }

            if ($instrumento->estado !== 'Revisión') {
                throw new DomainException(
                    'El convenio no se encuentra en estado Revisión.'
                );
            }

            $destinatarios = Usuario::query()
                ->where('activo', true)
                ->where('id_unidad', $convenio->id_unidad_academica)
                ->whereHas('rol', fn ($query) => $query->where('nombre', 'Gestión'))
                ->get(['id_usuario']);

            if ($destinatarios->isEmpty()) {
                throw new DomainException(
                    'No existen usuarios con rol Gestión activos en la unidad académica para recibir la notificación de rechazo'
                );
            }

            $fechaHora = now();

            $instrumento->estado = 'Rechazado';
            $instrumento->save();

            HistorialEstado::query()->create([
                'estado_anterior' => 'Revisión',
                'estado_nuevo' => 'Rechazado',
                'fecha_hora' => $fechaHora,
                'motivo' => $motivo,
                'id_instrumento' => $convenio->id_instrumento,
                'id_usuario' => $usuario->id_usuario,
            ]);

            $this->auditoria->registrar(
                'RECHAZO_DE_CONVENIO',
                $usuario->id_usuario,
                $convenio->id_instrumento,
                'Convenio #'.$convenio->id_instrumento.' rechazado. Motivo: '.$motivo
            );

            foreach ($destinatarios as $destinatario) {
                Notificacion::query()->create([
                    'tipo' => 'CONVENIO_RECHAZADO',
                    'mensaje' => 'El convenio #'.$convenio->id_instrumento
                        .' fue rechazado. Motivo: '.$motivo,
                    'fecha_generacion' => $fechaHora,
                    'id_instrumento' => $convenio->id_instrumento,
                    'id_usuario' => $destinatario->id_usuario,
                ]);
            }

            return $convenio->load([
                'instrumento',
                'marco',
                'especifico',
                'institucionesFirmantes',
            ]);
        });
    }
}
