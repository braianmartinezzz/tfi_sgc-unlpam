<?php

namespace App\Services;

use App\Models\InstitucionFirmante;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InstitucionFirmanteService
{
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

        return $query->paginate(15)->appends($filters);
    }

    public function findById(string $id): InstitucionFirmante
    {
        return InstitucionFirmante::query()->findOrFail($id);
    }

    public function store(array $data): InstitucionFirmante
    {
        return InstitucionFirmante::query()->create($data);
    }

    public function update(string $id, array $data): InstitucionFirmante
    {
        return DB::transaction(function () use ($id, $data) {
            $institucion = InstitucionFirmante::query()
                ->lockForUpdate()
                ->findOrFail($id);

            $institucion->fill($data)->save();

            return $institucion;
        });
    }

    public function destroy(string $id): void
    {
        DB::transaction(function () use ($id) {
            $institucion = InstitucionFirmante::query()
                ->lockForUpdate()
                ->findOrFail($id);

            if ($institucion->convenios()->exists() || $institucion->unidadesAcademicas()->exists()) {
                throw new \DomainException(
                    'No se puede eliminar una institución firmante que se encuentra vinculada a convenios o unidades académicas.'
                );
            }

            if (DB::table('representante')
                ->where('id_institucion', $institucion->id_institucion)
                ->exists()) {
                throw new \DomainException(
                    'No se puede eliminar una institución firmante que tiene representantes asociados.'
                );
            }

            if (! $institucion->delete()) {
                throw new RuntimeException('No se pudo eliminar la institución firmante.');
            }
        });
    }
}
