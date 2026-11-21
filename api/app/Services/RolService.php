<?php

namespace App\Services;

use App\Models\Rol;
use Illuminate\Database\Eloquent\Collection;

class RolService
{
    /**
     * @return Collection<int, Rol>
     */
    public function all(): Collection
    {
        return Rol::query()
            ->orderBy('nombre')
            ->get(['id_rol', 'nombre']);
    }
}
