<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Documento;
use App\Models\HistorialEstado;
use App\Models\InstitucionFirmante;
use App\Models\Instrumento;
use App\Models\Notificacion;
use App\Models\RegistroAuditoria;
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

class RegistrarFirmaTest extends TestCase
{
    use RefreshDatabase;

    private int $number = 1;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
    }

    public function test_registra_firma_estado_fecha_historial_y_auditoria_con_pdf_nuevo(): void
    {
        $context = $this->createConvenio();
        $contenido = '%PDF-1.4 documento firmado de prueba';
        $fechaFirma = now()->toDateString();

        $response = $this->actingAs($context['manager'], 'sanctum')->post(
            "/api/convenios/{$context['convenio']->id_instrumento}/registrar-firma",
            [
                'fecha_firma' => $fechaFirma,
                'archivo_pdf' => UploadedFile::fake()->createWithContent(
                    'convenio-firmado.pdf',
                    $contenido
                ),
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Firma registrada correctamente.')
            ->assertJsonPath('data.id_instrumento', $context['convenio']->id_instrumento)
            ->assertJsonPath('data.estado', 'Firmado')
            ->assertJsonPath('data.fecha_firma', $fechaFirma.'T00:00:00.000000Z');

        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $context['convenio']->id_instrumento,
            'estado' => 'Firmado',
        ]);
        $this->assertSame(
            $fechaFirma,
            $context['instrumento']->fresh()->fecha_firma->format('Y-m-d')
        );
        $this->assertDatabaseHas('historial_estado', [
            'estado_anterior' => 'Aprobado',
            'estado_nuevo' => 'Firmado',
            'motivo' => 'Firma de convenio registrada y documento final adjuntado.',
            'id_instrumento' => $context['convenio']->id_instrumento,
            'id_usuario' => $context['manager']->id_usuario,
        ]);
        $this->assertDatabaseHas('registro_auditoria', [
            'accion' => 'REGISTRO_FIRMA',
            'detalle' => 'Firma registrada para el convenio #'
                .$context['convenio']->id_instrumento,
            'id_instrumento' => $context['convenio']->id_instrumento,
            'id_usuario' => $context['manager']->id_usuario,
        ]);

        $documento = Documento::query()
            ->where('id_instrumento', $context['convenio']->id_instrumento)
            ->where('es_documento_firmado', true)
            ->sole();
        $version = $documento->ultimaVersion;
        $this->assertSame('Convenio', $documento->tipo);
        $this->assertSame($context['instrumento']->titulo, $documento->titulo);
        $this->assertSame(1, $version->numero);
        $this->assertSame(hash('sha256', $contenido), $version->hash_sha256);
        $this->assertDatabaseCount('firma', 0);
        $this->assertDatabaseCount('notificacion', 0);
        Storage::disk('private')->assertExists($version->ruta_interna);
        $response->assertJsonMissingPath('data.ruta_interna');
    }

    public function test_sin_pdf_nuevo_requiere_un_documento_firmado_marcado_y_disponible(): void
    {
        $context = $this->createConvenio();
        $documento = $this->createDocumentoFirmado($context);
        $this->actingAs($context['manager'], 'sanctum')->postJson(
            "/api/convenios/{$context['convenio']->id_instrumento}/registrar-firma",
            ['fecha_firma' => now()->toDateString()]
        )
            ->assertOk()
            ->assertJsonPath('data.estado', 'Firmado');

        $this->assertSame(1, Documento::query()
            ->where('id_instrumento', $context['convenio']->id_instrumento)
            ->where('es_documento_firmado', true)
            ->count());
        $this->assertSame(1, $documento->versiones()->count());
        $this->assertDatabaseCount('firma', 0);
        $this->assertDatabaseCount('notificacion', 0);
    }

    public function test_sin_pdf_rechaza_un_pdf_ordinario_no_marcado_como_firmado(): void
    {
        $context = $this->createConvenio();
        $path = 'documentos/ordinario.pdf';
        Storage::disk('private')->put($path, '%PDF-1.4 documento ordinario');
        $documento = Documento::query()->create([
            'tipo' => 'Convenio',
            'titulo' => 'Documento ordinario',
            'id_instrumento' => $context['convenio']->id_instrumento,
        ]);
        $documento->versiones()->create([
            'numero' => 1,
            'nombre_archivo' => 'ordinario.pdf',
            'tamanio_bytes' => 28,
            'hash_sha256' => hash('sha256', '%PDF-1.4 documento ordinario'),
            'ruta_interna' => $path,
            'fecha_carga' => now(),
            'id_usuario' => $context['manager']->id_usuario,
        ]);

        $this->assertFalse($documento->fresh()->es_documento_firmado);
        $this->actingAs($context['manager'], 'sanctum')->postJson(
            "/api/convenios/{$context['convenio']->id_instrumento}/registrar-firma",
            ['fecha_firma' => now()->toDateString()]
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'Debe adjuntar el PDF firmado o existir previamente un documento final firmado con una versión PDF disponible.'
            );

        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $context['convenio']->id_instrumento,
            'estado' => 'Aprobado',
            'fecha_firma' => null,
        ]);
        $this->assertSame(0, HistorialEstado::query()->count());
        $this->assertSame(0, RegistroAuditoria::query()->count());
    }

    public function test_subir_un_pdf_a_documento_firmado_existente_agrega_version_y_conserva_la_anterior(): void
    {
        $context = $this->createConvenio();
        $documento = $this->createDocumentoFirmado($context);
        $versionAnterior = $documento->ultimaVersion;
        $contenido = '%PDF-1.4 version final reemplazada';

        $response = $this->actingAs($context['manager'], 'sanctum')->post(
            "/api/convenios/{$context['convenio']->id_instrumento}/registrar-firma",
            [
                'fecha_firma' => now()->toDateString(),
                'archivo_pdf' => UploadedFile::fake()->createWithContent(
                    'convenio-firmado-v2.pdf',
                    $contenido
                ),
            ]
        );

        $response->assertOk();
        $documento->refresh();
        $this->assertTrue($documento->es_documento_firmado);
        $this->assertSame(1, Documento::query()
            ->where('id_instrumento', $context['convenio']->id_instrumento)
            ->where('es_documento_firmado', true)
            ->count());
        $this->assertSame(2, $documento->versiones()->count());
        $this->assertSame(1, $versionAnterior->fresh()->numero);
        $this->assertDatabaseHas('version', [
            'id_documento' => $documento->id_documento,
            'numero' => 2,
            'hash_sha256' => hash('sha256', $contenido),
        ]);
        Storage::disk('private')->assertExists($versionAnterior->ruta_interna);
        Storage::disk('private')->assertExists(
            $documento->ultimaVersion->ruta_interna
        );
    }

    public function test_rechaza_estados_distintos_de_aprobado(): void
    {
        foreach (['Borrador', 'EnCorreccion', 'Revisión', 'Firmado'] as $state) {
            $context = $this->createConvenio($state);

            $this->actingAs($context['manager'], 'sanctum')->postJson(
                "/api/convenios/{$context['convenio']->id_instrumento}/registrar-firma",
                ['fecha_firma' => now()->toDateString()]
            )
                ->assertStatus(409)
                ->assertJsonPath(
                    'message',
                    'El convenio debe estar en estado Aprobado para poder registrar la firma.'
                );

            $this->assertDatabaseHas('instrumento', [
                'id_instrumento' => $context['convenio']->id_instrumento,
                'estado' => $state,
                'fecha_firma' => null,
            ]);
        }
    }

    public function test_fecha_futura_y_coordenadas_son_rechazadas(): void
    {
        $context = $this->createConvenio();
        $url = "/api/convenios/{$context['convenio']->id_instrumento}/registrar-firma";

        $this->actingAs($context['manager'], 'sanctum')->postJson($url, [
            'fecha_firma' => now()->addDay()->toDateString(),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha_firma']);

        $this->post($url, [
            'fecha_firma' => now()->toDateString(),
            'archivo_pdf' => UploadedFile::fake()->create(
                'archivo-grande.pdf',
                20481,
                'application/pdf'
            ),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['archivo_pdf']);

        $this->postJson($url, [
            'fecha_firma' => now()->toDateString(),
            'latitud' => -36.6,
            'longitud' => -64.3,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitud', 'longitud']);

        $this->assertSame(0, RegistroAuditoria::query()->count());
    }

    public function test_ruta_requiere_autenticacion_y_retorna_404_si_no_existe_el_convenio(): void
    {
        $this->postJson('/api/convenios/999999/registrar-firma', [
            'fecha_firma' => now()->toDateString(),
        ])->assertUnauthorized();

        $context = $this->createConvenio();
        $this->actingAs($context['manager'], 'sanctum')->postJson(
            '/api/convenios/999999/registrar-firma',
            ['fecha_firma' => now()->toDateString()]
        )->assertNotFound();
    }

    public function test_gestion_de_otra_unidad_recibe_403_y_administracion_conserva_acceso_global(): void
    {
        $context = $this->createConvenio();
        $otherUnit = $this->createUnit($this->createInstitution());
        $otherManager = $this->createUser(
            'Gestión',
            $otherUnit->id_unidad_academica
        );
        $url = "/api/convenios/{$context['convenio']->id_instrumento}/registrar-firma";

        $this->actingAs($otherManager, 'sanctum')->postJson($url, [
            'fecha_firma' => now()->toDateString(),
        ])->assertForbidden();

        $administracion = $this->createUser(
            'Administración',
            $otherUnit->id_unidad_academica
        );
        $this->actingAs($administracion, 'sanctum')->post(
            $url,
            [
                'fecha_firma' => now()->toDateString(),
                'archivo_pdf' => UploadedFile::fake()->createWithContent(
                    'convenio-firmado.pdf',
                    '%PDF-1.4 firma global'
                ),
            ]
        )->assertOk();
    }

    public function test_fallo_de_auditoria_revierte_base_de_datos_y_elimina_el_archivo_nuevo(): void
    {
        $context = $this->createConvenio();
        $this->actingAs($context['manager'], 'sanctum');
        DB::unprepared(
            'CREATE TRIGGER fail_signature_audit BEFORE INSERT ON registro_auditoria '
            ."WHEN NEW.accion = 'REGISTRO_FIRMA' "
            ."BEGIN SELECT RAISE(FAIL, 'audit write failed'); END"
        );

        $this->post(
            "/api/convenios/{$context['convenio']->id_instrumento}/registrar-firma",
            [
                'fecha_firma' => now()->toDateString(),
                'archivo_pdf' => UploadedFile::fake()->createWithContent(
                    'convenio-firmado.pdf',
                    '%PDF-1.4 rollback de archivo'
                ),
            ]
        )->assertStatus(500);

        $this->assertDatabaseHas('instrumento', [
            'id_instrumento' => $context['convenio']->id_instrumento,
            'estado' => 'Aprobado',
            'fecha_firma' => null,
        ]);
        $this->assertSame(0, Documento::query()->count());
        $this->assertSame(0, Version::query()->count());
        $this->assertSame(0, HistorialEstado::query()->count());
        $this->assertSame(0, RegistroAuditoria::query()->count());
        $this->assertSame(0, Notificacion::query()->count());
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    private function createConvenio(string $state = 'Aprobado'): array
    {
        $institution = $this->createInstitution();
        $unit = $this->createUnit($institution);
        $manager = $this->createUser('Gestión', $unit->id_unidad_academica);
        $instrumento = Instrumento::query()->create([
            'titulo' => 'Convenio de prueba',
            'estado' => $state,
        ]);
        $convenio = Convenio::query()->create([
            'id_instrumento' => $instrumento->id_instrumento,
            'tipo' => 'Colaboración',
            'id_unidad_academica' => $unit->id_unidad_academica,
        ]);

        return [
            'instrumento' => $instrumento,
            'convenio' => $convenio,
            'manager' => $manager,
        ];
    }

    private function createDocumentoFirmado(array $context): Documento
    {
        $contenido = '%PDF-1.4 historico firmado';
        $path = 'documentos/final-historico-'.$this->number.'.pdf';
        Storage::disk('private')->put($path, $contenido);
        $documento = Documento::query()->create([
            'tipo' => 'Convenio',
            'titulo' => $context['instrumento']->titulo,
            'id_instrumento' => $context['convenio']->id_instrumento,
            'es_documento_firmado' => true,
        ]);
        $documento->versiones()->create([
            'numero' => 1,
            'nombre_archivo' => 'convenio-final.pdf',
            'tamanio_bytes' => strlen($contenido),
            'hash_sha256' => hash('sha256', $contenido),
            'ruta_interna' => $path,
            'fecha_carga' => now(),
            'id_usuario' => $context['manager']->id_usuario,
        ]);

        return $documento->load('ultimaVersion');
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
            'sigla' => 'FIR-'.$this->number,
            'nombre' => 'Unidad '.$this->number++,
            'id_institucion' => $institution->id_institucion,
        ]);
    }

    private function createUser(string $roleName, int $unitId): Usuario
    {
        $role = Rol::query()->firstOrCreate(['nombre' => $roleName]);

        return Usuario::query()->create([
            'mail' => 'firma-'.$this->number++.'@example.com',
            'nombre' => 'Usuario de firma',
            'password' => Hash::make('password-segura'),
            'activo' => true,
            'id_rol' => $role->id_rol,
            'id_unidad' => $unitId,
        ]);
    }
}
