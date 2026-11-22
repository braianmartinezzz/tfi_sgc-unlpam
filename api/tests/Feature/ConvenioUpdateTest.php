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

    public function test_it_updates_other_fields_without_revalidating_unchanged_institutions_or_losing_signature_evidence(): void
    {
        $context = $this->authenticate();
        $convenio = $this->createConvenio($context, true);
        $idPersona = $this->addInactiveSignerWithEvidence($convenio, $context['institucion']);

        $this->putJson('/api/convenios/'.$convenio->id_instrumento, [
            'titulo' => 'Título actualizado sin tocar las instituciones',
            'instituciones_ids' => [$context['institucion']],
        ])->assertOk();

        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $convenio->id_instrumento,
            'titulo' => 'Título actualizado sin tocar las instituciones',
        ]);
        $this->assertDatabaseHas('convenio_institucion', [
            'id_instrumento' => $convenio->id_instrumento,
            'id_institucion' => $context['institucion'],
        ]);
        $this->assertSignatureEvidencePreserved($convenio->id_instrumento, $idPersona);
    }

    public function test_it_rejects_institution_changes_that_orphan_an_inactive_assigned_signer_without_losing_evidence(): void
    {
        $context = $this->authenticate();
        $convenio = $this->createConvenio($context, true);
        $idPersona = $this->addInactiveSignerWithEvidence($convenio, $context['institucion']);

        $this->putJson('/api/convenios/'.$convenio->id_instrumento, [
            'titulo' => 'Este cambio debe revertirse',
            'instituciones_ids' => [$context['otraInstitucion']],
        ])
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'No se pueden quitar instituciones que justifican firmantes individuales habilitados.'
            );

        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $convenio->id_instrumento,
            'titulo' => 'Convenio de prueba',
        ]);
        $this->assertDatabaseHas('convenio_institucion', [
            'id_instrumento' => $convenio->id_instrumento,
            'id_institucion' => $context['institucion'],
        ]);
        $this->assertDatabaseMissing('convenio_institucion', [
            'id_instrumento' => $convenio->id_instrumento,
            'id_institucion' => $context['otraInstitucion'],
        ]);
        $this->assertSignatureEvidencePreserved($convenio->id_instrumento, $idPersona);
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

    public function test_it_updates_a_convenio_in_en_correccion(): void
    {
        $context = $this->authenticate();
        $convenio = $this->createConvenio(
            $context,
            true,
            ['estado' => 'EnCorreccion']
        );

        $this->putJson('/api/convenios/'.$convenio->id_instrumento, [
            'titulo' => 'Convenio corregido',
        ])
            ->assertOk()
            ->assertJsonPath('data.instrumento.estado', 'EnCorreccion')
            ->assertJsonPath('data.instrumento.titulo', 'Convenio corregido');

        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $convenio->id_instrumento,
            'estado' => 'EnCorreccion',
            'titulo' => 'Convenio corregido',
        ]);
    }

    public function test_it_rejects_updates_outside_editable_states(): void
    {
        $context = $this->authenticate();
        foreach (['Revisión', 'Aprobado', 'Firmado', 'Rescindido', 'Rechazado', 'Archivado'] as $state) {
            $convenio = $this->createConvenio(
                $context,
                true,
                ['estado' => $state]
            );

            $this->putJson('/api/convenios/'.$convenio->id_instrumento, [
                'titulo' => 'No debe cambiar',
            ])
                ->assertStatus(409)
                ->assertJsonPath(
                    'message',
                    'El convenio solo puede editarse en estado Borrador o En corrección.'
                );

            $this->assertDatabaseHas('instrumento', [
                'id_instrumento' => $convenio->id_instrumento,
                'titulo' => 'Convenio de prueba',
                'estado' => $state,
            ]);
        }
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

    public function test_management_can_only_update_its_own_unit_and_cannot_reassign_outside_it(): void
    {
        $context = $this->authenticate('Gestión');
        $convenio = $this->createConvenio($context, true);
        $otherUnitConvenio = $this->createConvenio(
            $context,
            true,
            [],
            ['id_unidad_academica' => $context['otraUnidad']]
        );

        $this->putJson('/api/convenios/'.$convenio->id_instrumento, [
            'titulo' => 'Cambio de la unidad propia',
        ])->assertOk();

        $this->putJson('/api/convenios/'.$convenio->id_instrumento, [
            'id_unidad_academica' => $context['otraUnidad'],
        ])->assertForbidden()
            ->assertJsonPath(
                'message',
                'No tiene permisos para modificar este convenio.'
            );

        $this->putJson('/api/convenios/'.$otherUnitConvenio->id_instrumento, [
            'titulo' => 'Cambio no autorizado',
        ])->assertForbidden();

        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $convenio->id_instrumento,
            'titulo' => 'Cambio de la unidad propia',
        ]);
        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $otherUnitConvenio->id_instrumento,
            'titulo' => 'Convenio de prueba',
        ]);
    }

    public function test_management_can_update_an_en_correccion_convenio_only_in_its_unit(): void
    {
        $context = $this->authenticate('Gestión');
        $convenio = $this->createConvenio(
            $context,
            true,
            ['estado' => 'EnCorreccion']
        );
        $otherUnitConvenio = $this->createConvenio(
            $context,
            true,
            ['estado' => 'EnCorreccion'],
            ['id_unidad_academica' => $context['otraUnidad']]
        );

        $this->putJson('/api/convenios/'.$convenio->id_instrumento, [
            'titulo' => 'Convenio corregido por su unidad',
        ])->assertOk();

        $this->putJson('/api/convenios/'.$otherUnitConvenio->id_instrumento, [
            'titulo' => 'Cambio no autorizado',
        ])->assertForbidden();

        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $convenio->id_instrumento,
            'estado' => 'EnCorreccion',
            'titulo' => 'Convenio corregido por su unidad',
        ]);
        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $otherUnitConvenio->id_instrumento,
            'estado' => 'EnCorreccion',
            'titulo' => 'Convenio de prueba',
        ]);
    }

    public function test_administracion_can_update_an_en_correccion_convenio_in_another_unit(): void
    {
        $context = $this->authenticate('Administración');
        $convenio = $this->createConvenio(
            $context,
            true,
            ['estado' => 'EnCorreccion'],
            ['id_unidad_academica' => $context['otraUnidad']]
        );

        $this->putJson('/api/convenios/'.$convenio->id_instrumento, [
            'titulo' => 'Convenio corregido por Administración',
        ])->assertOk();

        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $convenio->id_instrumento,
            'estado' => 'EnCorreccion',
            'titulo' => 'Convenio corregido por Administración',
        ]);
    }

    public function test_revision_and_consulta_cannot_update_convenios(): void
    {
        $context = $this->authenticate('Revisión');
        $convenio = $this->createConvenio($context, true);

        $this->putJson('/api/convenios/'.$convenio->id_instrumento, [
            'titulo' => 'Cambio no autorizado',
        ])->assertForbidden();

        $this->setRole($context['usuario'], 'Consulta');
        $this->putJson('/api/convenios/'.$convenio->id_instrumento, [
            'titulo' => 'Cambio no autorizado',
        ])->assertForbidden();
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
            ], $context['usuario']);

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

    private function addInactiveSignerWithEvidence(Convenio $convenio, int $idInstitucion): int
    {
        $idPersona = DB::table('persona')->insertGetId([
            'nombre' => 'Representante',
            'apellido' => 'Desactivado',
            'dni' => '30999111',
            'cuil' => '20309991119',
        ]);

        DB::table('representante')->insert([
            'id_persona' => $idPersona,
            'cargo' => 'Representante legal',
            'id_institucion' => $idInstitucion,
            'activo' => false,
        ]);

        DB::table('firma')->insert([
            'id_instrumento' => $convenio->id_instrumento,
            'id_persona' => $idPersona,
            'fecha_hora_firma' => '2026-02-03 12:34:56',
            'latitud' => -36.62,
            'longitud' => -64.29,
            'trazo_ruta' => 'firmas/1/1-20260203123456.png',
            'biometria_confirmada' => true,
        ]);

        return $idPersona;
    }

    private function assertSignatureEvidencePreserved(int $idInstrumento, int $idPersona): void
    {
        $this->assertDatabaseHas('firma', [
            'id_instrumento' => $idInstrumento,
            'id_persona' => $idPersona,
            'fecha_hora_firma' => '2026-02-03 12:34:56',
            'latitud' => -36.62,
            'longitud' => -64.29,
            'trazo_ruta' => 'firmas/1/1-20260203123456.png',
            'biometria_confirmada' => true,
        ]);
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

    private function authenticate(string $role = 'Administración'): array
    {
        $rolId = DB::table('rol')->insertGetId([
            'nombre' => $role,
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

    private function setRole(Usuario $usuario, string $nombre): void
    {
        $rolId = DB::table('rol')->insertGetId(['nombre' => $nombre]);
        $usuario->update(['id_rol' => $rolId]);
        $usuario->unsetRelation('rol');
        $this->app['auth']->forgetGuards();
    }
}
