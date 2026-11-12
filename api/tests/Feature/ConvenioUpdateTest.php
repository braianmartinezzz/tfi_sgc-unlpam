<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Especifico;
use App\Models\Instrumento;
use App\Models\Marco;
use App\Models\Usuario;
use App\Services\ConvenioService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConvenioUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_instrumento_convenio_and_signer_institutions(): void
    {
        $context = $this->authenticate();
        $convenio = $this->createConvenio($context, true);

        $this->putJson('/api/convenios/'.$convenio->id_instrumento, [
            'titulo' => 'Título actualizado',
            'objeto' => 'Objeto actualizado',
            'expediente' => 'EXP-200',
            'resolucion' => 'RES-200',
            'fecha_firma' => '2026-02-01',
            'inicio' => '2026-03-01',
            'vencimiento' => '2027-03-01',
            'dias_preaviso' => 45,
            'notas' => 'Notas actualizadas',
            'tipo' => 'Pasantía',
            'alcance' => 'Alcance actualizado',
            'obligaciones' => 'Obligaciones actualizadas',
            'condiciones_recision' => 'Condiciones actualizadas',
            'marco_legal' => 'Marco legal actualizado',
            'condicion_renovacion' => 'Renovación actualizada',
            'confidencialidad' => false,
            'id_unidad_academica' => $context['otraUnidad'],
            'instituciones_ids' => [$context['otraInstitucion']],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Convenio actualizado correctamente.')
            ->assertJsonPath('data.instrumento.titulo', 'Título actualizado')
            ->assertJsonPath('data.convenio.tipo', 'Pasantía')
            ->assertJsonPath('data.instituciones_firmantes.0.id_institucion', $context['otraInstitucion']);

        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $convenio->id_instrumento,
            'estado' => 'Borrador',
            'version_registro' => 1,
            'titulo' => 'Título actualizado',
            'objeto' => 'Objeto actualizado',
            'expediente' => 'EXP-200',
            'resolucion' => 'RES-200',
            'dias_preaviso' => 45,
            'notas' => 'Notas actualizadas',
        ]);
        $actualizado = Instrumento::query()->findOrFail($convenio->id_instrumento);
        $this->assertSame('2026-02-01', $actualizado->fecha_firma->format('Y-m-d'));
        $this->assertSame('2026-03-01', $actualizado->inicio->format('Y-m-d'));
        $this->assertSame('2027-03-01', $actualizado->vencimiento->format('Y-m-d'));

        $this->assertDatabaseHas('convenio', [
            'id_instrumento' => $convenio->id_instrumento,
            'tipo' => 'Pasantía',
            'alcance' => 'Alcance actualizado',
            'obligaciones' => 'Obligaciones actualizadas',
            'condiciones_recision' => 'Condiciones actualizadas',
            'marco_legal' => 'Marco legal actualizado',
            'condicion_renovacion' => 'Renovación actualizada',
            'confidencialidad' => false,
            'id_unidad_academica' => $context['otraUnidad'],
        ]);
        $this->assertDatabaseMissing('convenio_institucion', [
            'id_instrumento' => $convenio->id_instrumento,
            'id_institucion' => $context['institucion'],
        ]);
        $this->assertDatabaseHas('convenio_institucion', [
            'id_instrumento' => $convenio->id_instrumento,
            'id_institucion' => $context['otraInstitucion'],
        ]);
        $this->assertDatabaseCount('historial_estado', 0);
    }

    public function test_it_reassigns_an_especifico_and_preserves_or_clears_omitted_and_nullable_fields(): void
    {
        $context = $this->authenticate();
        $marcoActual = $this->createConvenio($context, true);
        $marcoNuevo = $this->createConvenio($context, true);
        $especifico = $this->createConvenio(
            $context,
            false,
            ['objeto' => 'Objeto que se limpiará', 'notas' => 'Notas que se limpiarán'],
            ['alcance' => 'Alcance que se limpiará', 'confidencialidad' => true],
            $marcoActual->id_instrumento
        );

        $this->putJson('/api/convenios/'.$especifico->id_instrumento, [
            'objeto' => null,
            'notas' => null,
            'alcance' => null,
            'confidencialidad' => null,
            'id_convenio_marco' => $marcoNuevo->id_instrumento,
        ])->assertOk();

        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $especifico->id_instrumento,
            'titulo' => 'Convenio de prueba',
            'objeto' => null,
            'expediente' => 'EXP-100',
            'notas' => null,
            'version_registro' => 1,
        ]);
        $this->assertDatabaseHas('convenio', [
            'id_instrumento' => $especifico->id_instrumento,
            'tipo' => 'Colaboración',
            'alcance' => null,
            'confidencialidad' => null,
            'id_unidad_academica' => $context['unidad'],
        ]);
        $this->assertDatabaseHas('especifico', [
            'id_instrumento' => $especifico->id_instrumento,
            'id_convenio_marco' => $marcoNuevo->id_instrumento,
        ]);
        $this->assertDatabaseHas('convenio_institucion', [
            'id_instrumento' => $especifico->id_instrumento,
            'id_institucion' => $context['institucion'],
        ]);
    }

    public function test_it_rejects_subtype_and_immutable_field_changes(): void
    {
        $context = $this->authenticate();
        $marco = $this->createConvenio($context, true);
        $otroMarco = $this->createConvenio($context, true);

        $this->putJson('/api/convenios/'.$marco->id_instrumento, [
            'titulo' => 'No debe cambiar',
            'es_marco' => false,
            'id_convenio_marco' => $otroMarco->id_instrumento,
            'id_instrumento' => 999,
            'estado' => 'Firmado',
            'version_registro' => 2,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'es_marco',
                'id_convenio_marco',
                'id_instrumento',
                'estado',
                'version_registro',
            ]);

        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $marco->id_instrumento,
            'titulo' => 'Convenio de prueba',
            'estado' => 'Borrador',
            'version_registro' => 1,
        ]);
    }

    public function test_it_rejects_a_nonexistent_parent_marco(): void
    {
        $context = $this->authenticate();
        $marco = $this->createConvenio($context, true);
        $especifico = $this->createConvenio(
            $context,
            false,
            [],
            [],
            $marco->id_instrumento
        );

        $this->putJson('/api/convenios/'.$especifico->id_instrumento, [
            'id_convenio_marco' => 999999,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id_convenio_marco']);
    }

    public function test_it_rejects_updates_when_the_convenio_is_not_a_draft(): void
    {
        $context = $this->authenticate();
        $convenio = $this->createConvenio(
            $context,
            true,
            ['estado' => 'Firmado']
        );

        $this->putJson('/api/convenios/'.$convenio->id_instrumento, [
            'titulo' => 'No debe cambiar',
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'El convenio no está en fase de edición.');

        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $convenio->id_instrumento,
            'titulo' => 'Convenio de prueba',
            'estado' => 'Firmado',
        ]);
    }

    public function test_it_returns_404_for_a_missing_convenio(): void
    {
        $this->authenticate();

        $this->putJson('/api/convenios/999999', [
            'titulo' => 'Convenio inexistente',
        ])
            ->assertNotFound()
            ->assertJsonPath('message', 'Convenio no encontrado.');
    }

    public function test_update_requires_authentication(): void
    {
        $this->putJson('/api/convenios/1', [
            'titulo' => 'No autorizado',
        ])->assertUnauthorized();
    }

    public function test_database_error_rolls_back_all_update_changes(): void
    {
        $context = $this->authenticate();
        $marco = $this->createConvenio($context, true);
        $especifico = $this->createConvenio(
            $context,
            false,
            [],
            [],
            $marco->id_instrumento
        );

        try {
            app(ConvenioService::class)->update($especifico->id_instrumento, [
                'titulo' => 'Cambio que debe revertirse',
                'tipo' => 'Tipo que debe revertirse',
                'id_convenio_marco' => 999999,
                'instituciones_ids' => [$context['otraInstitucion']],
            ]);

            $this->fail('Se esperaba una violación de clave foránea.');
        } catch (QueryException) {
            $this->assertDatabaseHas('instrumento', [
                'id_instrumento' => $especifico->id_instrumento,
                'titulo' => 'Convenio de prueba',
            ]);
            $this->assertDatabaseHas('convenio', [
                'id_instrumento' => $especifico->id_instrumento,
                'tipo' => 'Colaboración',
            ]);
            $this->assertDatabaseHas('especifico', [
                'id_instrumento' => $especifico->id_instrumento,
                'id_convenio_marco' => $marco->id_instrumento,
            ]);
            $this->assertDatabaseHas('convenio_institucion', [
                'id_instrumento' => $especifico->id_instrumento,
                'id_institucion' => $context['institucion'],
            ]);
            $this->assertDatabaseMissing('convenio_institucion', [
                'id_instrumento' => $especifico->id_instrumento,
                'id_institucion' => $context['otraInstitucion'],
            ]);
        }
    }

    private function createConvenio(
        array $context,
        bool $esMarco,
        array $instrumentoAttributes = [],
        array $convenioAttributes = [],
        ?int $idConvenioMarco = null
    ): Convenio {
        $instrumento = Instrumento::query()->create(array_replace([
            'titulo' => 'Convenio de prueba',
            'objeto' => 'Objeto original',
            'estado' => 'Borrador',
            'fecha_firma' => '2025-01-01',
            'inicio' => '2025-02-01',
            'vencimiento' => '2026-02-01',
            'dias_preaviso' => 30,
            'expediente' => 'EXP-100',
            'resolucion' => 'RES-100',
            'notas' => 'Notas originales',
            'version_registro' => 1,
        ], $instrumentoAttributes));
        $convenio = Convenio::query()->create(array_replace([
            'id_instrumento' => $instrumento->id_instrumento,
            'tipo' => 'Colaboración',
            'alcance' => 'Alcance original',
            'obligaciones' => 'Obligaciones originales',
            'condiciones_recision' => 'Condiciones originales',
            'marco_legal' => 'Marco legal original',
            'condicion_renovacion' => 'Renovación original',
            'confidencialidad' => true,
            'id_unidad_academica' => $context['unidad'],
        ], $convenioAttributes));

        if ($esMarco) {
            Marco::query()->create([
                'id_instrumento' => $instrumento->id_instrumento,
            ]);
        } else {
            Especifico::query()->create([
                'id_instrumento' => $instrumento->id_instrumento,
                'id_convenio_marco' => $idConvenioMarco,
            ]);
        }

        $convenio->institucionesFirmantes()->sync([$context['institucion']]);

        return $convenio;
    }

    private function authenticate(): array
    {
        $rolId = DB::table('rol')->insertGetId([
            'nombre' => 'Actualización',
        ]);
        $institutionId = DB::table('institucion_firmante')->insertGetId([
            'razon_social' => 'Institución de actualización',
        ]);
        $otherInstitutionId = DB::table('institucion_firmante')->insertGetId([
            'razon_social' => 'Otra institución de actualización',
        ]);
        $unitId = DB::table('unidad_academica')->insertGetId([
            'sigla' => 'UA-UPD-01',
            'nombre' => 'Unidad de actualización',
            'id_institucion' => $institutionId,
        ]);
        $otherUnitId = DB::table('unidad_academica')->insertGetId([
            'sigla' => 'UA-UPD-02',
            'nombre' => 'Otra unidad de actualización',
            'id_institucion' => $institutionId,
        ]);
        $usuario = Usuario::query()->create([
            'mail' => 'update@example.com',
            'nombre' => 'Usuario de actualización',
            'password' => Hash::make('password-segura'),
            'activo' => true,
            'id_rol' => $rolId,
            'id_unidad' => $unitId,
        ]);

        $this->withToken($usuario->createToken('update-test')->plainTextToken);

        return [
            'institucion' => $institutionId,
            'otraInstitucion' => $otherInstitutionId,
            'unidad' => $unitId,
            'otraUnidad' => $otherUnitId,
            'usuario' => $usuario,
        ];
    }
}
