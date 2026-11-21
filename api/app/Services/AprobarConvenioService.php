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

class AprobarConvenioService
{
    public function __construct(
        private ConvenioService $convenios,
        private AuditoriaService $auditoria
    ) {
    }

    /**
     * Aprueba un convenio que está en Revisión, sin observaciones pendientes y con al menos
     * un documento con una versión PDF (la misma regla que se exige al enviarlo a revisión).
     *
     * La firma la realizan los firmantes fuera del sistema: al aprobar se notifica a los
     * usuarios de Gestión activos de la unidad para que envíen el instrumento a los firmantes
     * y, una vez firmado, carguen el documento firmado y registren la firma.
     */
    public function aprobar(string $id, Usuario $usuario): Convenio
    {
        return DB::transaction(function () use ($id, $usuario) {
            $instrumento = Instrumento::query()
                ->lockForUpdate()
                ->findOrFail($id);
            $convenio = $instrumento->convenio()->firstOrFail();
            $rol = $usuario->rol?->nombre;

            if (! in_array($rol, ['Revisión', 'Administración'], true)) {
                throw new AuthorizationException(
                    'No tiene permisos para aprobar este convenio.'
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

            if ($convenio->observaciones()
                ->where('estado_tramite', 'Pendiente')
                ->exists()) {
                throw new DomainException(
                    'El convenio tiene observaciones pendientes.'
                );
            }

            $tieneVersionPdf = $instrumento->documentos()
                ->whereHas('versiones', function ($versionQuery) {
                    $versionQuery->whereRaw(
                        'LOWER(ruta_interna) LIKE ?',
                        ['%.pdf']
                    );
                })
                ->exists();

            if (! $tieneVersionPdf) {
                throw new DomainException(
                    'El convenio debe tener al menos un documento con una versión PDF.'
                );
            }

            $destinatarios = Usuario::query()
                ->where('activo', true)
                ->where('id_unidad', $convenio->id_unidad_academica)
                ->whereHas('rol', fn ($query) => $query->where('nombre', 'Gestión'))
                ->get(['id_usuario']);

            if ($destinatarios->isEmpty()) {
                throw new DomainException(
                    'No existen usuarios con rol Gestión activos en la unidad académica para recibir la notificación de aprobación'
                );
            }

            $fechaHora = now();

            $instrumento->estado = 'Aprobado';
            $instrumento->save();

            HistorialEstado::query()->create([
                'estado_anterior' => 'Revisión',
                'estado_nuevo' => 'Aprobado',
                'fecha_hora' => $fechaHora,
                'motivo' => null,
                'id_instrumento' => $convenio->id_instrumento,
                'id_usuario' => $usuario->id_usuario,
            ]);

            $this->auditoria->registrar(
                'APROBACION_DE_CONVENIO',
                $usuario->id_usuario,
                $convenio->id_instrumento,
                'Convenio #'.$convenio->id_instrumento.' aprobado.'
            );

            $mensaje = $this->mensajeDeAprobacion($convenio);

            foreach ($destinatarios as $destinatario) {
                Notificacion::query()->create([
                    'tipo' => 'CONVENIO_APROBADO',
                    'mensaje' => $mensaje,
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

    private function mensajeDeAprobacion(Convenio $convenio): string
    {
        $mensaje = 'El convenio #'.$convenio->id_instrumento
            .' fue aprobado y está listo para ser enviado a los firmantes.';

        $firmantes = $convenio->institucionesFirmantes()
            ->orderBy('razon_social')
            ->pluck('razon_social');

        if ($firmantes->isNotEmpty()) {
            $mensaje .= ' Firmantes: '.$firmantes->implode(', ').'.';
        }

        return $mensaje.' Una vez firmado, cargue el documento firmado y registre la firma.';
    }
}
