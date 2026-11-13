<?php

namespace App\Services;

use App\Models\UnidadAcademica;
use DomainException;
use Illuminate\Database\Eloquent\Collection;

class UnidadAcademicaService
{
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

        return $query->get();
    }

    public function findById(string $id): ?UnidadAcademica
    {
        return UnidadAcademica::query()
            ->with('institucionFirmante')
            ->find($id);
    }

    public function store(array $data): UnidadAcademica
    {
        $unidad = UnidadAcademica::query()->create($data);

        return $unidad->load('institucionFirmante');
    }

    public function update(string $id, array $data): UnidadAcademica
    {
        $unidad = UnidadAcademica::query()->findOrFail($id);
        $unidad->update($data);

        return $unidad->load('institucionFirmante');
    }

    public function destroy(string $id): void
    {
        $unidad = UnidadAcademica::query()->findOrFail($id);

        if ($unidad->usuarios()->exists() || $unidad->convenios()->exists()) {
            throw new DomainException(
                'No se puede eliminar la unidad académica porque tiene usuarios o convenios asociados.'
            );
        }

        $unidad->delete();
    }
}