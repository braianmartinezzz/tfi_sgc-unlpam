<?php

namespace App\Services;

use App\Models\Adenda;
use App\Models\Convenio;
use App\Models\Especifico;
use App\Models\HistorialEstado;
use App\Models\Instrumento;
use App\Models\Marco;
use App\Models\Notificacion;
use App\Models\RegistroAuditoria;
use App\Models\Renovacion;
use App\Models\Sustitucion;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ConvenioService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Convenio::query()->with([
            'instrumento',
            'unidadAcademica',
            'institucionesFirmantes',
            'marco',
            'especifico',
        ]);

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

    public function findById(string $id): ?Convenio
    {
        return Convenio::query()
            ->with([
                'instrumento',
                'unidadAcademica',
                'institucionesFirmantes',
                'marco',
                'especifico.marco.instrumento',
                'historialEstados',
            ])
            ->find($id);
    }

    public function update(string $id, array $data): Convenio
    {
        return DB::transaction(function () use ($id, $data) {
            $convenio = Convenio::query()
                ->lockForUpdate()
                ->findOrFail($id);
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

    public function destroy(string $id): void
    {
        DB::transaction(function () use ($id) {
            $convenio = Convenio::query()
                ->lockForUpdate()
                ->findOrFail($id);
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
}
