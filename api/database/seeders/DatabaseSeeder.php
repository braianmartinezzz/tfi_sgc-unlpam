<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesUnlpamSeeder::class,
            InstitucionUnlpamSeeder::class,
            UnidadesAcademicasUnlpamSeeder::class,
            UsuariosUnlpamSeeder::class,
        ]);
    }
}
