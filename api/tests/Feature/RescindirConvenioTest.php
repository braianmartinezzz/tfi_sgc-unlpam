<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Docente;
use App\Models\Documento;
use App\Models\Especifico;
use App\Models\Estudiante;
use App\Models\HistorialEstado;
use App\Models\InstitucionFirmante;
use App\Models\Instrumento;
use App\Models\Marco;
use App\Models\Notificacion;
use App\Models\Pasantia;
use App\Models\Persona;
use App\Models\RegistroAuditoria;
use App\Models\Representante;
use App\Models\Rol;
use App\Models\UnidadAcademica;
use App\Models\Usuario;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RescindirConvenioTest extends TestCase
{
    use RefreshDatabase;

    private int $number = 1;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
    }

    public function test_rescision_registra_estado_historial_auditoria_notificaciones_y_resolucion(): void
    {
        $context = $this->createConvenio(
            'Firmado',
            now()->toDateString()
        );
        $context = array_merge($context, $this->createSignedDocument($context));
        $relatedRecords = $this->createRelatedRecords($context);
        $destinatarioConsulta = $this->createUser(
            'Consulta',
            $context['unidad']->id_unidad_academica
        );
        $usuarioInactivo = $this->createUser(
            'Revisión',
            $context['unidad']->id_unidad_academica,
            false
        );
        $otherUnit = $this->createUnit($this->createInstitution());
        $usuarioOtraUnidad = $this->createUser(
            'Gestión',
            $otherUnit->id_unidad_academica
        );
        $motivo = 'Incumplimiento grave de las obligaciones pactadas.';
        $contenido = '%PDF-1.4 resolución de rescisión';

        $response = $this->actingAs($context['manager'], 'sanctum')->post(
            "/api/convenios/{$context['convenio']->id_instrumento}/rescindir",
            [
                'motivo' => $motivo,
                'documento_resolucion' => UploadedFile::fake()->createWithContent(
                    'resolucion.pdf',
                    $contenido
                ),
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Convenio rescindido correctamente.')
            ->assertJsonPath('data.estado', 'Rescindido')
            ->assertJsonPath('data.id_instrumento', $context['convenio']->id_instrumento);

        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $context['convenio']->id_instrumento,
            'estado' => 'Rescindido',
            'fecha_firma' => null,
        ]);
        $this->assertSame(
            now()->toDateString(),
            $context['instrumento']->fresh()->vencimiento->format('Y-m-d')
        );
        $this->assertDatabaseHas('historial_estado', [
            'estado_anterior' => 'Firmado',
            'estado_nuevo' => 'Rescindido',
            'motivo' => $motivo,
            'id_instrumento' => $context['convenio']->id_instrumento,
            'id_usuario' => $context['manager']->id_usuario,
        ]);

        $auditoria = RegistroAuditoria::query()
            ->where('accion', 'RESCISION_ANTICIPADA')
            ->sole();
        $this->assertSame(
            $context['convenio']->id_instrumento,
            $auditoria->id_instrumento
        );
        $this->assertSame($context['manager']->id_usuario, $auditoria->id_usuario);
        $this->assertStringContainsString(
            'Convenio #'.$context['convenio']->id_instrumento,
            $auditoria->detalle
        );
        $this->assertStringContainsString($motivo, $auditoria->detalle);
        $this->assertStringContainsString('Fecha y hora:', $auditoria->detalle);

        $notificaciones = Notificacion::query()
            ->where('id_instrumento', $context['convenio']->id_instrumento)
            ->orderBy('id_usuario')
            ->get();
        $this->assertSame(2, $notificaciones->count());
        $this->assertSame(
            [$context['manager']->id_usuario, $destinatarioConsulta->id_usuario],
            $notificaciones->pluck('id_usuario')->all()
        );
        $this->assertFalse(
            $notificaciones->contains('id_usuario', $usuarioInactivo->id_usuario)
        );
        $this->assertFalse(
            $notificaciones->contains('id_usuario', $usuarioOtraUnidad->id_usuario)
        );
        $this->assertSame(
            ['CONVENIO_CANCELADO'],
            $notificaciones->pluck('tipo')->unique()->values()->all()
        );

        $resolucion = Documento::query()
            ->where('id_instrumento', $context['convenio']->id_instrumento)
            ->where('tipo', 'resolucion')
            ->sole();
        $versionResolucion = $resolucion->ultimaVersion;
        $this->assertSame('Resolución de rescisión', $resolucion->titulo);
        $this->assertFalse($resolucion->es_documento_firmado);
        $this->assertSame('resolucion.pdf', $versionResolucion->nombre_archivo);
        $this->assertSame(strlen($contenido), $versionResolucion->tamanio_bytes);
        $this->assertSame(hash('sha256', $contenido), $versionResolucion->hash_sha256);
        $this->assertSame($context['manager']->id_usuario, $versionResolucion->id_usuario);
        Storage::disk('private')->assertExists($versionResolucion->ruta_interna);

        $this->assertDatabaseHas('documento', [
            'id_documento' => $context['signedDocument']->id_documento,
            'es_documento_firmado' => true,
        ]);
        $this->assertDatabaseHas('version', [
            'id_version' => $context['signedVersion']->id_version,
            'ruta_interna' => $context['signedVersion']->ruta_interna,
        ]);
        Storage::disk('private')->assertExists($context['signedVersion']->ruta_interna);
        $this->assertDatabaseHas('firma', $relatedRecords['firma']);
        $this->assertDatabaseHas('pasantia', $relatedRecords['pasantia']);
        $this->assertSame(2, Documento::query()
            ->where('id_instrumento', $context['convenio']->id_instrumento)
            ->count());
        $this->assertSame(2, Version::query()
            ->whereIn('id_documento', [
                $context['signedDocument']->id_documento,
                $resolucion->id_documento,
            ])
            ->count());
    }

    public function test_rescision_sin_documento_resolucion_es_permitida(): void
    {
        $context = $this->createConvenio();
        $this->actingAs($context['manager'], 'sanctum')->postJson(
            "/api/convenios/{$context['convenio']->id_instrumento}/rescindir",
            ['motivo' => 'Causa legal suficientemente explicada.']
        )->assertOk();

        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $context['convenio']->id_instrumento,
            'estado' => 'Rescindido',
        ]);
        $this->assertSame(0, Documento::query()
            ->where('id_instrumento', $context['convenio']->id_instrumento)
            ->count());
    }

    public function test_rescision_rechaza_convenios_vencidos(): void
    {
        $context = $this->createConvenio(
            'Firmado',
            now()->subDay()->toDateString()
        );
        $this->actingAs($context['manager'], 'sanctum')->postJson(
            "/api/convenios/{$context['convenio']->id_instrumento}/rescindir",
            ['motivo' => 'Causa legal suficientemente explicada.']
        )
            ->assertStatus(409)
            ->assertJsonPath('message', 'No se puede rescindir un convenio vencido.');

        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $context['convenio']->id_instrumento,
            'estado' => 'Firmado',
        ]);
        $this->assertSame(0, HistorialEstado::query()->count());
        $this->assertSame(0, RegistroAuditoria::query()->count());
        $this->assertSame(0, Notificacion::query()->count());
    }

    public function test_solo_se_permite_rescindir_desde_estado_firmado(): void
    {
        foreach ([
            'Borrador',
            'EnCorreccion',
            'Revisión',
            'Aprobado',
            'Rescindido',
            'Archivado',
        ] as $state) {
            $context = $this->createConvenio($state);

            $this->actingAs($context['manager'], 'sanctum')->postJson(
                "/api/convenios/{$context['convenio']->id_instrumento}/rescindir",
                ['motivo' => 'Causa legal suficientemente explicada.']
            )
                ->assertStatus(409)
                ->assertJsonPath(
                    'message',
                    'El convenio no se encuentra en estado vigente para ser rescindido.'
                );

            $this->assertDatabaseHas('instrumento', [
                'id_instrumento' => $context['convenio']->id_instrumento,
                'estado' => $state,
            ]);
        }
    }

    public function test_motivo_es_obligatorio_y_debe_tener_al_menos_diez_caracteres(): void
    {
        $context = $this->createConvenio();
        $url = "/api/convenios/{$context['convenio']->id_instrumento}/rescindir";

        $this->actingAs($context['manager'], 'sanctum')->postJson($url, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motivo']);
        $this->postJson($url, ['motivo' => 'corto'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motivo']);

        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $context['convenio']->id_instrumento,
            'estado' => 'Firmado',
        ]);
    }

    public function test_documento_resolucion_rechaza_archivos_no_pdf_y_mayores_a_20_mb(): void
    {
        $context = $this->createConvenio();
        $url = "/api/convenios/{$context['convenio']->id_instrumento}/rescindir";

        $this->actingAs($context['manager'], 'sanctum')->post($url, [
            'motivo' => 'Causa legal suficientemente explicada.',
            'documento_resolucion' => UploadedFile::fake()->create(
                'resolucion.txt',
                5,
                'text/plain'
            ),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['documento_resolucion']);

        $this->post($url, [
            'motivo' => 'Causa legal suficientemente explicada.',
            'documento_resolucion' => UploadedFile::fake()->create(
                'resolucion.pdf',
                20481,
                'application/pdf'
            ),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['documento_resolucion']);

        $this->assertSame(0, Documento::query()->count());
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_endpoint_requiere_autenticacion_y_retorna_404_si_no_existe_el_convenio(): void
    {
        $this->postJson('/api/convenios/999999/rescindir', [
            'motivo' => 'Causa legal suficientemente explicada.',
        ])->assertUnauthorized();

        $context = $this->createConvenio();
        $this->actingAs($context['manager'], 'sanctum')->postJson(
            '/api/convenios/999999/rescindir',
            ['motivo' => 'Causa legal suficientemente explicada.']
        )->assertNotFound();
    }

    public function test_gestion_de_otra_unidad_recibe_403_y_administracion_conserva_alcance_global(): void
    {
        $context = $this->createConvenio();
        $otherUnit = $this->createUnit($this->createInstitution());
        $otherManager = $this->createUser(
            'Gestión',
            $otherUnit->id_unidad_academica
        );
        $url = "/api/convenios/{$context['convenio']->id_instrumento}/rescindir";

        $this->actingAs($otherManager, 'sanctum')->postJson($url, [
            'motivo' => 'Causa legal suficientemente explicada.',
        ])->assertForbidden();

        $administracion = $this->createUser(
            'Administración',
            $otherUnit->id_unidad_academica
        );
        $this->actingAs($administracion, 'sanctum')->postJson($url, [
            'motivo' => 'Causa legal suficientemente explicada.',
        ])->assertOk();
    }

    public function test_fallo_al_generar_notificacion_revierte_cambios_y_elimina_el_archivo_nuevo(): void
    {
        $context = $this->createConvenio();
        $this->actingAs($context['manager'], 'sanctum');
        DB::unprepared(
            'CREATE TRIGGER fail_rescision_notification BEFORE INSERT ON notificacion '
            ."WHEN NEW.tipo = 'CONVENIO_CANCELADO' "
            ."BEGIN SELECT RAISE(FAIL, 'notification write failed'); END"
        );

        $this->post(
            "/api/convenios/{$context['convenio']->id_instrumento}/rescindir",
            [
                'motivo' => 'Causa legal suficientemente explicada.',
                'documento_resolucion' => UploadedFile::fake()->createWithContent(
                    'resolucion.pdf',
                    '%PDF-1.4 fallo de notificacion'
                ),
            ]
        )->assertStatus(500);

        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $context['convenio']->id_instrumento,
            'estado' => 'Firmado',
        ]);
        $this->assertSame(0, Documento::query()->count());
        $this->assertSame(0, Version::query()->count());
        $this->assertSame(0, HistorialEstado::query()->count());
        $this->assertSame(0, RegistroAuditoria::query()->count());
        $this->assertSame(0, Notificacion::query()->count());
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_convenio_rescindido_no_se_puede_editar_ni_reenviar_a_revision(): void
    {
        $context = $this->createConvenio();
        $url = "/api/convenios/{$context['convenio']->id_instrumento}";
        $this->actingAs($context['manager'], 'sanctum')->postJson(
            "{$url}/rescindir",
            ['motivo' => 'Causa legal suficientemente explicada.']
        )->assertOk();

        $this->putJson($url, ['titulo' => 'Intento de modificación'])
            ->assertStatus(409);
        $this->postJson("{$url}/enviar-revision")
            ->assertStatus(409);

        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $context['convenio']->id_instrumento,
            'estado' => 'Rescindido',
            'titulo' => $context['instrumento']->titulo,
        ]);
    }

    private function createConvenio(
        string $state = 'Firmado',
        ?string $vencimiento = null
    ): array {
        $institution = $this->createInstitution();
        $unit = $this->createUnit($institution);
        $manager = $this->createUser('Gestión', $unit->id_unidad_academica);
        $instrumento = Instrumento::query()->create([
            'titulo' => 'Convenio para rescindir',
            'estado' => $state,
            'vencimiento' => $vencimiento,
        ]);
        $convenio = Convenio::query()->create([
            'id_instrumento' => $instrumento->id_instrumento,
            'tipo' => 'Colaboración',
            'id_unidad_academica' => $unit->id_unidad_academica,
        ]);

        return [
            'institution' => $institution,
            'unidad' => $unit,
            'manager' => $manager,
            'instrumento' => $instrumento,
            'convenio' => $convenio,
        ];
    }

    private function createSignedDocument(array $context): array
    {
        $content = '%PDF-1.4 convenio firmado histórico';
        $path = 'documentos/convenio-firmado-'.$this->number++.'.pdf';
        Storage::disk('private')->put($path, $content);
        $documento = Documento::query()->create([
            'tipo' => 'Convenio',
            'titulo' => $context['instrumento']->titulo,
            'id_instrumento' => $context['convenio']->id_instrumento,
            'es_documento_firmado' => true,
        ]);
        $version = $documento->versiones()->create([
            'numero' => 1,
            'nombre_archivo' => 'convenio-firmado.pdf',
            'tamanio_bytes' => strlen($content),
            'hash_sha256' => hash('sha256', $content),
            'ruta_interna' => $path,
            'fecha_carga' => now(),
            'id_usuario' => $context['manager']->id_usuario,
        ]);

        return [
            'signedDocument' => $documento,
            'signedVersion' => $version,
        ];
    }

    private function createRelatedRecords(array $context): array
    {
        $marcoInstrumento = Instrumento::query()->create([
            'titulo' => 'Convenio Marco',
            'estado' => 'Firmado',
        ]);
        $marcoConvenio = Convenio::query()->create([
            'id_instrumento' => $marcoInstrumento->id_instrumento,
            'tipo' => 'Colaboración',
            'id_unidad_academica' => $context['unidad']->id_unidad_academica,
        ]);
        Marco::query()->create([
            'id_instrumento' => $marcoConvenio->id_instrumento,
        ]);
        Especifico::query()->create([
            'id_instrumento' => $context['convenio']->id_instrumento,
            'id_convenio_marco' => $marcoConvenio->id_instrumento,
        ]);

        $student = Persona::query()->create([
            'nombre' => 'Ana',
            'apellido' => 'Estudiante',
            'dni' => '81000001',
        ]);
        $teacher = Persona::query()->create([
            'nombre' => 'Tomás',
            'apellido' => 'Docente',
            'dni' => '81000002',
        ]);
        $representative = Persona::query()->create([
            'nombre' => 'Rosa',
            'apellido' => 'Representante',
            'dni' => '81000003',
        ]);
        Estudiante::query()->create([
            'id_persona' => $student->id_persona,
            'carrera' => 'Ingeniería',
        ]);
        Docente::query()->create([
            'id_persona' => $teacher->id_persona,
        ]);
        Representante::query()->create([
            'id_persona' => $representative->id_persona,
            'cargo' => 'Tutora',
            'id_institucion' => $context['institution']->id_institucion,
        ]);

        $pasantia = Pasantia::query()->create([
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-12-31',
            'carga_horaria' => 20,
            'asignacion_estimulo' => 1000,
            'id_convenio_especifico' => $context['convenio']->id_instrumento,
            'id_estudiante' => $student->id_persona,
            'id_docente_guia' => $teacher->id_persona,
            'id_tutor_laboral' => $representative->id_persona,
        ]);

        $firma = [
            'id_instrumento' => $context['convenio']->id_instrumento,
            'id_persona' => $representative->id_persona,
        ];
        DB::table('firma')->insert($firma);

        return [
            'firma' => $firma,
            'pasantia' => [
                'id_pasantia' => $pasantia->id_pasantia,
                'id_convenio_especifico' => $context['convenio']->id_instrumento,
            ],
        ];
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
            'sigla' => 'RES-'.$this->number,
            'nombre' => 'Unidad '.$this->number++,
            'id_institucion' => $institution->id_institucion,
        ]);
    }

    private function createUser(
        string $roleName,
        int $unitId,
        bool $active = true
    ): Usuario {
        $role = Rol::query()->firstOrCreate(['nombre' => $roleName]);

        return Usuario::query()->create([
            'mail' => 'rescision-'.$this->number++.'@example.com',
            'nombre' => 'Usuario de rescisión',
            'password' => Hash::make('password-segura'),
            'activo' => $active,
            'id_rol' => $role->id_rol,
            'id_unidad' => $unitId,
        ]);
    }
}
