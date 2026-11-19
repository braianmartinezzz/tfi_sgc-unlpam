<?php

namespace Database\Seeders;

use App\Models\Rol;
use Illuminate\Database\Seeder;

class RolesUnlpamSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Administración', 'Gestión', 'Consulta', 'Revisión'] as $nombre) {
            Rol::query()->firstOrCreate(['nombre' => $nombre]);
        }
    }
}
