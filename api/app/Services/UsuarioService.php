<?php

namespace App\Services;

use App\Models\UnidadAcademica;
use App\Models\Usuario;
use DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsuarioService
{
    private const RELACIONES = ['rol', 'unidadAcademica', 'persona'];

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Usuario::query()
            ->with(self::RELACIONES)
            ->orderBy('nombre');

        if (isset($filters['id_rol']) && $filters['id_rol'] !== '') {
            $query->where('id_rol', $filters['id_rol']);
        }

        if (isset($filters['id_unidad']) && $filters['id_unidad'] !== '') {
            $query->where('id_unidad', $filters['id_unidad']);
        }

        if (isset($filters['activo']) && $filters['activo'] !== '') {
            $activo = filter_var($filters['activo'], FILTER_VALIDATE_BOOLEAN);

            $query->where('activo', $activo);
        }

        if (isset($filters['buscar']) && $filters['buscar'] !== '') {
            $texto = '%'.$filters['buscar'].'%';

            $query->where(function ($subquery) use ($texto) {
                $subquery->where('nombre', 'like', $texto)
                    ->orWhere('mail', 'like', $texto);
            });
        }

        return $query->paginate(15)->withQueryString();
    }

    public function findById(string $id): ?Usuario
    {
        return Usuario::query()
            ->with(self::RELACIONES)
            ->find($id);
    }

    public function store(array $data): Usuario
    {
        return DB::transaction(function () use ($data) {
            $data['password'] = Hash::make($data['password']);
            $data['activo'] ??= true;
            $data['alcance_global'] ??= false;

            $unidad = UnidadAcademica::query()
                ->lockForUpdate()
                ->findOrFail($data['id_unidad']);

            if (! $unidad->activo) {
                throw new DomainException('No podés crear un usuario en una unidad académica inactiva.');
            }

            $usuario = Usuario::query()->create($data);

            return $usuario->load(self::RELACIONES);
        });
    }

    public function update(string $id, array $data, int $actorId): Usuario
    {
        return DB::transaction(function () use ($id, $data, $actorId) {
            $usuario = Usuario::query()->lockForUpdate()->findOrFail($id);

            if ((int) $usuario->id_usuario === $actorId) {
                if (array_key_exists('activo', $data) && ! $data['activo']) {
                    throw new DomainException('No podés desactivar tu propia cuenta.');
                }

                if (isset($data['id_rol']) && (int) $data['id_rol'] !== (int) $usuario->id_rol) {
                    throw new DomainException('No podés cambiar tu propio rol.');
                }
            }

            $unidadId = $data['id_unidad'] ?? $usuario->id_unidad;
            $moviendoUnidad = isset($data['id_unidad'])
                && (int) $data['id_unidad'] !== (int) $usuario->id_unidad;
            $reactivando = ! $usuario->activo
                && isset($data['activo'])
                && (bool) $data['activo'];

            if ($moviendoUnidad || $reactivando) {
                $unidad = UnidadAcademica::query()->lockForUpdate()->findOrFail($unidadId);

                if (! $unidad->activo) {
                    throw new DomainException(
                        'No podés reactivar ni mover un usuario a una unidad académica inactiva.'
                    );
                }
            }

            if (array_key_exists('password', $data)) {
                $data['password'] = Hash::make($data['password']);
            }

            $usuario->update($data);

            if (! $usuario->activo) {
                $usuario->tokens()->delete();
            }

            return $usuario->load(self::RELACIONES);
        });
    }

    /**
     * Baja lógica: el usuario queda inactivo y se le revocan los tokens.
     * No se borra la fila porque está referenciado por historial, auditoría, versiones, etc.
     */
    public function destroy(string $id, int $actorId): void
    {
        $usuario = Usuario::query()->findOrFail($id);

        if ((int) $usuario->id_usuario === $actorId) {
            throw new DomainException('No podés desactivar tu propia cuenta.');
        }

        $usuario->update(['activo' => false]);
        $usuario->tokens()->delete();
    }
}
