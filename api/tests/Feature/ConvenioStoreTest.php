<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Services\ConvenioService;
use Illuminate\Database\QueryException;
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
        $usuario = $this->authenticate($unitId);

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

        $this->assertDatabaseHas('historial_estado', [
            'estado_anterior' => null,
            'estado_nuevo' => 'Borrador',
            'motivo' => 'Creación del convenio',
            'id_instrumento' => $marcoResponse->json('data.instrumento.id_instrumento'),
            'id_usuario' => $usuario->id_usuario,
        ]);

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
        $this->assertDatabaseCount('historial_estado', 1);

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

        $this->assertDatabaseHas('historial_estado', [
            'estado_anterior' => null,
            'estado_nuevo' => 'Borrador',
            'motivo' => 'Creación del convenio',
            'id_instrumento' => $specificResponse->json('data.instrumento.id_instrumento'),
            'id_usuario' => $usuario->id_usuario,
        ]);
        $this->assertDatabaseCount('historial_estado', 2);
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
        $this->assertDatabaseCount('historial_estado', 0);
    }

    public function test_history_failure_rolls_back_convenio_creation(): void
    {
        $institutionId = DB::table('institucion_firmante')->insertGetId([
            'razon_social' => 'Institución de prueba',
        ]);
        $unitId = DB::table('unidad_academica')->insertGetId([
            'sigla' => 'UA',
            'nombre' => 'Unidad Académica',
            'id_institucion' => $institutionId,
        ]);
        $usuario = new Usuario;
        $usuario->setAttribute('id_usuario', 999999);

        try {
            app(ConvenioService::class)->store([
                'titulo' => 'Convenio con usuario inexistente',
                'objeto' => null,
                'id_unidad_academica' => $unitId,
                'tipo' => 'Colaboración',
                'es_marco' => true,
                'instituciones_ids' => [$institutionId],
            ], $usuario);

            $this->fail('Se esperaba que fallara la creación del historial.');
        } catch (QueryException) {
            $this->assertDatabaseCount('instrumento', 0);
            $this->assertDatabaseCount('convenio', 0);
            $this->assertDatabaseCount('marco', 0);
            $this->assertDatabaseCount('convenio_institucion', 0);
            $this->assertDatabaseCount('historial_estado', 0);
        }
    }

    private function authenticate(int $unitId): Usuario
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

        return $usuario;
    }
}
