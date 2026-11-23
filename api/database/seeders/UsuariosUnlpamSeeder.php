<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\UnidadAcademica;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Persona;
use Illuminate\Support\Facades\DB;

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

        foreach ([
            'rector@unlpam.edu.ar' => [
                'nombre' => 'Rector',
                'apellido' => 'UNLPam',
                'dni' => 'DNI-DEMO-RECTOR',
            ],
            'decano@unlpam.edu.ar' => [
                'nombre' => 'Decano',
                'apellido' => 'UNLPam',
                'dni' => 'DNI-DEMO-DECANO',
            ],
        ] as $correo => $datos) {
            $persona = Persona::query()->updateOrCreate(
                ['dni' => $datos['dni']],
                [
                    'nombre' => $datos['nombre'],
                    'apellido' => $datos['apellido'],
                ]
            );

            $usuario = Usuario::query()->updateOrCreate(
                ['mail' => $correo],
                [
                    'nombre' => $datos['nombre'].' - UNLPam',
                    'password' => $password,
                    'activo' => true,
                    'id_rol' => $roles['Administración']->id_rol,
                    'id_unidad' => $unidadIngenieria->id_unidad_academica,
                    'alcance_global' => true,
                    'id_persona' => $persona->id_persona,
                ]
            );

            foreach ([4, 15] as $idInstrumento) {
                DB::table('firma')->updateOrInsert(
                    [
                        'id_instrumento' => $idInstrumento,
                        'id_persona' => $persona->id_persona,
                    ],
                    [
                        'fecha_hora_firma' => null,
                        'latitud' => null,
                        'longitud' => null,
                        'trazo_ruta' => null,
                        'biometria_confirmada' => false,
                    ]
                );
            }
        }
    }
}
