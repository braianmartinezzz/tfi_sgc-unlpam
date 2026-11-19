<?php

namespace App\Services;

use App\Models\InstitucionFirmante;
use App\Models\UnidadAcademica;
use App\Models\Usuario;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UnidadAcademicaService
{
    public function __construct(private AuditoriaService $auditoria) {}

    public function all(array $filters): Collection
    {
        $query = UnidadAcademica::query()
            ->with('institucionFirmante')
            ->orderBy('nombre');

        if (isset($filters['id_institucion']) && $filters['id_institucion'] !== '') {
            $query->where('id_institucion', $filters['id_institucion']);
        }

        if (isset($filters['buscar']) && $filters['buscar'] !== '') {
            $texto = '%'.$filters['buscar'].'%';

            $query->where(function ($subquery) use ($texto) {
                $subquery->where('nombre', 'like', $texto)
                    ->orWhere('sigla', 'like', $texto);
            });
        }

        if (isset($filters['activo']) && $filters['activo'] !== '') {
            $query->where('activo', filter_var($filters['activo'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->get();
    }

    public function findById(string $id): ?UnidadAcademica
    {
        return UnidadAcademica::query()
            ->with('institucionFirmante')
            ->find($id);
    }

    public function store(array $data, int $actorId): UnidadAcademica
    {
        return DB::transaction(function () use ($data, $actorId) {
            $institucion = InstitucionFirmante::query()
                ->lockForUpdate()
                ->findOrFail($data['id_institucion']);

            if (! $institucion->activo) {
                throw new DomainException('No podés crear una unidad bajo una institución inactiva.');
            }

            $unidad = UnidadAcademica::query()->create($data);
            $this->registrarAuditoria('Alta de unidad académica', $actorId, $unidad);

            return $unidad->load('institucionFirmante');
        });
    }

    public function update(string $id, array $data, int $actorId): UnidadAcademica
    {
        return DB::transaction(function () use ($id, $data, $actorId) {
            $unidad = UnidadAcademica::query()
                ->lockForUpdate()
                ->findOrFail($id);

            if (isset($data['id_institucion'])
                && (int) $data['id_institucion'] !== (int) $unidad->id_institucion) {
                $institucion = InstitucionFirmante::query()
                    ->lockForUpdate()
                    ->findOrFail($data['id_institucion']);

                if (! $institucion->activo) {
                    throw new DomainException('No podés mover una unidad a una institución inactiva.');
                }
            }

            $unidad->fill($data)->save();
            $this->registrarAuditoria('Edición de unidad académica', $actorId, $unidad);

            return $unidad->load('institucionFirmante');
        });
    }

    public function cambiarEstado(string $id, bool $activo, int $actorId): array
    {
        return DB::transaction(function () use ($id, $activo, $actorId) {
            $unidad = UnidadAcademica::query()
                ->lockForUpdate()
                ->findOrFail($id);
            $resumen = [
                'unidades_desactivadas' => 0,
                'usuarios_desactivados' => 0,
                'representantes_desactivados' => 0,
            ];

            if ($unidad->activo === $activo) {
                return ['data' => $unidad->load('institucionFirmante'), 'resumen' => $resumen];
            }

            if ($activo) {
                $institucion = InstitucionFirmante::query()
                    ->lockForUpdate()
                    ->findOrFail($unidad->id_institucion);

                if (! $institucion->activo) {
                    throw new DomainException('No podés reactivar una unidad de una institución inactiva.');
                }
            } else {
                $actor = Usuario::query()->with('rol')->findOrFail($actorId);

                if ($actor->rol?->nombre === 'Administración'
                    && (int) $actor->id_unidad === (int) $unidad->id_unidad_academica) {
                    throw new DomainException('No podés desactivar la unidad a la que pertenecés.');
                }

                $usuarios = Usuario::query()
                    ->where('id_unidad', $unidad->id_unidad_academica)
                    ->orderBy('id_usuario')
                    ->lockForUpdate()
                    ->get();

                foreach ($usuarios as $usuario) {
                    if ($usuario->activo) {
                        $usuario->activo = false;
                        $usuario->save();
                        $resumen['usuarios_desactivados']++;
                    }

                    $usuario->tokens()->delete();
                }
            }

            $unidad->activo = $activo;
            $unidad->save();
            if (! $activo) {
                $resumen['unidades_desactivadas'] = 1;
            }

            $this->auditoria->registrar(
                'Cambio de estado de unidad académica',
                $actorId,
                null,
                json_encode([
                    'id_unidad_academica' => $unidad->id_unidad_academica,
                    'activo' => $activo,
                    'resumen' => $resumen,
                ], JSON_UNESCAPED_UNICODE)
            );

            return ['data' => $unidad->load('institucionFirmante'), 'resumen' => $resumen];
        });
    }

    private function registrarAuditoria(
        string $accion,
        int $actorId,
        UnidadAcademica $unidad
    ): void {
        $this->auditoria->registrar(
            $accion,
            $actorId,
            null,
            json_encode([
                'id_unidad_academica' => $unidad->id_unidad_academica,
                'nombre' => $unidad->nombre,
            ], JSON_UNESCAPED_UNICODE)
        );
    }
}
