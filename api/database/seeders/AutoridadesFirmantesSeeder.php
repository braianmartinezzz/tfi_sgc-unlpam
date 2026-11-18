<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\UnidadAcademica;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Usuarios de demostración para quienes intervienen en la firma de convenios.
 *
 * El sistema tiene cuatro roles (Administración, Gestión, Revisión y Consulta);
 * "Rector/a", "Decano/a" y "Representante legal" no son roles nuevos, sino
 * perfiles que se resuelven con esos roles y con el alcance de cada usuario:
 *
 *  - Rector/a      -> Administración con alcance global: registra la firma de
 *                     cualquier convenio aprobado (marcos institucionales, adendas).
 *  - Decano/a      -> Gestión de su unidad académica: registra la firma de los
 *                     convenios aprobados de su Facultad (regla del Gate
 *                     'enviar-convenio-a-revision').
 *  - Representante -> Consulta sin alcance global: solo lee los convenios de su
 *                     unidad que ya están firmados, rescindidos o archivados. No
 *                     firma: la firma es un registro administrativo que hace
 *                     personal interno (el TP excluye la firma digital).
 *
 * Requiere haber corrido antes DatabaseSeeder (usa sus roles y unidades).
 * Se puede repetir sin duplicar usuarios.
 *
 *   php artisan db:seed --class=AutoridadesFirmantesSeeder
 */
class AutoridadesFirmantesSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('AutoridadesFirmantesSeeder no se ejecutó en producción.');

            return;
        }

        $unidadUno = UnidadAcademica::query()->where('sigla', 'UA-TEST-01')->firstOrFail();
        $unidadDos = UnidadAcademica::query()->where('sigla', 'UA-TEST-02')->firstOrFail();

        $rolAdministracion = Rol::query()->where('nombre', 'Administración')->firstOrFail();
        $rolGestion = Rol::query()->where('nombre', 'Gestión')->firstOrFail();
        $rolConsulta = Rol::query()->where('nombre', 'Consulta')->firstOrFail();

        $usuarios = [
            'rector@unlpam.edu.ar' => [
                'nombre' => 'Rector/a Demo',
                'password' => 'rector123',
                'id_rol' => $rolAdministracion->id_rol,
                'id_unidad' => $unidadUno->id_unidad_academica,
                'alcance_global' => true,
            ],
            'decano.ua1@unlpam.edu.ar' => [
                'nombre' => 'Decano/a Demo (Unidad 1)',
                'password' => 'decano123',
                'id_rol' => $rolGestion->id_rol,
                'id_unidad' => $unidadUno->id_unidad_academica,
                'alcance_global' => false,
            ],
            'decano.ua2@unlpam.edu.ar' => [
                'nombre' => 'Decano/a Demo (Unidad 2)',
                'password' => 'decano123',
                'id_rol' => $rolGestion->id_rol,
                'id_unidad' => $unidadDos->id_unidad_academica,
                'alcance_global' => false,
            ],
            'representante.legal@empresademo.com.ar' => [
                'nombre' => 'Representante Legal Autorizado Demo',
                'password' => 'representante123',
                'id_rol' => $rolConsulta->id_rol,
                'id_unidad' => $unidadUno->id_unidad_academica,
                'alcance_global' => false,
            ],
        ];

        foreach ($usuarios as $mail => $datos) {
            Usuario::query()->updateOrCreate(
                ['mail' => $mail],
                [
                    'nombre' => $datos['nombre'],
                    'password' => Hash::make($datos['password']),
                    'activo' => true,
                    'id_rol' => $datos['id_rol'],
                    'id_unidad' => $datos['id_unidad'],
                    'alcance_global' => $datos['alcance_global'],
                ]
            );
        }
    }
}
