<?php

namespace App\Services;

use App\Models\InstitucionFirmante;
use App\Models\Representante;
use App\Models\UnidadAcademica;
use App\Models\Usuario;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class InstitucionFirmanteService
{
    public function __construct(private AuditoriaService $auditoria) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = InstitucionFirmante::query();

        if (isset($filters['buscar']) && $filters['buscar'] !== '') {
            $buscar = '%'.$filters['buscar'].'%';

            $query->where(function ($searchQuery) use ($buscar) {
                $searchQuery
                    ->where('razon_social', 'like', $buscar)
                    ->orWhere('cuit', 'like', $buscar);
            });
        }

        if (isset($filters['tipo_institucion']) && $filters['tipo_institucion'] !== '') {
            $query->where('tipo_institucion', $filters['tipo_institucion']);
        }

        if (isset($filters['activo']) && $filters['activo'] !== '') {
            $query->where('activo', filter_var($filters['activo'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->paginate(15)->appends($filters);
    }

    public function findById(string $id): InstitucionFirmante
    {
        return InstitucionFirmante::query()->findOrFail($id);
    }

    public function store(array $data, int $actorId): InstitucionFirmante
    {
        return DB::transaction(function () use ($data, $actorId) {
            $institucion = InstitucionFirmante::query()->create($data);
            $this->registrarAuditoria('Alta de institución firmante', $actorId, $institucion);

            return $institucion;
        });
    }

    public function update(string $id, array $data, int $actorId): InstitucionFirmante
    {
        return DB::transaction(function () use ($id, $data, $actorId) {
            $institucion = InstitucionFirmante::query()
                ->lockForUpdate()
                ->findOrFail($id);

            $institucion->fill($data)->save();
            $this->registrarAuditoria('Edición de institución firmante', $actorId, $institucion);

            return $institucion;
        });
    }

    public function cambiarEstado(string $id, bool $activo, int $actorId): array
    {
        return DB::transaction(function () use ($id, $activo, $actorId) {
            $institucion = InstitucionFirmante::query()
                ->lockForUpdate()
                ->findOrFail($id);
            $resumen = [
                'unidades_desactivadas' => 0,
                'usuarios_desactivados' => 0,
                'representantes_desactivados' => 0,
            ];

            if ($institucion->activo === $activo) {
                return ['data' => $institucion, 'resumen' => $resumen];
            }

            if (! $activo) {
                if ($institucion->es_propia) {
                    throw new DomainException('No podés desactivar la institución propia de la UNLPam.');
                }

                $actor = Usuario::query()->with('rol')->findOrFail($actorId);
                if ($actor->rol?->nombre === 'Administración'
                    && $institucion->unidadesAcademicas()
                        ->whereKey($actor->id_unidad)
                        ->exists()) {
                    throw new DomainException('No podés desactivar una institución que incluye tu propia unidad.');
                }

                $unidades = UnidadAcademica::query()
                    ->where('id_institucion', $institucion->id_institucion)
                    ->orderBy('id_unidad_academica')
                    ->lockForUpdate()
                    ->get();
                $unidadIds = $unidades->pluck('id_unidad_academica')->all();
                $usuarios = Usuario::query()
                    ->whereIn('id_unidad', $unidadIds)
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

                foreach ($unidades as $unidad) {
                    if ($unidad->activo) {
                        $unidad->activo = false;
                        $unidad->save();
                        $resumen['unidades_desactivadas']++;
                    }
                }

                $representantes = Representante::query()
                    ->where('id_institucion', $institucion->id_institucion)
                    ->where('activo', true)
                    ->orderBy('id_persona')
                    ->lockForUpdate()
                    ->get();

                foreach ($representantes as $representante) {
                    $representante->activo = false;
                    $representante->save();
                    $resumen['representantes_desactivados']++;
                }
            }

            $institucion->activo = $activo;
            $institucion->save();
            $this->auditoria->registrar(
                'Cambio de estado de institución firmante',
                $actorId,
                null,
                json_encode([
                    'id_institucion' => $institucion->id_institucion,
                    'activo' => $activo,
                    'resumen' => $resumen,
                ], JSON_UNESCAPED_UNICODE)
            );

            return ['data' => $institucion, 'resumen' => $resumen];
        });
    }

    private function registrarAuditoria(
        string $accion,
        int $actorId,
        InstitucionFirmante $institucion
    ): void {
        $this->auditoria->registrar(
            $accion,
            $actorId,
            null,
            json_encode([
                'id_institucion' => $institucion->id_institucion,
                'razon_social' => $institucion->razon_social,
            ], JSON_UNESCAPED_UNICODE)
        );
    }
}
