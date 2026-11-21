<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\UnidadAcademica;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuariosUnlpamSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [];
        foreach (['Administración', 'Gestión', 'Consulta', 'Revisión'] as $nombre) {
            $roles[$nombre] = Rol::query()->where('nombre', $nombre)->firstOrFail();
        }

        $password = Hash::make('password123');
        $unidades = [];
        foreach (['FA', 'FCS', 'FCEyJ', 'FCEyN', 'FCH', 'FCV', 'FI'] as $sigla) {
            $unidades[$sigla] = UnidadAcademica::query()
                ->where('sigla', $sigla)
                ->firstOrFail();
        }

        foreach ($unidades as $sigla => $unidad) {
            foreach ([
                'Gestión' => 'gestion',
                'Consulta' => 'consulta',
                'Revisión' => 'revision',
            ] as $nombreRol => $prefijoCorreo) {
                $correo = $sigla === 'FI' && $nombreRol === 'Gestión'
                    ? 'gestion@unlpam.edu.ar'
                    : sprintf(
                        '%s.%s@unlpam.edu.ar',
                        $prefijoCorreo,
                        strtolower($sigla)
                    );

                Usuario::query()->updateOrCreate(
                    ['mail' => $correo],
                    [
                        'nombre' => "Usuario de {$nombreRol} - {$unidad->nombre}",
                        'password' => $password,
                        'activo' => true,
                        'id_rol' => $roles[$nombreRol]->id_rol,
                        'id_unidad' => $unidad->id_unidad_academica,
                        'alcance_global' => false,
                    ]
                );
            }
        }

        $unidadIngenieria = $unidades['FI'];

        Usuario::query()->updateOrCreate(
            ['mail' => 'admin@unlpam.edu.ar'],
            [
                'nombre' => 'Usuario de Administración - UNLPam',
                'password' => $password,
                'activo' => true,
                'id_rol' => $roles['Administración']->id_rol,
                'id_unidad' => $unidadIngenieria->id_unidad_academica,
                'alcance_global' => false,
            ]
        );
    }
}
