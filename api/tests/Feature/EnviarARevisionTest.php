<?php

namespace Tests\Feature;

use App\Models\Adenda;
use App\Models\Convenio;
use App\Models\Documento;
use App\Models\Especifico;
use App\Models\HistorialEstado;
use App\Models\InstitucionFirmante;
use App\Models\Instrumento;
use App\Models\Marco;
use App\Models\Notificacion;
use App\Models\RegistroAuditoria;
use App\Models\Renovacion;
use App\Models\Rol;
use App\Models\Sustitucion;
use App\Models\UnidadAcademica;
use App\Models\Usuario;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EnviarARevisionTest extends TestCase
{
    use RefreshDatabase;

    private int $number = 1;

    public function test_borrador_and_en_correccion_are_sent_to_review_with_history_audit_and_notifications(): void
    {
        foreach ([
            ['estado' => 'Borrador', 'motivo' => 'Solicito revisión legal.'],
            ['estado' => 'EnCorreccion', 'motivo' => null],
        ] as $case) {
            $context = $this->createReadyConvention($case['estado'], 2);
            $this->assertSame('Gestión', $context['manager']->rol->nombre);
            $this->assertTrue(
                Gate::forUser($context['manager'])->allows(
                    'enviar-convenio-a-revision',
                    $context['convenio']
                )
            );
            $this->authenticate($context['manager']);

            $response = $this->postJson(
                "/api/convenios/{$context['convenio']->id_instrumento}/enviar-revision",
                $case['motivo'] === null ? [] : ['motivo' => $case['motivo']]
            );

            $response
                ->assertOk()
                ->assertJsonPath('message', 'Convenio enviado a revisión correctamente.')
                ->assertJsonPath('estado', 'Revisión')
                ->assertJsonPath('historial.estado_anterior', $case['estado'])
                ->assertJsonPath('historial.estado_nuevo', 'Revisión');

            $this->assertDatabaseHas('instrumento', [
                'id_instrumento' => $context['convenio']->id_instrumento,
                'estado' => 'Revisión',
            ]);
            $this->assertDatabaseHas('historial_estado', [
                'estado_anterior' => $case['estado'],
                'estado_nuevo' => 'Revisión',
                'motivo' => $case['motivo']
                    ?? 'Solicitud de revisión legal enviada por el equipo de gestión',
                'id_instrumento' => $context['convenio']->id_instrumento,
                'id_usuario' => $context['manager']->id_usuario,
            ]);
            $this->assertDatabaseHas('registro_auditoria', [
                'accion' => 'SOLICITUD_REVISION',
                'detalle' => 'Convenio enviado a revisión legal.',
                'id_instrumento' => $context['convenio']->id_instrumento,
                'id_usuario' => $context['manager']->id_usuario,
            ]);

            foreach ($context['reviewers'] as $reviewer) {
                $this->assertDatabaseHas('notificacion', [
                    'tipo' => 'REVISION_ASIGNADA',
                    'mensaje' => 'El convenio fue enviado a revisión.',
                    'id_instrumento' => $context['convenio']->id_instrumento,
                    'id_usuario' => $reviewer->id_usuario,
                ]);
            }
        }

        $this->assertSame(2, HistorialEstado::query()->count());
        $this->assertSame(2, RegistroAuditoria::query()->count());
        $this->assertSame(6, Notificacion::query()->count());
    }

    public function test_authenticated_gestion_is_limited_to_its_unit_and_administracion_is_global(): void
    {
        $context = $this->createReadyConvention();
        $otherUnit = $this->createUnit($this->createInstitution());
        $gestorOtraUnidad = $this->createUser('Gestión', $otherUnit->id_unidad_academica);
        $this->authenticate($gestorOtraUnidad);

        $this->postJson("/api/convenios/{$context['convenio']->id_instrumento}/enviar-revision")
            ->assertForbidden();

        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $context['convenio']->id_instrumento,
            'estado' => 'Borrador',
        ]);

        $administracion = $this->createUser('Administración', $otherUnit->id_unidad_academica);
        $this->authenticate($administracion);

        $this->postJson("/api/convenios/{$context['convenio']->id_instrumento}/enviar-revision")
            ->assertOk();
    }

    public function test_user_with_an_unpermitted_role_receives_forbidden(): void
    {
        $context = $this->createReadyConvention();
        $consulta = $this->createUser(
            'Consulta',
            $context['convenio']->id_unidad_academica
        );
        $this->authenticate($consulta);

        $this->postJson("/api/convenios/{$context['convenio']->id_instrumento}/enviar-revision")
            ->assertForbidden();

        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $context['convenio']->id_instrumento,
            'estado' => 'Borrador',
        ]);
    }

    public function test_missing_convenio_and_unauthenticated_requests_are_rejected(): void
    {
        $this->postJson('/api/convenios/999999/enviar-revision')
            ->assertUnauthorized();

        $context = $this->createReadyConvention();
        $this->authenticate($context['manager']);

        $this->postJson('/api/convenios/999999/enviar-revision')
            ->assertNotFound()
            ->assertJsonPath('message', 'Convenio no encontrado.');
    }

    public function test_fewer_than_two_signing_institutions_are_rejected(): void
    {
        $context = $this->createReadyConvention('Borrador', 1, institutionCount: 1);
        $this->authenticate($context['manager']);

        $this->postJson("/api/convenios/{$context['convenio']->id_instrumento}/enviar-revision")
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'El convenio debe tener al menos dos partes firmantes vinculadas antes de enviarse a revisión.'
            );

        $this->assertUnchanged($context);
    }

    public function test_missing_document_pdf_or_pdf_version_is_rejected(): void
    {
        foreach ([
            ['document' => false, 'versionPath' => null],
            ['document' => true, 'versionPath' => null],
            ['document' => true, 'versionPath' => 'documentos/contrato.docx'],
        ] as $case) {
            $context = $this->createReadyConvention(
                document: $case['document'],
                versionPath: $case['versionPath']
            );
            $this->authenticate($context['manager']);

            $this->postJson("/api/convenios/{$context['convenio']->id_instrumento}/enviar-revision")
                ->assertStatus(409)
                ->assertJsonPath(
                    'message',
                    'El convenio debe tener al menos un documento con una versión PDF.'
                );

            $this->assertUnchanged($context);
        }
    }

    public function test_missing_title_or_type_is_rejected_and_other_nullable_fields_are_not_required(): void
    {
        foreach (['titulo', 'tipo'] as $field) {
            $context = $this->createReadyConvention();
            $this->authenticate($context['manager']);

            if ($field === 'titulo') {
                $context['instrumento']->titulo = '';
                $context['instrumento']->save();
            } else {
                $context['convenio']->tipo = '';
                $context['convenio']->save();
            }

            $this->postJson("/api/convenios/{$context['convenio']->id_instrumento}/enviar-revision")
                ->assertStatus(409)
                ->assertJsonPath('message', 'La ficha técnica del convenio está incompleta.');

            $this->assertUnchanged($context);
        }

        $context = $this->createReadyConvention();
        $context['instrumento']->objeto = null;
        $context['instrumento']->fecha_firma = null;
        $context['instrumento']->inicio = null;
        $context['instrumento']->vencimiento = null;
        $context['instrumento']->dias_preaviso = null;
        $context['instrumento']->expediente = null;
        $context['instrumento']->resolucion = null;
        $context['instrumento']->notas = null;
        $context['instrumento']->save();
        $this->authenticate($context['manager']);

        $this->postJson("/api/convenios/{$context['convenio']->id_instrumento}/enviar-revision")
            ->assertOk();
    }

    public function test_convenios_with_valid_subtype_antecedents_are_accepted(): void
    {
        $context = $this->createReadyConvention();
        $antecedent = $this->createAntecedent($context['convenio']->id_unidad_academica);

        Marco::query()->create([
            'id_instrumento' => $antecedent->id_instrumento,
        ]);
        Especifico::query()->create([
            'id_instrumento' => $context['convenio']->id_instrumento,
            'id_convenio_marco' => $antecedent->id_instrumento,
        ]);
        Adenda::query()->create([
            'id_instrumento' => $context['convenio']->id_instrumento,
            'id_instrumento_modificado' => $antecedent->id_instrumento,
        ]);
        Renovacion::query()->create([
            'id_instrumento' => $context['convenio']->id_instrumento,
            'fecha' => '2026-01-01',
            'id_instrumento_renovado' => $antecedent->id_instrumento,
        ]);
        Sustitucion::query()->create([
            'id_instrumento' => $context['convenio']->id_instrumento,
            'fecha' => '2026-01-01',
            'id_instrumento_sustituido' => $antecedent->id_instrumento,
        ]);
        $this->authenticate($context['manager']);

        $this->postJson("/api/convenios/{$context['convenio']->id_instrumento}/enviar-revision")
            ->assertOk();
    }

    public function test_self_referencing_or_incompatible_subtype_antecedents_are_rejected(): void
    {
        foreach (['especifico', 'adenda', 'renovacion', 'sustitucion'] as $type) {
            $context = $this->createReadyConvention();
            $id = $context['convenio']->id_instrumento;

            if ($type === 'especifico') {
                Marco::query()->create(['id_instrumento' => $id]);
                Especifico::query()->create([
                    'id_instrumento' => $id,
                    'id_convenio_marco' => $id,
                ]);
            } elseif ($type === 'adenda') {
                Adenda::query()->create([
                    'id_instrumento' => $id,
                    'id_instrumento_modificado' => $id,
                ]);
            } elseif ($type === 'renovacion') {
                Renovacion::query()->create([
                    'id_instrumento' => $id,
                    'fecha' => '2026-01-01',
                    'id_instrumento_renovado' => $id,
                ]);
            } else {
                Sustitucion::query()->create([
                    'id_instrumento' => $id,
                    'fecha' => '2026-01-01',
                    'id_instrumento_sustituido' => $id,
                ]);
            }

            $this->authenticate($context['manager']);

            $this->postJson("/api/convenios/{$id}/enviar-revision")
                ->assertStatus(409);

            $this->assertUnchanged($context);
        }
    }

    public function test_states_other_than_borrador_and_en_correccion_are_rejected(): void
    {
        foreach (['Revisión', 'Aprobado'] as $state) {
            $context = $this->createReadyConvention($state);
            $this->authenticate($context['manager']);

            $this->postJson("/api/convenios/{$context['convenio']->id_instrumento}/enviar-revision")
                ->assertStatus(409)
                ->assertJsonPath(
                    'message',
                    'El convenio no se encuentra en un estado que admita el envío a revisión.'
                );

            $this->assertUnchanged($context);
        }
    }

    public function test_request_validation_and_missing_review_recipients_do_not_write_partial_data(): void
    {
        $context = $this->createReadyConvention(reviewers: 0);
        $this->authenticate($context['manager']);

        $this->postJson(
            "/api/convenios/{$context['convenio']->id_instrumento}/enviar-revision",
            ['motivo' => ['no', 'es', 'texto']]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motivo']);

        $this->postJson("/api/convenios/{$context['convenio']->id_instrumento}/enviar-revision")
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'No hay usuarios activos del rol Revisión para notificar.'
            );

        $this->assertUnchanged($context);
    }

    public function test_notification_failure_rolls_back_state_history_and_audit(): void
    {
        $context = $this->createReadyConvention();
        $this->authenticate($context['manager']);
        DB::unprepared(
            'CREATE TRIGGER fail_revision_notification BEFORE INSERT ON notificacion '
            ."BEGIN SELECT RAISE(FAIL, 'notification write failed'); END"
        );

        $this->postJson("/api/convenios/{$context['convenio']->id_instrumento}/enviar-revision")
            ->assertStatus(500);

        $this->assertUnchanged($context);
    }

    private function createReadyConvention(
        string $estado = 'Borrador',
        int $reviewers = 1,
        int $institutionCount = 2,
        bool $document = true,
        ?string $versionPath = 'documentos/contrato.pdf'
    ): array {
        $institution = $this->createInstitution();
        $unit = $this->createUnit($institution);
        $manager = $this->createUser('Gestión', $unit->id_unidad_academica);

        Rol::query()->firstOrCreate(['nombre' => 'Revisión']);
        $reviewerUsers = [];

        for ($index = 0; $index < $reviewers; $index++) {
            $reviewerUsers[] = $this->createUser(
                'Revisión',
                $unit->id_unidad_academica
            );
        }

        $instrumento = Instrumento::query()->create([
            'titulo' => 'Convenio para revisión',
            'estado' => $estado,
        ]);
        $convenio = Convenio::query()->create([
            'id_instrumento' => $instrumento->id_instrumento,
            'tipo' => 'Colaboración',
            'id_unidad_academica' => $unit->id_unidad_academica,
        ]);

        $institutions = [$institution];
        if ($institutionCount > 1) {
            $institutions[] = $this->createInstitution();
        }
        $convenio->institucionesFirmantes()->attach(
            array_map(
                fn (InstitucionFirmante $item) => $item->id_institucion,
                array_slice($institutions, 0, $institutionCount)
            )
        );

        if ($document) {
            $documento = Documento::query()->create([
                'tipo' => 'Anexo',
                'titulo' => 'Documento de prueba',
                'id_instrumento' => $instrumento->id_instrumento,
            ]);

            if ($versionPath !== null) {
                Version::query()->create([
                    'numero' => 1,
                    'nombre_archivo' => 'contrato.pdf',
                    'tamanio_bytes' => 10,
                    'hash_sha256' => str_repeat('a', 64),
                    'ruta_interna' => $versionPath,
                    'fecha_carga' => now(),
                    'id_documento' => $documento->id_documento,
                    'id_usuario' => $manager->id_usuario,
                ]);
            }
        }

        return [
            'instrumento' => $instrumento,
            'convenio' => $convenio,
            'manager' => $manager,
            'reviewers' => $reviewerUsers,
        ];
    }

    private function createAntecedent(int $unitId): Instrumento
    {
        $instrumento = Instrumento::query()->create([
            'titulo' => 'Convenio antecedente',
            'estado' => 'Revisión',
        ]);
        Convenio::query()->create([
            'id_instrumento' => $instrumento->id_instrumento,
            'tipo' => 'Colaboración',
            'id_unidad_academica' => $unitId,
        ]);

        return $instrumento;
    }

    private function createInstitution(): InstitucionFirmante
    {
        return InstitucionFirmante::query()->create([
            'razon_social' => 'Institución '.$this->number++,
        ]);
    }

    private function createUnit(InstitucionFirmante $institution): UnidadAcademica
    {
        return UnidadAcademica::query()->create([
            'sigla' => 'UA-'.$this->number,
            'nombre' => 'Unidad '.$this->number++,
            'id_institucion' => $institution->id_institucion,
        ]);
    }

    private function createUser(string $roleName, int $unitId): Usuario
    {
        $rol = Rol::query()->firstOrCreate(['nombre' => $roleName]);

        return Usuario::query()->create([
            'mail' => 'revision-'.$this->number++.'@example.com',
            'nombre' => 'Usuario de prueba',
            'password' => Hash::make('password-segura'),
            'activo' => true,
            'id_rol' => $rol->id_rol,
            'id_unidad' => $unitId,
        ]);
    }

    private function authenticate(Usuario $usuario): void
    {
        Auth::forgetGuards();
        $this->withToken($usuario->createToken('test')->plainTextToken);
    }

    private function assertUnchanged(array $context): void
    {
        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $context['convenio']->id_instrumento,
            'estado' => $context['instrumento']->estado,
        ]);
        $this->assertSame(0, HistorialEstado::query()->count());
        $this->assertSame(0, RegistroAuditoria::query()->count());
        $this->assertSame(0, Notificacion::query()->count());
    }
}
