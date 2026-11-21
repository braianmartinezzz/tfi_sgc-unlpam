<?php

namespace App\Services;

use App\Models\Convenio;
use App\Models\HistorialEstado;
use App\Models\Instrumento;
use App\Models\Usuario;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ArchivarConvenioService
{
    /**
     * Estados finales desde los que siempre se puede archivar.
     * Además se puede archivar un convenio Firmado cuya vigencia ya venció.
     */
    public const ESTADOS_ARCHIVABLES = ['Rechazado', 'Rescindido'];

    public function __construct(
        private ConvenioService $convenios,
        private AuditoriaService $auditoria
    ) {
    }

    public function archivar(string $id, ?string $motivo, Usuario $usuario): Convenio
    {
        return DB::transaction(function () use ($id, $motivo, $usuario) {
            $instrumento = Instrumento::query()
                ->lockForUpdate()
                ->findOrFail($id);
            $convenio = $instrumento->convenio()->firstOrFail();
            $rol = $usuario->rol?->nombre;

            if (! in_array($rol, ['Revisión', 'Administración'], true)) {
                throw new AuthorizationException(
                    'No tiene permisos para archivar este convenio.'
                );
            }

            if ($rol === 'Revisión') {
                // Solo se valida el alcance por unidad: un Firmado vencido no figura en el
                // listado de Revisión, pero este rol sí puede archivarlo.
                $this->convenios->assertCanViewWithinRevisionUnitScope($convenio, $usuario);
            }

            if ($instrumento->estado === 'Archivado') {
                throw new DomainException('El convenio ya se encuentra archivado.');
            }

            if (! $this->puedeArchivarse($instrumento)) {
                throw new DomainException(
                    'Solo se pueden archivar convenios Rechazados, Rescindidos o Firmados con vigencia vencida.'
                );
            }

            $estadoAnterior = $instrumento->estado;
            $motivo = filled($motivo) ? $motivo : null;

            $instrumento->estado = 'Archivado';
            $instrumento->save();

            HistorialEstado::query()->create([
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => 'Archivado',
                'fecha_hora' => now(),
                'motivo' => $motivo,
                'id_instrumento' => $convenio->id_instrumento,
                'id_usuario' => $usuario->id_usuario,
            ]);

            $this->auditoria->registrar(
                'ARCHIVAR_CONVENIO',
                $usuario->id_usuario,
                $convenio->id_instrumento,
                'Convenio #'.$convenio->id_instrumento.' archivado desde el estado '.$estadoAnterior.'.'
                    .($motivo !== null ? ' Motivo: '.$motivo : '')
            );

            return $convenio->load([
                'instrumento',
                'marco',
                'especifico',
                'institucionesFirmantes',
            ]);
        });
    }

    private function puedeArchivarse(Instrumento $instrumento): bool
    {
        if (in_array($instrumento->estado, self::ESTADOS_ARCHIVABLES, true)) {
            return true;
        }

        return $instrumento->estado === 'Firmado'
            && $instrumento->vencimiento !== null
            && $instrumento->vencimiento->lt(today());
    }
}
