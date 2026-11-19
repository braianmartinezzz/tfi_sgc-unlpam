<?php

namespace Tests\Feature;

use App\Models\InstitucionFirmante;
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

    public function test_database_seeder_creates_repeatable_login_fixtures(): void
    {
        $this->seed(DatabaseSeeder::class);
        $unidadGestionId = DB::table('unidad_academica')
            ->where('sigla', 'UA-TEST-01')
            ->value('id_unidad_academica');
        $unidadAdministracionId = DB::table('unidad_academica')
            ->where('sigla', 'UA-TEST-02')
            ->value('id_unidad_academica');

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('rol', 4);
        $this->assertDatabaseCount('institucion_firmante', 1);
        $this->assertDatabaseCount('unidad_academica', 9);
        $this->assertDatabaseCount('usuario', 2);

        $this->assertDatabaseHas('unidad_academica', [
            'id_unidad_academica' => $unidadGestionId,
            'sigla' => 'UA-TEST-01',
        ]);
        $this->assertDatabaseHas('unidad_academica', [
            'id_unidad_academica' => $unidadAdministracionId,
            'sigla' => 'UA-TEST-02',
        ]);

        $gestion = Usuario::query()
            ->where('mail', 'gestion@unlpam.edu.ar')
            ->firstOrFail();
        $administracion = Usuario::query()
            ->where('mail', 'admin@unlpam.edu.ar')
            ->firstOrFail();

        $this->assertTrue(Hash::check('password123', $gestion->password));
        $this->assertSame('Gestión', $gestion->rol->nombre);
        $this->assertSame('Administración', $administracion->rol->nombre);
        $this->assertSame(
            $gestion->unidadAcademica->id_institucion,
            $administracion->unidadAcademica->id_institucion
        );
        $this->assertSame('UA-TEST-01', $gestion->unidadAcademica->sigla);
        $this->assertSame('UA-TEST-02', $administracion->unidadAcademica->sigla);
        $this->assertSame(
            'Universidad Nacional de La Pampa',
            $gestion->unidadAcademica->institucionFirmante->razon_social
        );
        $this->assertTrue(
            InstitucionFirmante::query()->where('es_propia', true)->sole()->es_propia
        );
        $this->assertNull($gestion->unidadAcademica->institucionFirmante->tipo_institucion);
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
