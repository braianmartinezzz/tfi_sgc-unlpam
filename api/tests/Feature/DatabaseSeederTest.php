<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_repeatable_login_fixtures(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('rol', 4);
        $this->assertDatabaseCount('institucion_firmante', 1);
        $this->assertDatabaseCount('unidad_academica', 2);
        $this->assertDatabaseCount('usuario', 2);

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
    }
}
