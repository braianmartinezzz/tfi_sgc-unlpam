<?php

namespace Tests\Feature;

use App\Models\InstitucionFirmante;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\InstitucionUnlpamSeeder;
use Database\Seeders\UnidadesAcademicasUnlpamSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_repeatable_unlpam_initial_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $usuariosIniciales = Usuario::query()
            ->orderBy('mail')
            ->pluck('id_usuario', 'mail')
            ->all();

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('rol', 4);
        $this->assertDatabaseCount('institucion_firmante', 1);
        $this->assertDatabaseCount('unidad_academica', 7);
        $this->assertDatabaseCount('usuario', 22);
        $this->assertSame(
            $usuariosIniciales,
            Usuario::query()->orderBy('mail')->pluck('id_usuario', 'mail')->all()
        );

        $institucion = InstitucionFirmante::query()
            ->where('es_propia', true)
            ->firstOrFail();
        $facultades = [
            'FA' => 'Facultad de Agronomía',
            'FCS' => 'Facultad de Ciencias de la Salud',
            'FCEyJ' => 'Facultad de Ciencias Económicas y Jurídicas',
            'FCEyN' => 'Facultad de Ciencias Exactas y Naturales',
            'FCH' => 'Facultad de Ciencias Humanas',
            'FCV' => 'Facultad de Ciencias Veterinarias',
            'FI' => 'Facultad de Ingeniería',
        ];
        $roles = [
            'Gestión' => 'gestion',
            'Consulta' => 'consulta',
            'Revisión' => 'revision',
        ];
        $correosEsperados = ['admin@unlpam.edu.ar'];

        foreach ($facultades as $sigla => $nombreFacultad) {
            $unidad = DB::table('unidad_academica')->where('sigla', $sigla)->first();
            $this->assertNotNull($unidad);
            $this->assertSame($nombreFacultad, $unidad->nombre);
            $this->assertSame($institucion->id_institucion, $unidad->id_institucion);

            foreach ($roles as $nombreRol => $prefijoCorreo) {
                $correo = $sigla === 'FI' && $nombreRol === 'Gestión'
                    ? 'gestion@unlpam.edu.ar'
                    : sprintf('%s.%s@unlpam.edu.ar', $prefijoCorreo, strtolower($sigla));
                $correosEsperados[] = $correo;

                $usuario = Usuario::query()->where('mail', $correo)->firstOrFail();
                $this->assertSame($nombreRol, $usuario->rol->nombre);
                $this->assertSame($sigla, $usuario->unidadAcademica->sigla);
                $this->assertTrue($usuario->activo);
                $this->assertFalse($usuario->alcance_global);
                $this->assertTrue(Hash::check('password123', $usuario->password));
            }

            foreach (array_keys($roles) as $nombreRol) {
                $this->assertSame(
                    1,
                    Usuario::query()
                        ->where('id_unidad', $unidad->id_unidad_academica)
                        ->whereHas('rol', fn ($query) => $query->where('nombre', $nombreRol))
                        ->count()
                );
            }
        }

        $administracion = Usuario::query()
            ->where('mail', 'admin@unlpam.edu.ar')
            ->firstOrFail();
        $this->assertSame('Administración', $administracion->rol->nombre);
        $this->assertSame('FI', $administracion->unidadAcademica->sigla);
        $this->assertTrue(Hash::check('password123', $administracion->password));

        sort($correosEsperados);
        $correosReales = Usuario::query()->orderBy('mail')->pluck('mail')->all();
        $this->assertSame($correosEsperados, $correosReales);
        $this->assertSame(1, Rol::query()->where('nombre', 'Administración')->count());
        $this->assertTrue(
            InstitucionFirmante::query()->where('es_propia', true)->sole()->es_propia
        );
    }

    public function test_unidades_academicas_seeder_is_idempotent_and_preserves_unrelated_units(): void
    {
        $this->seed(InstitucionUnlpamSeeder::class);
        $institucionPropia = InstitucionFirmante::query()
            ->where('es_propia', true)
            ->firstOrFail();
        $otraInstitucion = InstitucionFirmante::query()->create([
            'razon_social' => 'Otra institución',
        ]);

        $facultadExistente = DB::table('unidad_academica')->insertGetId([
            'sigla' => 'FA',
            'nombre' => 'Nombre anterior',
            'id_institucion' => $otraInstitucion->id_institucion,
            'activo' => false,
        ]);
        $unidadAjena = DB::table('unidad_academica')->insertGetId([
            'sigla' => 'UA-AJENA',
            'nombre' => 'Unidad ajena',
            'id_institucion' => $otraInstitucion->id_institucion,
            'activo' => false,
        ]);

        $this->seed(UnidadesAcademicasUnlpamSeeder::class);
        $this->seed(UnidadesAcademicasUnlpamSeeder::class);

        $facultadesEsperadas = [
            'FA' => 'Facultad de Agronomía',
            'FCS' => 'Facultad de Ciencias de la Salud',
            'FCEyJ' => 'Facultad de Ciencias Económicas y Jurídicas',
            'FCEyN' => 'Facultad de Ciencias Exactas y Naturales',
            'FCH' => 'Facultad de Ciencias Humanas',
            'FCV' => 'Facultad de Ciencias Veterinarias',
            'FI' => 'Facultad de Ingeniería',
        ];

        $this->assertDatabaseCount('unidad_academica', 8);
        foreach ($facultadesEsperadas as $sigla => $nombre) {
            $this->assertDatabaseHas('unidad_academica', [
                'sigla' => $sigla,
                'nombre' => $nombre,
                'id_institucion' => $institucionPropia->id_institucion,
                'activo' => true,
            ]);
            $this->assertSame(
                1,
                DB::table('unidad_academica')->where('sigla', $sigla)->count()
            );
        }

        $this->assertDatabaseHas('unidad_academica', [
            'id_unidad_academica' => $facultadExistente,
            'sigla' => 'FA',
        ]);
        $this->assertDatabaseHas('unidad_academica', [
            'id_unidad_academica' => $unidadAjena,
            'sigla' => 'UA-AJENA',
            'id_institucion' => $otraInstitucion->id_institucion,
            'activo' => false,
        ]);
    }

    public function test_unidades_academicas_seeder_fails_without_own_institution(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->seed(UnidadesAcademicasUnlpamSeeder::class);
    }
}
