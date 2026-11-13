<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\InstitucionFirmante;
use App\Models\Instrumento;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InstitucionFirmanteTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_paginates_fifteen_and_preserves_filters_in_page_links(): void
    {
        $this->authenticate();

        for ($index = 1; $index <= 16; $index++) {
            $this->createInstitution([
                'razon_social' => 'Entidad Paginada '.$index,
                'tipo_institucion' => 'Universidad',
            ]);
        }

        $response = $this->getJson(
            '/api/instituciones-firmantes?buscar=Entidad%20Paginada&tipo_institucion=Universidad'
        );

        $response
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('per_page', 15)
            ->assertJsonPath('total', 16)
            ->assertJsonPath('current_page', 1);

        $nextPageQuery = parse_url($response->json('next_page_url'), PHP_URL_QUERY);
        parse_str($nextPageQuery, $pageFilters);

        $this->assertSame('Entidad Paginada', $pageFilters['buscar']);
        $this->assertSame('Universidad', $pageFilters['tipo_institucion']);
        $this->assertSame('2', $pageFilters['page']);

        $this->getJson('/api/instituciones-firmantes?'.http_build_query($pageFilters))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('current_page', 2);
    }

    public function test_index_filters_by_razon_social_cuit_tipo_and_combines_filters(): void
    {
        $this->authenticate();
        $universidad = $this->createInstitution([
            'razon_social' => 'Universidad del Centro',
            'cuit' => '30111222333',
            'tipo_institucion' => 'Universidad',
        ]);
        $fundacion = $this->createInstitution([
            'razon_social' => 'Fundación Regional',
            'cuit' => '30777888999',
            'tipo_institucion' => 'Fundación',
        ]);

        $this->getJson('/api/instituciones-firmantes?buscar=Centro')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_institucion', $universidad->id_institucion);

        $this->getJson('/api/instituciones-firmantes?buscar=77888')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_institucion', $fundacion->id_institucion);

        $this->getJson('/api/instituciones-firmantes?tipo_institucion=Universidad')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_institucion', $universidad->id_institucion);

        $this->getJson('/api/instituciones-firmantes?buscar=Centro&tipo_institucion=Universidad')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_institucion', $universidad->id_institucion);

        $this->getJson('/api/instituciones-firmantes?buscar=Centro&tipo_institucion=Fundación')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_show_returns_the_full_institution_and_404_for_missing_records(): void
    {
        $this->authenticate();
        $institution = $this->createInstitution([
            'razon_social' => 'Universidad Nacional',
            'cuit' => '30123456789',
            'domicilio_legal' => 'Calle 123',
            'tipo_institucion' => 'Universidad',
        ]);

        $this->getJson("/api/instituciones-firmantes/{$institution->id_institucion}")
            ->assertOk()
            ->assertJsonPath('data.id_institucion', $institution->id_institucion)
            ->assertJsonPath('data.razon_social', 'Universidad Nacional')
            ->assertJsonPath('data.cuit', '30123456789')
            ->assertJsonPath('data.domicilio_legal', 'Calle 123')
            ->assertJsonPath('data.tipo_institucion', 'Universidad');

        $this->getJson('/api/instituciones-firmantes/999999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Institución firmante no encontrada.');
    }

    public function test_store_creates_an_institution_and_rejects_duplicate_or_invalid_data(): void
    {
        $this->authenticate();
        $this->createInstitution(['cuit' => '30123456789']);

        $this->postJson('/api/instituciones-firmantes', [
            'razon_social' => 'Instituto de Prueba',
            'cuit' => '30987654321',
            'domicilio_legal' => 'Avenida Central 50',
            'tipo_institucion' => 'Instituto',
        ])
            ->assertCreated()
            ->assertJsonPath('data.razon_social', 'Instituto de Prueba')
            ->assertJsonPath('data.cuit', '30987654321')
            ->assertJsonPath('data.domicilio_legal', 'Avenida Central 50')
            ->assertJsonPath('data.tipo_institucion', 'Instituto');

        $this->postJson('/api/instituciones-firmantes', [
            'razon_social' => 'CUIT duplicado',
            'cuit' => '30123456789',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cuit']);

        $this->postJson('/api/instituciones-firmantes', [
            'razon_social' => str_repeat('x', 256),
            'cuit' => str_repeat('1', 14),
            'domicilio_legal' => str_repeat('d', 256),
            'tipo_institucion' => str_repeat('t', 51),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'razon_social',
                'cuit',
                'domicilio_legal',
                'tipo_institucion',
            ]);

        $this->postJson('/api/instituciones-firmantes', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['razon_social']);
    }

    public function test_update_replaces_all_fields_and_ignores_its_own_cuit_for_uniqueness(): void
    {
        $this->authenticate();
        $institution = $this->createInstitution([
            'razon_social' => 'Nombre Inicial',
            'cuit' => '30111111111',
            'domicilio_legal' => 'Domicilio Inicial',
            'tipo_institucion' => 'Universidad',
        ]);

        $this->putJson("/api/instituciones-firmantes/{$institution->id_institucion}", [
            'razon_social' => 'Nombre Actualizado',
            'cuit' => '30111111111',
            'domicilio_legal' => 'Domicilio Actualizado',
            'tipo_institucion' => 'Facultad',
        ])
            ->assertOk()
            ->assertJsonPath('data.id_institucion', $institution->id_institucion)
            ->assertJsonPath('data.razon_social', 'Nombre Actualizado')
            ->assertJsonPath('data.cuit', '30111111111')
            ->assertJsonPath('data.domicilio_legal', 'Domicilio Actualizado')
            ->assertJsonPath('data.tipo_institucion', 'Facultad');
    }

    public function test_update_preserves_omitted_fields_and_allows_clearing_nullable_fields(): void
    {
        $this->authenticate();
        $institution = $this->createInstitution([
            'razon_social' => 'Nombre Conservado',
            'cuit' => '30222222222',
            'domicilio_legal' => 'Domicilio Conservado',
            'tipo_institucion' => 'Universidad',
        ]);

        $this->putJson("/api/instituciones-firmantes/{$institution->id_institucion}", [
            'tipo_institucion' => 'Instituto',
        ])
            ->assertOk()
            ->assertJsonPath('data.razon_social', 'Nombre Conservado')
            ->assertJsonPath('data.cuit', '30222222222')
            ->assertJsonPath('data.domicilio_legal', 'Domicilio Conservado')
            ->assertJsonPath('data.tipo_institucion', 'Instituto');

        $this->putJson("/api/instituciones-firmantes/{$institution->id_institucion}", [
            'cuit' => null,
            'domicilio_legal' => null,
            'tipo_institucion' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.cuit', null)
            ->assertJsonPath('data.domicilio_legal', null)
            ->assertJsonPath('data.tipo_institucion', null);

        $this->assertDatabaseHas('institucion_firmante', [
            'id_institucion' => $institution->id_institucion,
            'razon_social' => 'Nombre Conservado',
            'cuit' => null,
            'domicilio_legal' => null,
            'tipo_institucion' => null,
        ]);
    }

    public function test_update_rejects_empty_requests_duplicate_cuits_and_missing_records(): void
    {
        $this->authenticate();
        $institution = $this->createInstitution(['cuit' => '30333333333']);
        $otherInstitution = $this->createInstitution(['cuit' => '30444444444']);

        $this->putJson("/api/instituciones-firmantes/{$institution->id_institucion}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['institucion_firmante']);

        $this->putJson("/api/instituciones-firmantes/{$institution->id_institucion}", [
            'cuit' => '30444444444',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cuit']);

        $this->putJson('/api/instituciones-firmantes/999999', [
            'razon_social' => 'No existe',
        ])->assertNotFound();

        $this->assertDatabaseHas('institucion_firmante', [
            'id_institucion' => $otherInstitution->id_institucion,
            'cuit' => '30444444444',
        ]);
    }

    public function test_destroy_deletes_only_an_unlinked_institution(): void
    {
        $this->authenticate();
        $institution = $this->createInstitution([
            'razon_social' => 'Institución eliminable',
        ]);
        $unrelatedInstitution = $this->createInstitution([
            'razon_social' => 'Institución relacionada no afectada',
        ]);
        $unrelatedUnit = $this->createUnit($unrelatedInstitution);
        $unrelatedConvenio = $this->createConvenioLinkedTo($unrelatedInstitution, $unrelatedUnit);

        $this->deleteJson("/api/instituciones-firmantes/{$institution->id_institucion}")
            ->assertOk()
            ->assertExactJson([
                'message' => 'Institución firmante eliminada correctamente.',
            ]);

        $this->assertDatabaseMissing('institucion_firmante', [
            'id_institucion' => $institution->id_institucion,
        ]);
        $this->assertDatabaseHas('institucion_firmante', [
            'id_institucion' => $unrelatedInstitution->id_institucion,
        ]);
        $this->assertDatabaseHas('unidad_academica', [
            'id_unidad_academica' => $unrelatedUnit,
        ]);
        $this->assertDatabaseHas('convenio', [
            'id_instrumento' => $unrelatedConvenio->id_instrumento,
        ]);
        $this->assertDatabaseHas('convenio_institucion', [
            'id_institucion' => $unrelatedInstitution->id_institucion,
            'id_instrumento' => $unrelatedConvenio->id_instrumento,
        ]);
    }

    public function test_destroy_rejects_institutions_linked_to_convenios_without_changing_records(): void
    {
        $this->authenticate();
        $institution = $this->createInstitution();
        $otherInstitution = $this->createInstitution();
        $otherUnit = $this->createUnit($otherInstitution);
        $convenio = $this->createConvenioLinkedTo($institution, $otherUnit);

        $this->deleteJson("/api/instituciones-firmantes/{$institution->id_institucion}")
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'No se puede eliminar una institución firmante que se encuentra vinculada a convenios o unidades académicas.'
            );

        $this->assertDatabaseHas('institucion_firmante', [
            'id_institucion' => $institution->id_institucion,
        ]);
        $this->assertDatabaseHas('convenio', [
            'id_instrumento' => $convenio->id_instrumento,
        ]);
        $this->assertDatabaseHas('convenio_institucion', [
            'id_institucion' => $institution->id_institucion,
            'id_instrumento' => $convenio->id_instrumento,
        ]);
    }

    public function test_destroy_rejects_institutions_linked_to_academic_units_without_changing_records(): void
    {
        $this->authenticate();
        $institution = $this->createInstitution();
        $unitId = $this->createUnit($institution);

        $this->deleteJson("/api/instituciones-firmantes/{$institution->id_institucion}")
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'No se puede eliminar una institución firmante que se encuentra vinculada a convenios o unidades académicas.'
            );

        $this->assertDatabaseHas('institucion_firmante', [
            'id_institucion' => $institution->id_institucion,
        ]);
        $this->assertDatabaseHas('unidad_academica', [
            'id_unidad_academica' => $unitId,
            'id_institucion' => $institution->id_institucion,
        ]);
    }

    public function test_destroy_checks_the_additional_representante_foreign_key(): void
    {
        $this->authenticate();
        $institution = $this->createInstitution();
        $personaId = DB::table('persona')->insertGetId([
            'nombre' => 'Representante',
            'apellido' => 'Prueba',
            'dni' => '12345678',
        ]);
        DB::table('representante')->insert([
            'id_persona' => $personaId,
            'id_institucion' => $institution->id_institucion,
        ]);

        $this->deleteJson("/api/instituciones-firmantes/{$institution->id_institucion}")
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'No se puede eliminar una institución firmante que tiene representantes asociados.'
            );

        $this->assertDatabaseHas('institucion_firmante', [
            'id_institucion' => $institution->id_institucion,
        ]);
        $this->assertDatabaseHas('representante', [
            'id_persona' => $personaId,
            'id_institucion' => $institution->id_institucion,
        ]);
    }

    public function test_destroy_returns_not_found_for_missing_institutions(): void
    {
        $this->authenticate();

        $this->deleteJson('/api/instituciones-firmantes/999999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Institución firmante no encontrada.');
    }

    public function test_all_institution_endpoints_require_authentication(): void
    {
        $this->getJson('/api/instituciones-firmantes')->assertUnauthorized();
        $this->getJson('/api/instituciones-firmantes/1')->assertUnauthorized();
        $this->postJson('/api/instituciones-firmantes', [])->assertUnauthorized();
        $this->putJson('/api/instituciones-firmantes/1', [])->assertUnauthorized();
        $this->deleteJson('/api/instituciones-firmantes/1')->assertUnauthorized();
    }

    private function authenticate(): Usuario
    {
        $institution = $this->createInstitution([
            'razon_social' => 'Institución para autenticación',
        ]);
        $unitId = $this->createUnit($institution);
        $roleId = DB::table('rol')->insertGetId([
            'nombre' => 'Prueba',
        ]);
        $usuario = Usuario::query()->create([
            'mail' => uniqid('institucion-', true).'@example.com',
            'nombre' => 'Usuario de prueba',
            'password' => Hash::make('password-segura'),
            'activo' => true,
            'id_rol' => $roleId,
            'id_unidad' => $unitId,
        ]);

        $this->withToken($usuario->createToken('test')->plainTextToken);

        return $usuario;
    }

    private function createInstitution(array $attributes = []): InstitucionFirmante
    {
        return InstitucionFirmante::query()->create(array_merge([
            'razon_social' => 'Institución de prueba',
            'cuit' => null,
            'domicilio_legal' => null,
            'tipo_institucion' => null,
        ], $attributes));
    }

    private function createUnit(InstitucionFirmante $institution): int
    {
        return DB::table('unidad_academica')->insertGetId([
            'sigla' => uniqid('UA'),
            'nombre' => 'Unidad de prueba',
            'id_institucion' => $institution->id_institucion,
        ]);
    }

    private function createConvenioLinkedTo(
        InstitucionFirmante $institution,
        int $unitId
    ): Convenio {
        $instrumento = Instrumento::query()->create([
            'titulo' => 'Convenio de prueba',
            'estado' => 'Borrador',
        ]);
        $convenio = Convenio::query()->create([
            'id_instrumento' => $instrumento->id_instrumento,
            'tipo' => 'Colaboración',
            'id_unidad_academica' => $unitId,
        ]);
        $convenio->institucionesFirmantes()->attach($institution->id_institucion);

        return $convenio;
    }
}
