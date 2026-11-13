<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UnidadAcademicaTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/unidades-academicas')->assertUnauthorized();
    }

    public function test_lists_ordered_by_name_and_filters_by_institucion(): void
    {
        $ctx = $this->authenticate();
        $otraInstitucion = $this->crearInstitucion('Otra institución');
        $this->crearUnidad('ZZZ', 'Zeta', $ctx['institucion']);
        $this->crearUnidad('AAA', 'Alfa', $ctx['institucion']);
        $this->crearUnidad('OTR', 'Otra', $otraInstitucion);

        $this->getJson('/api/unidades-academicas')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.nombre', 'Alfa')
            ->assertJsonPath('data.3.nombre', 'Zeta');

        $this->getJson('/api/unidades-academicas?id_institucion='.$otraInstitucion)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sigla', 'OTR');
    }

    public function test_shows_one_unidad_or_returns_404(): void
    {
        $ctx = $this->authenticate();

        $this->getJson('/api/unidades-academicas/'.$ctx['unidad'])
            ->assertOk()
            ->assertJsonPath('data.sigla', 'UA-USR');

        $this->getJson('/api/unidades-academicas/99999')->assertNotFound();
    }

    public function test_creates_a_unidad(): void
    {
        $ctx = $this->authenticate();

        $this->postJson('/api/unidades-academicas', [
            'sigla' => 'NUEVA',
            'nombre' => 'Unidad nueva',
            'id_institucion' => $ctx['institucion'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.sigla', 'NUEVA');

        $this->assertDatabaseHas('unidad_academica', ['sigla' => 'NUEVA']);
    }

    public function test_rejects_duplicated_sigla_and_unknown_institucion(): void
    {
        $this->authenticate();

        $this->postJson('/api/unidades-academicas', [
            'sigla' => 'UA-USR',
            'nombre' => 'Repetida',
            'id_institucion' => 99999,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sigla', 'id_institucion']);
    }

    public function test_updates_a_unidad_keeping_its_own_sigla(): void
    {
        $ctx = $this->authenticate();

        $this->putJson('/api/unidades-academicas/'.$ctx['unidad'], [
            'sigla' => 'UA-USR',
            'nombre' => 'Nombre actualizado',
        ])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Nombre actualizado');

        $this->assertDatabaseHas('unidad_academica', ['nombre' => 'Nombre actualizado']);
    }

    public function test_deletes_a_unidad_without_dependencies(): void
    {
        $ctx = $this->authenticate();
        $libre = $this->crearUnidad('LIB', 'Libre', $ctx['institucion']);

        $this->deleteJson('/api/unidades-academicas/'.$libre)->assertOk();

        $this->assertDatabaseMissing('unidad_academica', ['id_unidad_academica' => $libre]);
    }

    public function test_does_not_delete_a_unidad_with_usuarios(): void
    {
        $ctx = $this->authenticate();

        $this->deleteJson('/api/unidades-academicas/'.$ctx['unidad'])
            ->assertStatus(409);

        $this->assertDatabaseHas('unidad_academica', ['id_unidad_academica' => $ctx['unidad']]);
    }

    private function crearInstitucion(string $razonSocial): int
    {
        return DB::table('institucion_firmante')->insertGetId([
            'razon_social' => $razonSocial,
        ]);
    }

    private function crearUnidad(string $sigla, string $nombre, int $institucionId): int
    {
        return DB::table('unidad_academica')->insertGetId([
            'sigla' => $sigla,
            'nombre' => $nombre,
            'id_institucion' => $institucionId,
        ]);
    }

    private function authenticate(): array
    {
        $rolId = DB::table('rol')->insertGetId(['nombre' => 'Administración']);
        $institucionId = $this->crearInstitucion('Institución de prueba');
        $unidadId = $this->crearUnidad('UA-USR', 'Unidad del usuario', $institucionId);

        $usuario = Usuario::query()->create([
            'mail' => 'admin-unidades@example.com',
            'nombre' => 'Admin de unidades',
            'password' => Hash::make('password-segura'),
            'activo' => true,
            'id_rol' => $rolId,
            'id_unidad' => $unidadId,
        ]);

        $this->withToken($usuario->createToken('unidades-test')->plainTextToken);

        return ['institucion' => $institucionId, 'unidad' => $unidadId];
    }
}