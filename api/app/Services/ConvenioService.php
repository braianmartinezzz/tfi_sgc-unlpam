<?php

namespace App\Services;

use App\Models\Adenda;
use App\Models\Convenio;
use App\Models\Documento;
use App\Models\Especifico;
use App\Models\HistorialEstado;
use App\Models\Instrumento;
use App\Models\Marco;
use App\Models\Notificacion;
use App\Models\Observacion;
use App\Models\RegistroAuditoria;
use App\Models\Renovacion;
use App\Models\Sustitucion;
use App\Models\Usuario;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ConvenioService
{
    public function paginate(array $filters, Usuario $usuario): LengthAwarePaginator
    {
        $query = Convenio::query()->with([
            'instrumento',
            'unidadAcademica',
            'institucionesFirmantes',
            'marco',
            'especifico',
        ]);

        switch ($usuario->rol?->nombre) {
            case 'Administración':
                break;

            case 'Gestión':
                $query->where(
                    'convenio.id_unidad_academica',
                    $usuario->id_unidad
                )->whereHas('instrumento', function ($instrumentoQuery) {
                    $instrumentoQuery->whereIn('estado', [
                        'Borrador',
                        'EnCorreccion',
                        'Revisión',
                        'Aprobado',
                        'Firmado',
                        'Rescindido',
                    ]);
                });
                break;

            case 'Revisión':
                $this->applyRevisionVisibility($query, $usuario);
                break;

            case 'Consulta':
                if (! $usuario->alcance_global) {
                    $query->where(
                        'convenio.id_unidad_academica',
                        $usuario->id_unidad
                    );
                }

                $query->whereHas('instrumento', function ($instrumentoQuery) {
                    $instrumentoQuery->whereIn('estado', [
                        'Firmado',
                        'Rescindido',
                        'Archivado',
                    ]);
                });
                break;

            default:
                $query->whereRaw('1 = 0');
                break;
        }

        if (isset($filters['estado']) && $filters['estado'] !== '') {
            $query->whereHas('instrumento', function ($instrumentoQuery) use ($filters) {
                $instrumentoQuery->where('estado', $filters['estado']);
            });
        }

        if (isset($filters['tipo']) && $filters['tipo'] !== '') {
            $query->where('tipo', $filters['tipo']);
        }

        if (isset($filters['es_marco']) && $filters['es_marco'] !== '') {
            $esMarco = filter_var(
                $filters['es_marco'],
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );

            if ($esMarco !== null) {
                $query->whereHas($esMarco ? 'marco' : 'especifico');
            }
        }

        if (isset($filters['id_unidad_academica']) && $filters['id_unidad_academica'] !== '') {
            $query->where('id_unidad_academica', $filters['id_unidad_academica']);
        }

        if (isset($filters['buscar']) && $filters['buscar'] !== '') {
            $query->whereHas('instrumento', function ($instrumentoQuery) use ($filters) {
                $instrumentoQuery->where('titulo', 'like', '%'.$filters['buscar'].'%');
            });
        }

        return $query->paginate(15)->appends($filters);
    }

    private function applyRevisionVisibility(Builder $query, Usuario $usuario): Builder
    {
        $this->applyRevisionUnitScope($query, $usuario);

        return $query->whereHas('instrumento', function ($instrumentoQuery) {
            $instrumentoQuery->whereIn('estado', [
                'Revisión',
                'EnCorreccion',
                'Aprobado',
                'Rechazado',
                'Rescindido',
                'Archivado',
            ]);
        });
    }

    private function applyRevisionUnitScope(Builder $query, Usuario $usuario): Builder
    {
        if (! $usuario->alcance_global) {
            $query->where(
                'convenio.id_unidad_academica',
                $usuario->id_unidad
            );
        }

        return $query;
    }

    public function findById(string $id, Usuario $usuario): ?Convenio
    {
        $convenio = Convenio::query()->find($id);

        if ($convenio === null) {
            return null;
        }

        $this->assertCanView($convenio, $usuario);

        return $convenio->load([
            'instrumento',
            'unidadAcademica',
            'institucionesFirmantes',
            'institucionesFirmantes.representantes' => fn ($query) => $query->select([
                'id_persona',
                'cargo',
                'id_institucion',
            ]),
            'institucionesFirmantes.representantes.persona:id_persona,nombre,apellido',
            'instrumento.documentos.ultimaVersion' => fn ($query) => $query->select([
                'version.id_version',
                'version.numero',
                'version.nombre_archivo',
                'version.tamanio_bytes',
                'version.hash_sha256',
                'version.fecha_carga',
                'version.id_documento',
            ]),
            'marco',
            'especifico.marco.instrumento',
            'historialEstados.usuario:id_usuario,nombre',
        ]);
    }

    public function assertCanView(Convenio $convenio, Usuario $usuario): void
    {
        $rol = $usuario->rol?->nombre;

        if ($rol === 'Revisión') {
            $query = Convenio::query()->whereKey($convenio->id_instrumento);

            if (! $this->applyRevisionVisibility($query, $usuario)->exists()) {
                throw new AuthorizationException(
                    'No tiene permisos para consultar este convenio.'
                );
            }

            return;
        }

        if (
            (
                $rol === 'Gestión'
                || ($rol === 'Consulta' && ! $usuario->alcance_global)
            )
            && (int) $usuario->id_unidad !== (int) $convenio->id_unidad_academica
        ) {
            throw new AuthorizationException(
                'No tiene permisos para consultar este convenio.'
            );
        }
    }

    public function assertCanViewWithinRevisionUnitScope(
        Convenio $convenio,
        Usuario $usuario
    ): void {
        if ($usuario->rol?->nombre !== 'Revisión') {
            throw new AuthorizationException(
                'No tiene permisos para consultar este convenio.'
            );
        }

        $query = Convenio::query()->whereKey($convenio->id_instrumento);

        if (! $this->applyRevisionUnitScope($query, $usuario)->exists()) {
            throw new AuthorizationException(
                'No tiene permisos para consultar este convenio.'
            );
        }
    }

    public function listarObservaciones(
        string $id,
        Usuario $usuario
    ): Collection {
        $instrumento = Instrumento::query()->findOrFail($id);
        $convenio = $instrumento->convenio()->firstOrFail();

        if ($usuario->rol?->nombre === 'Revisión') {
            $this->assertCanView($convenio, $usuario);
        } else {
            Gate::forUser($usuario)->authorize(
                'enviar-convenio-a-revision',
                $convenio
            );
        }

        return $convenio->observaciones()
            ->with('usuario:id_usuario,nombre')
            ->orderBy('fecha')
            ->orderBy('id_observacion')
            ->get();
    }

    public function crearObservacion(
        string $id,
        string $contenido,
        Usuario $usuario,
        AuditoriaService $auditoriaService
    ): Observacion {
        return DB::transaction(function () use (
            $id,
            $contenido,
            $usuario,
            $auditoriaService
        ) {
            $instrumento = Instrumento::query()
                ->lockForUpdate()
                ->findOrFail($id);
            $convenio = $instrumento->convenio()
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCanManageObservations($convenio, $instrumento, $usuario);

            $observacion = $convenio->observaciones()->create([
                'contenido' => $contenido,
                'fecha' => now(),
                'estado_tramite' => 'Pendiente',
                'resolucion' => null,
                'respuesta' => null,
                'fecha_resolucion' => null,
                'id_usuario' => $usuario->id_usuario,
            ]);

            $auditoriaService->registrar(
                'CREAR_OBSERVACION',
                $usuario->id_usuario,
                $convenio->id_instrumento,
                'Observación #'.$observacion->id_observacion.' creada.'
            );

            return $observacion->load('usuario:id_usuario,nombre');
        });
    }

    public function actualizarObservacion(
        string $idObservacion,
        string $contenido,
        Usuario $usuario,
        AuditoriaService $auditoriaService
    ): Observacion {
        return DB::transaction(function () use (
            $idObservacion,
            $contenido,
            $usuario,
            $auditoriaService
        ) {
            $idInstrumento = Observacion::query()
                ->findOrFail($idObservacion)
                ->id_instrumento;
            $instrumento = Instrumento::query()
                ->lockForUpdate()
                ->findOrFail($idInstrumento);
            $convenio = $instrumento->convenio()
                ->lockForUpdate()
                ->firstOrFail();
            $observacion = $convenio->observaciones()
                ->lockForUpdate()
                ->findOrFail($idObservacion);

            $this->assertCanManageObservations($convenio, $instrumento, $usuario);

            if ($observacion->estado_tramite !== 'Pendiente') {
                throw new \DomainException(
                    'Solo se pueden modificar observaciones pendientes.'
                );
            }

            $observacion->contenido = $contenido;
            $observacion->save();

            $auditoriaService->registrar(
                'MODIFICAR_OBSERVACION',
                $usuario->id_usuario,
                $convenio->id_instrumento,
                'Observación #'.$observacion->id_observacion.' modificada.'
            );

            return $observacion->load('usuario:id_usuario,nombre');
        });
    }

    public function eliminarObservacion(
        string $idObservacion,
        Usuario $usuario,
        AuditoriaService $auditoriaService
    ): void {
        DB::transaction(function () use (
            $idObservacion,
            $usuario,
            $auditoriaService
        ) {
            $idInstrumento = Observacion::query()
                ->findOrFail($idObservacion)
                ->id_instrumento;
            $instrumento = Instrumento::query()
                ->lockForUpdate()
                ->findOrFail($idInstrumento);
            $convenio = $instrumento->convenio()
                ->lockForUpdate()
                ->firstOrFail();
            $observacion = $convenio->observaciones()
                ->lockForUpdate()
                ->findOrFail($idObservacion);

            $this->assertCanManageObservations($convenio, $instrumento, $usuario);

            if ($observacion->estado_tramite !== 'Pendiente') {
                throw new \DomainException(
                    'Solo se pueden eliminar observaciones pendientes.'
                );
            }

            $observacionId = $observacion->id_observacion;
            $observacion->delete();

            $auditoriaService->registrar(
                'ELIMINAR_OBSERVACION',
                $usuario->id_usuario,
                $convenio->id_instrumento,
                'Observación #'.$observacionId.' eliminada.'
            );
        });
    }

    public function responderObservacion(
        string $id,
        string $idObservacion,
        string $respuesta,
        Usuario $usuario
    ): Observacion {
        return DB::transaction(function () use (
            $id,
            $idObservacion,
            $respuesta,
            $usuario
        ) {
            $instrumento = Instrumento::query()
                ->lockForUpdate()
                ->findOrFail($id);
            $convenio = $instrumento->convenio()
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($usuario)->authorize(
                'enviar-convenio-a-revision',
                $convenio
            );

            if ($instrumento->estado !== 'EnCorreccion') {
                throw new \DomainException(
                    'Solo se pueden responder observaciones cuando el convenio está EnCorreccion.'
                );
            }

            $observacion = $convenio->observaciones()
                ->lockForUpdate()
                ->findOrFail($idObservacion);

            if ($observacion->estado_tramite !== 'Pendiente') {
                throw new \DomainException(
                    'Solo se pueden responder observaciones pendientes.'
                );
            }

            $fechaHora = now();
            $observacion->respuesta = $respuesta;
            $observacion->fecha_resolucion = $fechaHora;
            $observacion->estado_tramite = 'Resuelta';
            $observacion->save();

            RegistroAuditoria::query()->create([
                'accion' => 'RESPUESTA_OBSERVACION',
                'fecha_hora' => $fechaHora,
                'detalle' => 'Respuesta registrada para la observación #'
                    .$observacion->id_observacion,
                'id_instrumento' => $convenio->id_instrumento,
                'id_usuario' => $usuario->id_usuario,
            ]);

            return $observacion;
        });
    }

    private function assertCanManageObservations(
        Convenio $convenio,
        Instrumento $instrumento,
        Usuario $usuario
    ): void {
        $rol = $usuario->rol?->nombre;

        if (! in_array($rol, ['Revisión', 'Administración'], true)) {
            throw new AuthorizationException(
                'No tiene permisos para gestionar observaciones.'
            );
        }

        if ($rol === 'Revisión') {
            $this->assertCanView($convenio, $usuario);

            if ($instrumento->estado !== 'Revisión') {
                throw new \DomainException(
                    'Solo se pueden gestionar observaciones cuando el convenio está en Revisión.'
                );
            }
        }
    }

    public function registrarFirma(
        string $id,
        string $fechaFirma,
        ?UploadedFile $archivoPdf,
        Usuario $usuario,
        DocumentoService $documentoService
    ): array {
        $rutaInterna = null;

        try {
            return DB::transaction(function () use (
                $id,
                $fechaFirma,
                $archivoPdf,
                $usuario,
                $documentoService,
                &$rutaInterna
            ) {
                $convenio = Convenio::query()
                    ->lockForUpdate()
                    ->findOrFail($id);

                Gate::forUser($usuario)->authorize(
                    'enviar-convenio-a-revision',
                    $convenio
                );

                $instrumento = $convenio->instrumento()
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($instrumento->estado !== 'Aprobado') {
                    throw new \DomainException(
                        'El convenio debe estar en estado Aprobado para poder registrar la firma.'
                    );
                }

                $documentosFirmados = Documento::query()
                    ->where('id_instrumento', $instrumento->id_instrumento)
                    ->where('es_documento_firmado', true)
                    ->lockForUpdate()
                    ->get();

                if ($documentosFirmados->count() > 1) {
                    throw new \DomainException(
                        'El convenio tiene más de un documento final firmado identificado.'
                    );
                }

                $documentoFirmado = $documentosFirmados->first();

                if ($archivoPdf !== null) {
                    $rutaInterna = $documentoService->registrarVersionFirmada(
                        $instrumento,
                        $documentoFirmado,
                        $archivoPdf,
                        $usuario
                    );
                } elseif (
                    $documentoFirmado === null
                    || ! $this->tieneVersionPdfDisponible($documentoFirmado)
                ) {
                    throw new \DomainException(
                        'Debe adjuntar el PDF firmado o existir previamente un documento final firmado con una versión PDF disponible.'
                    );
                }

                $fechaHora = now();
                $instrumento->fecha_firma = $fechaFirma;
                $instrumento->estado = 'Firmado';
                $instrumento->save();

                HistorialEstado::query()->create([
                    'estado_anterior' => 'Aprobado',
                    'estado_nuevo' => 'Firmado',
                    'fecha_hora' => $fechaHora,
                    'motivo' => 'Firma de convenio registrada y documento final adjuntado.',
                    'id_instrumento' => $convenio->id_instrumento,
                    'id_usuario' => $usuario->id_usuario,
                ]);

                RegistroAuditoria::query()->create([
                    'accion' => 'REGISTRO_FIRMA',
                    'fecha_hora' => $fechaHora,
                    'detalle' => 'Firma registrada para el convenio #'
                        .$convenio->id_instrumento,
                    'id_instrumento' => $convenio->id_instrumento,
                    'id_usuario' => $usuario->id_usuario,
                ]);

                return [
                    'id_instrumento' => $convenio->id_instrumento,
                    'estado' => 'Firmado',
                    'fecha_firma' => $instrumento->fecha_firma,
                ];
            });
        } catch (\Throwable $exception) {
            $documentoService->eliminarArchivoNuevo(
                $rutaInterna,
                (int) $id
            );

            throw $exception;
        }
    }

    public function rescindir(
        string $id,
        string $motivo,
        ?UploadedFile $documentoResolucion,
        Usuario $usuario,
        DocumentoService $documentoService
    ): array {
        $rutaInterna = null;

        try {
            return DB::transaction(function () use (
                $id,
                $motivo,
                $documentoResolucion,
                $usuario,
                $documentoService,
                &$rutaInterna
            ) {
                $convenio = Convenio::query()
                    ->lockForUpdate()
                    ->findOrFail($id);

                Gate::forUser($usuario)->authorize(
                    'enviar-convenio-a-revision',
                    $convenio
                );

                $instrumento = $convenio->instrumento()
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($instrumento->estado !== 'Firmado') {
                    throw new \DomainException(
                        'El convenio no se encuentra en estado vigente para ser rescindido.'
                    );
                }

                if (
                    $instrumento->vencimiento !== null
                    && $instrumento->vencimiento->lt(today())
                ) {
                    throw new \DomainException(
                        'No se puede rescindir un convenio vencido.'
                    );
                }

                $destinatarios = $convenio->unidadAcademica()
                    ->firstOrFail()
                    ->usuarios()
                    ->where('activo', true)
                    ->get(['id_usuario']);

                if ($destinatarios->isEmpty()) {
                    throw new \DomainException(
                        'No hay usuarios activos asociados a la unidad académica para notificar.'
                    );
                }

                if ($documentoResolucion !== null) {
                    $rutaInterna = $documentoService->registrarDocumentoResolucion(
                        $instrumento,
                        $documentoResolucion,
                        $usuario
                    );
                }

                $fechaHora = now();
                $instrumento->estado = 'Rescindido';
                $instrumento->save();

                HistorialEstado::query()->create([
                    'estado_anterior' => 'Firmado',
                    'estado_nuevo' => 'Rescindido',
                    'fecha_hora' => $fechaHora,
                    'motivo' => $motivo,
                    'id_instrumento' => $convenio->id_instrumento,
                    'id_usuario' => $usuario->id_usuario,
                ]);

                RegistroAuditoria::query()->create([
                    'accion' => 'RESCISION_ANTICIPADA',
                    'fecha_hora' => $fechaHora,
                    'detalle' => 'Convenio #'.$convenio->id_instrumento
                        .' rescindido. Motivo: '.$motivo
                        .'. Fecha y hora: '.$fechaHora->format('Y-m-d H:i:s').'.',
                    'id_instrumento' => $convenio->id_instrumento,
                    'id_usuario' => $usuario->id_usuario,
                ]);

                foreach ($destinatarios as $destinatario) {
                    Notificacion::query()->create([
                        'tipo' => 'CONVENIO_CANCELADO',
                        'mensaje' => 'El convenio #'.$convenio->id_instrumento
                            .' fue rescindido.',
                        'fecha_generacion' => $fechaHora,
                        'id_instrumento' => $convenio->id_instrumento,
                        'id_usuario' => $destinatario->id_usuario,
                    ]);
                }

                return [
                    'id_instrumento' => $convenio->id_instrumento,
                    'estado' => 'Rescindido',
                    'fecha_hora' => $fechaHora,
                ];
            });
        } catch (\Throwable $exception) {
            $documentoService->eliminarArchivoNuevo(
                $rutaInterna,
                (int) $id
            );

            throw $exception;
        }
    }

    public function update(string $id, array $data, Usuario $usuario): Convenio
    {
        return DB::transaction(function () use ($id, $data, $usuario) {
            $convenio = Convenio::query()
                ->lockForUpdate()
                ->findOrFail($id);

            Gate::forUser($usuario)->authorize(
                'gestionar-convenio',
                (int) $convenio->id_unidad_academica
            );

            if (array_key_exists('id_unidad_academica', $data)) {
                Gate::forUser($usuario)->authorize(
                    'gestionar-convenio',
                    (int) $data['id_unidad_academica']
                );
            }

            $instrumento = $convenio->instrumento()
                ->lockForUpdate()
                ->firstOrFail();

            if ($instrumento->estado !== 'Borrador') {
                throw new \DomainException('El convenio no está en fase de edición.');
            }

            $instrumentoFields = array_intersect_key($data, array_flip([
                'titulo',
                'objeto',
                'expediente',
                'resolucion',
                'fecha_firma',
                'inicio',
                'vencimiento',
                'dias_preaviso',
                'notas',
            ]));

            if ($instrumentoFields !== []) {
                $instrumento->fill($instrumentoFields)->save();
            }

            $convenioFields = array_intersect_key($data, array_flip([
                'tipo',
                'alcance',
                'obligaciones',
                'condiciones_recision',
                'marco_legal',
                'condicion_renovacion',
                'confidencialidad',
                'id_unidad_academica',
            ]));

            if ($convenioFields !== []) {
                $convenio->fill($convenioFields)->save();
            }

            if (array_key_exists('id_convenio_marco', $data)) {
                $especifico = $convenio->especifico()->first();

                if ($especifico === null) {
                    throw new \DomainException('No se puede modificar el subtipo del convenio.');
                }

                $especifico->fill([
                    'id_convenio_marco' => $data['id_convenio_marco'],
                ])->save();
            }

            if (array_key_exists('instituciones_ids', $data)) {
                $convenio->institucionesFirmantes()->sync($data['instituciones_ids']);
            }

            return $convenio->load([
                'instrumento',
                'unidadAcademica',
                'institucionesFirmantes',
                'marco',
                'especifico.marco.instrumento',
                'historialEstados',
            ]);
        });
    }

    public function destroy(string $id, Usuario $usuario): void
    {
        DB::transaction(function () use ($id, $usuario) {
            $convenio = Convenio::query()
                ->lockForUpdate()
                ->findOrFail($id);

            Gate::forUser($usuario)->authorize(
                'gestionar-convenio',
                (int) $convenio->id_unidad_academica
            );

            $instrumento = $convenio->instrumento()
                ->lockForUpdate()
                ->firstOrFail();

            if ($instrumento->estado !== 'Borrador') {
                throw new \DomainException(
                    'Solo se pueden eliminar convenios en estado Borrador.'
                );
            }

            $marco = $convenio->marco()
                ->lockForUpdate()
                ->first();

            if ($marco !== null && $marco->especificos()->exists()) {
                throw new \DomainException(
                    'No se puede eliminar un Convenio Marco que posee Convenios Específicos vinculados.'
                );
            }

            $referenciado = DB::table('adenda')
                ->where('id_instrumento_modificado', $id)
                ->exists()
                || DB::table('sustitucion')
                    ->where('id_instrumento_sustituido', $id)
                    ->exists()
                || DB::table('renovacion')
                    ->where('id_instrumento_renovado', $id)
                    ->exists();

            if ($referenciado) {
                throw new \DomainException(
                    'No se puede eliminar el convenio porque está referenciado por otros instrumentos.'
                );
            }

            DB::table('registro_auditoria')
                ->where('id_instrumento', $id)
                ->update(['id_instrumento' => null]);

            DB::table('notificacion')
                ->where('id_instrumento', $id)
                ->update(['id_instrumento' => null]);

            DB::table('historial_estado')
                ->where('id_instrumento', $id)
                ->delete();
            DB::table('observacion')
                ->where('id_instrumento', $id)
                ->delete();

            $convenio->institucionesFirmantes()->detach();
            $convenio->personasFirmantes()->detach();

            $especifico = $convenio->especifico()->first();

            if ($especifico !== null) {
                DB::table('pasantia')
                    ->where('id_convenio_especifico', $id)
                    ->delete();
            }

            $documentIds = DB::table('documento')
                ->where('id_instrumento', $id)
                ->pluck('id_documento');

            DB::table('version')
                ->whereIn('id_documento', $documentIds)
                ->delete();
            DB::table('documento')
                ->where('id_instrumento', $id)
                ->delete();

            DB::table('adenda')
                ->where('id_instrumento', $id)
                ->delete();
            DB::table('sustitucion')
                ->where('id_instrumento', $id)
                ->delete();
            DB::table('renovacion')
                ->where('id_instrumento', $id)
                ->delete();

            $convenio->marco()->delete();
            $convenio->especifico()->delete();
            $convenio->delete();
            $instrumento->delete();
        });
    }

    public function store(array $data, Usuario $usuario): Instrumento
    {
        return DB::transaction(function () use ($data, $usuario) {
            Gate::forUser($usuario)->authorize(
                'gestionar-convenio',
                (int) $data['id_unidad_academica']
            );

            $instrumento = Instrumento::create([
                'titulo' => $data['titulo'],
                'objeto' => $data['objeto'] ?? null,
                'estado' => 'Borrador',
                'version_registro' => 1,
                'dias_preaviso' => $data['dias_preaviso'] ?? 30,
            ]);

            $convenio = Convenio::create([
                'id_instrumento' => $instrumento->id_instrumento,
                'tipo' => $data['tipo'],
                'id_unidad_academica' => $data['id_unidad_academica'],
            ]);

            if ($data['es_marco']) {
                Marco::create([
                    'id_instrumento' => $instrumento->id_instrumento,
                ]);
            } else {
                Especifico::create([
                    'id_instrumento' => $instrumento->id_instrumento,
                    'id_convenio_marco' => $data['id_convenio_marco'],
                ]);
            }

            $convenio->institucionesFirmantes()->attach($data['instituciones_ids']);

            HistorialEstado::create([
                'estado_anterior' => null,
                'estado_nuevo' => 'Borrador',
                'motivo' => 'Creación del convenio',
                'id_instrumento' => $instrumento->id_instrumento,
                'id_usuario' => $usuario->id_usuario,
            ]);

            return $instrumento->load([
                'convenio',
                'convenio.marco',
                'convenio.especifico',
                'convenio.institucionesFirmantes',
            ]);
        });
    }

    /**
     * @return array{estado_anterior: string, estado_nuevo: string}
     */
    public function enviarARevision(
        string $id,
        ?string $motivo,
        Usuario $usuario
    ): array {
        return DB::transaction(function () use ($id, $motivo, $usuario) {
            $convenio = Convenio::query()
                ->lockForUpdate()
                ->findOrFail($id);

            Gate::forUser($usuario)->authorize(
                'enviar-convenio-a-revision',
                $convenio
            );

            $instrumento = $convenio->instrumento()
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($instrumento->estado, ['Borrador', 'EnCorreccion'], true)) {
                throw new \DomainException(
                    'El convenio no se encuentra en un estado que admita el envío a revisión.'
                );
            }

            if ($convenio->observaciones()->where('estado_tramite', 'Pendiente')->exists()) {
                throw new \DomainException(
                    'El convenio todavía tiene observaciones pendientes de respuesta.'
                );
            }

            if (
                trim($instrumento->titulo) === ''
                || trim($convenio->tipo) === ''
                || $convenio->id_unidad_academica === null
                || ! $convenio->unidadAcademica()->exists()
            ) {
                throw new \DomainException(
                    'La ficha técnica del convenio está incompleta.'
                );
            }

            if ($convenio->institucionesFirmantes()->count() < 2) {
                throw new \DomainException(
                    'El convenio debe tener al menos dos partes firmantes vinculadas antes de enviarse a revisión.'
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
                throw new \DomainException(
                    'El convenio debe tener al menos un documento con una versión PDF.'
                );
            }

            $this->validarAntecedentes($convenio, $instrumento);

            $revisores = Usuario::query()
                ->where('activo', true)
                ->whereHas('rol', fn ($query) => $query->where('nombre', 'Revisión'))
                ->get(['id_usuario']);

            if ($revisores->isEmpty()) {
                throw new \DomainException(
                    'No hay usuarios activos del rol Revisión para notificar.'
                );
            }

            $estadoAnterior = $instrumento->estado;
            $fechaHora = now();
            $motivoHistorial = is_string($motivo) && trim($motivo) !== ''
                ? trim($motivo)
                : 'Solicitud de revisión legal enviada por el equipo de gestión';

            $instrumento->estado = 'Revisión';
            $instrumento->save();

            HistorialEstado::query()->create([
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => 'Revisión',
                'fecha_hora' => $fechaHora,
                'motivo' => $motivoHistorial,
                'id_instrumento' => $convenio->id_instrumento,
                'id_usuario' => $usuario->id_usuario,
            ]);

            RegistroAuditoria::query()->create([
                'accion' => 'SOLICITUD_REVISION',
                'fecha_hora' => $fechaHora,
                'detalle' => 'Convenio enviado a revisión legal.',
                'id_instrumento' => $convenio->id_instrumento,
                'id_usuario' => $usuario->id_usuario,
            ]);

            foreach ($revisores as $revisor) {
                Notificacion::query()->create([
                    'tipo' => 'REVISION_ASIGNADA',
                    'mensaje' => 'El convenio fue enviado a revisión.',
                    'fecha_generacion' => $fechaHora,
                    'id_instrumento' => $convenio->id_instrumento,
                    'id_usuario' => $revisor->id_usuario,
                ]);
            }

            return [
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => 'Revisión',
            ];
        });
    }

    public function devolverAcorreccion(
        string $id,
        string $motivo,
        Usuario $usuario,
        AuditoriaService $auditoriaService
    ): Convenio {
        return DB::transaction(function () use (
            $id,
            $motivo,
            $usuario,
            $auditoriaService
        ) {
            $instrumento = Instrumento::query()
                ->lockForUpdate()
                ->findOrFail($id);
            $convenio = $instrumento->convenio()->firstOrFail();
            $rol = $usuario->rol?->nombre;

            if (! in_array($rol, ['Revisión', 'Administración'], true)) {
                throw new AuthorizationException(
                    'No tiene permisos para devolver este convenio a corrección.'
                );
            }

            if ($rol === 'Revisión') {
                if ($instrumento->estado === 'Revisión') {
                    $this->assertCanView($convenio, $usuario);
                } else {
                    $this->assertCanViewWithinRevisionUnitScope($convenio, $usuario);
                }
            }

            if ($instrumento->estado !== 'Revisión') {
                throw new \DomainException(
                    'El convenio no se encuentra en estado Revisión.'
                );
            }

            if (! $convenio->observaciones()
                ->where('estado_tramite', 'Pendiente')
                ->exists()) {
                throw new \DomainException(
                    'El convenio no tiene observaciones pendientes.'
                );
            }

            $destinatarios = Usuario::query()
                ->where('activo', true)
                ->where('id_unidad', $convenio->id_unidad_academica)
                ->whereHas('rol', fn ($query) => $query->where('nombre', 'Gestión'))
                ->get(['id_usuario']);

            if ($destinatarios->isEmpty()) {
                throw new \DomainException(
                    'No existen usuarios con rol Gestión activos en la unidad académica para recibir la notificación de devolución'
                );
            }

            $fechaHora = now();
            $instrumento->estado = 'EnCorreccion';
            $instrumento->save();

            HistorialEstado::query()->create([
                'estado_anterior' => 'Revisión',
                'estado_nuevo' => 'EnCorreccion',
                'fecha_hora' => $fechaHora,
                'motivo' => $motivo,
                'id_instrumento' => $convenio->id_instrumento,
                'id_usuario' => $usuario->id_usuario,
            ]);

            $auditoriaService->registrar(
                'DEVOLUCION_A_CORRECCION',
                $usuario->id_usuario,
                $convenio->id_instrumento,
                'Convenio #'.$convenio->id_instrumento
                    .' devuelto a corrección. Motivo: '.$motivo
            );

            foreach ($destinatarios as $destinatario) {
                Notificacion::query()->create([
                    'tipo' => 'DEVOLUCION_OBSERVACION',
                    'mensaje' => 'El convenio #'.$convenio->id_instrumento
                        .' fue devuelto a corrección.',
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

    private function validarAntecedentes(
        Convenio $convenio,
        Instrumento $instrumento
    ): void {
        $id = (int) $convenio->id_instrumento;

        $especifico = $convenio->especifico()
            ->with('marco.convenio.instrumento')
            ->first();

        if ($especifico !== null) {
            $marco = $especifico->marco;

            if (
                $marco === null
                || (int) $marco->id_instrumento === $id
                || $marco->convenio === null
                || $marco->instrumento === null
            ) {
                throw new \DomainException(
                    'El convenio específico debe tener asociado un Convenio Marco válido.'
                );
            }
        }

        $adenda = Adenda::query()
            ->with('instrumentoModificado')
            ->find($instrumento->id_instrumento);

        if (
            $adenda !== null
            && (
                $adenda->instrumentoModificado === null
                || (int) $adenda->id_instrumento_modificado === $id
            )
        ) {
            throw new \DomainException(
                'La adenda debe tener asociado un instrumento válido que modifica.'
            );
        }

        $renovacion = Renovacion::query()
            ->with('convenioRenovado.instrumento')
            ->find($convenio->id_instrumento);

        if (
            $renovacion !== null
            && (
                $renovacion->convenioRenovado === null
                || $renovacion->convenioRenovado->instrumento === null
                || (int) $renovacion->id_instrumento_renovado === $id
            )
        ) {
            throw new \DomainException(
                'La renovación debe tener asociado un convenio válido que renueva.'
            );
        }

        $sustitucion = Sustitucion::query()
            ->with('convenioSustituido.instrumento')
            ->find($convenio->id_instrumento);

        if (
            $sustitucion !== null
            && (
                $sustitucion->convenioSustituido === null
                || $sustitucion->convenioSustituido->instrumento === null
                || (int) $sustitucion->id_instrumento_sustituido === $id
            )
        ) {
            throw new \DomainException(
                'La sustitución debe tener asociado un convenio válido que sustituye.'
            );
        }
    }

    private function tieneVersionPdfDisponible(Documento $documento): bool
    {
        $ultimaVersion = $documento->versiones()
            ->orderByDesc('numero')
            ->first();

        if (
            $ultimaVersion === null
            || strtolower(pathinfo($ultimaVersion->ruta_interna, PATHINFO_EXTENSION)) !== 'pdf'
        ) {
            return false;
        }

        return Storage::disk('private')->exists($ultimaVersion->ruta_interna);
    }
}
