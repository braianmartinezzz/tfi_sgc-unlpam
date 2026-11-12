<?php

namespace Database\Seeders;

use App\Models\InstitucionFirmante;
use App\Models\Rol;
use App\Models\UnidadAcademica;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $rolAdministracion = Rol::firstOrCreate([
            'nombre' => 'Administración',
        ]);
        $rolGestion = Rol::firstOrCreate([
            'nombre' => 'Gestión',
        ]);
        Rol::firstOrCreate([
            'nombre' => 'Revisión',
        ]);
        Rol::firstOrCreate([
            'nombre' => 'Consulta',
        ]);

        $institucion = InstitucionFirmante::firstOrCreate([
            'razon_social' => 'Institución de prueba para convenios',
        ]);

        $unidadGestion = UnidadAcademica::updateOrCreate(
            ['sigla' => 'UA-TEST-01'],
            [
                'nombre' => 'Unidad Académica de Prueba 1',
                'id_institucion' => $institucion->id_institucion,
            ]
        );
        $unidadAdministracion = UnidadAcademica::updateOrCreate(
            ['sigla' => 'UA-TEST-02'],
            [
                'nombre' => 'Unidad Académica de Prueba 2',
                'id_institucion' => $institucion->id_institucion,
            ]
        );

        Usuario::updateOrCreate(
            ['mail' => 'gestion@unlpam.edu.ar'],
            [
                'nombre' => 'Usuario de Gestión de Prueba',
                'password' => Hash::make('password123'),
                'activo' => true,
                'id_rol' => $rolGestion->id_rol,
                'id_unidad' => $unidadGestion->id_unidad_academica,
            ]
        );

        Usuario::updateOrCreate(
            ['mail' => 'admin@unlpam.edu.ar'],
            [
                'nombre' => 'Usuario de Administración de Prueba',
                'password' => Hash::make('admin123'),
                'activo' => true,
                'id_rol' => $rolAdministracion->id_rol,
                'id_unidad' => $unidadAdministracion->id_unidad_academica,
            ]
        );
    }
}
