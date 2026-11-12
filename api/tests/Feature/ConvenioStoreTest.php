<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConvenioStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_marco_and_especifico_convenios_with_firmantes(): void
    {
        $institutionId = DB::table('institucion_firmante')->insertGetId([
            'razon_social' => 'Institución de prueba',
        ]);
        $unitId = DB::table('unidad_academica')->insertGetId([
            'sigla' => 'UA',
            'nombre' => 'Unidad Académica',
            'id_institucion' => $institutionId,
        ]);
        $this->authenticate($unitId);

        $marcoResponse = $this->postJson('/api/convenios', [
            'titulo' => 'Convenio marco',
            'id_unidad_academica' => $unitId,
            'tipo' => 'Colaboración',
            'es_marco' => true,
            'instituciones_ids' => [$institutionId],
        ]);

        $marcoResponse
            ->assertCreated()
            ->assertJsonPath('data.instrumento.estado', 'Borrador')
            ->assertJsonPath('data.instrumento.version_registro', 1)
            ->assertJsonPath('data.instrumento.dias_preaviso', 30)
            ->assertJsonPath('data.subtipo.id_instrumento', $marcoResponse->json('data.convenio.id_instrumento'))
            ->assertJsonPath('data.instituciones_firmantes.0.id_institucion', $institutionId);

        $this->postJson('/api/convenios', [
            'titulo' => 'Marco inválido',
            'id_unidad_academica' => $unitId,
            'tipo' => 'Colaboración',
            'es_marco' => true,
            'id_convenio_marco' => $marcoResponse->json('data.convenio.id_instrumento'),
            'instituciones_ids' => [$institutionId],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id_convenio_marco']);

        $this->assertDatabaseCount('instrumento', 1);

        $specificResponse = $this->postJson('/api/convenios', [
            'titulo' => 'Convenio específico',
            'id_unidad_academica' => $unitId,
            'tipo' => 'Pasantía',
            'es_marco' => false,
            'id_convenio_marco' => $marcoResponse->json('data.convenio.id_instrumento'),
            'instituciones_ids' => [$institutionId],
            'dias_preaviso' => 0,
        ]);

        $specificResponse
            ->assertCreated()
            ->assertJsonPath('data.instrumento.dias_preaviso', 0)
            ->assertJsonPath(
                'data.subtipo.id_convenio_marco',
                $marcoResponse->json('data.convenio.id_instrumento')
            );
    }

    public function test_it_returns_validation_errors_without_creating_records(): void
    {
        $institutionId = DB::table('institucion_firmante')->insertGetId([
            'razon_social' => 'Institución de prueba',
        ]);
        $unitId = DB::table('unidad_academica')->insertGetId([
            'sigla' => 'UA',
            'nombre' => 'Unidad Académica',
            'id_institucion' => $institutionId,
        ]);
        $this->authenticate($unitId);

        $this->postJson('/api/convenios', [
            'titulo' => 'Convenio inválido',
            'id_unidad_academica' => $unitId,
            'tipo' => 'Colaboración',
            'es_marco' => false,
            'instituciones_ids' => [$institutionId, $institutionId],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id_convenio_marco', 'instituciones_ids.1']);

        $this->assertDatabaseCount('instrumento', 0);
    }

    private function authenticate(int $unitId): void
    {
        $rolId = DB::table('rol')->insertGetId([
            'nombre' => 'Prueba',
        ]);
        $usuario = Usuario::query()->create([
            'mail' => 'convenio@example.com',
            'nombre' => 'Usuario de prueba',
            'password' => Hash::make('password-segura'),
            'activo' => true,
            'id_rol' => $rolId,
            'id_unidad' => $unitId,
        ]);

        $this->withToken($usuario->createToken('test')->plainTextToken);
    }
}
