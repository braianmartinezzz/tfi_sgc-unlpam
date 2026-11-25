<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Documento;
use App\Models\Instrumento;
use App\Models\Usuario;
use App\Models\Version;
use App\Services\AuditoriaService;
use App\Services\DocumentoService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class DocumentoTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
    }

    public function test_it_uploads_a_pdf_with_metadata_and_first_version(): void
    {
        [$convenio, $usuario] = $this->createConvenio();
        $contenido = '%PDF-1.4 documento de prueba';

        $response = $this->actingAs($usuario, 'sanctum')->post(
            "/api/convenios/{$convenio->id_instrumento}/documentos",
            [
                'tipo' => 'Convenio',
                'titulo' => 'Convenio firmado',
                'archivo' => UploadedFile::fake()->createWithContent(
                    'original.pdf',
                    $contenido
                ),
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath('data.documento.tipo', 'Convenio')
            ->assertJsonPath('data.documento.id_instrumento', $convenio->id_instrumento)
            ->assertJsonPath('data.version.numero', 1)
            ->assertJsonPath('data.version.nombre_archivo', 'original.pdf')
            ->assertJsonPath('data.version.hash_sha256', hash('sha256', $contenido))
            ->assertJsonMissingPath('data.version.ruta_interna');

        $this->assertDatabaseHas('documento', [
            'id_documento' => $response->json('data.documento.id_documento'),
            'id_instrumento' => $convenio->id_instrumento,
        ]);
        $this->assertDatabaseHas('version', [
            'numero' => 1,
            'id_documento' => $response->json('data.documento.id_documento'),
            'id_usuario' => $usuario->id_usuario,
            'nombre_archivo' => 'original.pdf',
            'tamanio_bytes' => strlen($contenido),
            'hash_sha256' => hash('sha256', $contenido),
        ]);
        Storage::disk('private')->assertExists(
            $response->json('data.version.ruta_interna')
        );
    }

    public function test_it_rejects_invalid_missing_and_oversized_uploads(): void
    {
        [$convenio, $usuario] = $this->createConvenio();
        $url = "/api/convenios/{$convenio->id_instrumento}/documentos";

        $this->actingAs($usuario, 'sanctum')
            ->post($url, [
                'tipo' => 'Convenio',
                'titulo' => 'No es PDF',
                'archivo' => UploadedFile::fake()->create(
                    'documento.txt',
                    5,
                    'text/plain'
                ),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['archivo']);

        $this->post($url, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tipo', 'titulo', 'archivo']);

        $this->post($url, [
            'tipo' => 'Convenio',
            'titulo' => 'Demasiado grande',
            'archivo' => UploadedFile::fake()->create(
                'grande.pdf',
                20481,
                'application/pdf'
            ),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['archivo']);

        $this->assertDatabaseCount('documento', 0);
    }

    public function test_it_accepts_a_pdf_at_the_20_mb_limit(): void
    {
        [$convenio, $usuario] = $this->createConvenio();

        $this->actingAs($usuario, 'sanctum')
            ->post("/api/convenios/{$convenio->id_instrumento}/documentos", [
                'tipo' => 'Convenio',
                'titulo' => 'PDF de 20 MB',
                'archivo' => UploadedFile::fake()->create(
                    '20mb.pdf',
                    20480,
                    'application/pdf'
                ),
            ])
            ->assertCreated();
    }

    public function test_it_returns_not_found_or_conflict_when_upload_is_not_allowed(): void
    {
        [$convenio, $usuario, $instrumento] = $this->createConvenio('Firmado');

        $this->actingAs($usuario, 'sanctum')
            ->post("/api/convenios/{$convenio->id_instrumento}/documentos", $this->validUpload())
            ->assertStatus(409);

        $this->post('/api/convenios/999999/documentos', $this->validUpload())
            ->assertNotFound();

        $instrumento->update(['estado' => 'EnCorreccion']);
        $this->post(
            "/api/convenios/{$convenio->id_instrumento}/documentos",
            $this->validUpload()
        )->assertCreated();
    }

    public function test_it_rejects_an_upload_based_on_a_stale_version(): void
    {
        [$convenio, $usuario, $instrumento] = $this->createConvenio();

        // El cliente cargó la versión 1; entre tanto, el convenio pasó a la 2.
        $instrumento->update(['version_registro' => 2]);

        $this->actingAs($usuario, 'sanctum')
            ->post(
                "/api/convenios/{$convenio->id_instrumento}/documentos",
                array_merge($this->validUpload(), ['version_registro' => 1])
            )
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'El convenio fue modificado por otro usuario. Recargá los datos y volvé a intentar.'
            );

        $this->assertDatabaseCount('documento', 0);

        // Con la versión vigente la carga pasa.
        $this->post(
            "/api/convenios/{$convenio->id_instrumento}/documentos",
            array_merge($this->validUpload(), ['version_registro' => 2])
        )->assertCreated();
    }

    public function test_it_removes_the_uploaded_file_when_database_persistence_fails(): void
    {
        [$convenio] = $this->createConvenio();
        $usuarioInexistente = new Usuario;
        $usuarioInexistente->id_usuario = 999999;
        $usuarioInexistente->id_rol = $this->roleId('Administración');
        $usuarioInexistente->id_unidad = $convenio->id_unidad_academica;

        try {
            app(DocumentoService::class)->storeInitial(
                (string) $convenio->id_instrumento,
                ['tipo' => 'Convenio', 'titulo' => 'Fallo'],
                UploadedFile::fake()->createWithContent('fallo.pdf', '%PDF-1.4'),
                $usuarioInexistente
            );
            $this->fail('Se esperaba que fallara la clave foránea del usuario.');
        } catch (QueryException) {
            $this->assertDatabaseCount('documento', 0);
            $this->assertDatabaseCount('version', 0);
            $this->assertSame([], Storage::disk('private')->allFiles());
        }
    }

    public function test_it_lists_documents_and_returns_an_empty_list_for_a_convenio_without_them(): void
    {
        [$convenio, $usuario] = $this->createConvenio();

        $this->actingAs($usuario, 'sanctum')
            ->getJson("/api/convenios/{$convenio->id_instrumento}/documentos")
            ->assertOk()
            ->assertExactJson(['data' => []]);

        $documento = $this->createDocument($convenio);
        $this->createVersion($documento, $usuario, 1, 'uno.pdf', 'documentos/uno.pdf');
        $this->createVersion($documento, $usuario, 2, 'dos.pdf', 'documentos/dos.pdf');

        $response = $this->getJson(
            "/api/convenios/{$convenio->id_instrumento}/documentos"
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.0.ultima_version.numero', 2)
            ->assertJsonMissingPath('data.0.ultima_version.ruta_interna');

        $this->getJson('/api/convenios/999999/documentos')->assertNotFound();
    }

    public function test_it_returns_document_history_in_descending_order_without_internal_paths(): void
    {
        [$convenio, $usuario] = $this->createConvenio();
        $documento = $this->createDocument($convenio);
        $this->createVersion($documento, $usuario, 1, 'uno.pdf', 'private/one.pdf');
        $this->createVersion($documento, $usuario, 2, 'dos.pdf', 'private/two.pdf');

        $this->actingAs($usuario, 'sanctum')
            ->getJson("/api/documentos/{$documento->id_documento}")
            ->assertOk()
            ->assertJsonPath('data.versiones.0.numero', 2)
            ->assertJsonPath('data.versiones.1.numero', 1)
            ->assertJsonMissingPath('data.versiones.0.ruta_interna');

        $this->getJson('/api/documentos/999999')->assertNotFound();
    }

    public function test_it_downloads_latest_and_historical_versions_privately(): void
    {
        [$convenio, $usuario] = $this->createConvenio();
        $documento = $this->createDocument($convenio);
        Storage::disk('private')->put('documentos/uno.pdf', '%PDF-1.4 uno');
        Storage::disk('private')->put('documentos/dos.pdf', '%PDF-1.4 dos');
        $primeraVersion = $this->createVersion(
            $documento,
            $usuario,
            1,
            'nombre-original-uno.pdf',
            'documentos/uno.pdf'
        );
        $this->createVersion(
            $documento,
            $usuario,
            2,
            'nombre-original-dos.pdf',
            'documentos/dos.pdf'
        );

        $this->actingAs($usuario, 'sanctum')
            ->get("/api/documentos/{$documento->id_documento}/descargar")
            ->assertOk()
            ->assertDownload('nombre-original-dos.pdf')
            ->assertHeader('Content-Type', 'application/pdf');

        $this->get(
            "/api/documentos/versiones/{$primeraVersion->id_version}/descargar"
        )
            ->assertOk()
            ->assertDownload('nombre-original-uno.pdf')
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_it_rejects_missing_downloads_and_unauthenticated_access(): void
    {
        [$convenio, $usuario] = $this->createConvenio();
        $documento = $this->createDocument($convenio);
        $version = $this->createVersion(
            $documento,
            $usuario,
            1,
            'ausente.pdf',
            'documentos/ausente.pdf'
        );

        $this->withToken('')
            ->getJson("/api/convenios/{$convenio->id_instrumento}/documentos")
            ->assertUnauthorized();
        $this->getJson("/api/documentos/{$documento->id_documento}")
            ->assertUnauthorized();
        $this->getJson("/api/documentos/{$documento->id_documento}/descargar")
            ->assertUnauthorized();
        $this->getJson("/api/documentos/versiones/{$version->id_version}/descargar")
            ->assertUnauthorized();

        $this->actingAs($usuario, 'sanctum')
            ->get("/api/documentos/{$documento->id_documento}/descargar")
            ->assertNotFound();
        $this->get("/api/documentos/{$version->id_version}/descargar")
            ->assertNotFound();
        $this->get('/api/documentos/versiones/999999/descargar')->assertNotFound();
        $this->get('/api/documentos/999999/descargar')->assertNotFound();
    }

    public function test_revision_scope_is_enforced_when_listing_documents(): void
    {
        [$localConvenio, $reviewer] = $this->createConvenio('Revisión');
        $this->createDocument($localConvenio);

        [$foreignConvenio] = $this->createConvenio('Aprobado');
        $this->createDocument($foreignConvenio);

        [$draftConvenio] = $this->createConvenio('Borrador');
        $this->createDocument($draftConvenio);

        $reviewer = $this->assignRole($reviewer, 'Revisión');

        $this->actingAs($reviewer, 'sanctum')
            ->getJson("/api/convenios/{$localConvenio->id_instrumento}/documentos")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson("/api/convenios/{$foreignConvenio->id_instrumento}/documentos")
            ->assertForbidden();
        $this->getJson("/api/convenios/{$draftConvenio->id_instrumento}/documentos")
            ->assertForbidden();

        $reviewer->alcance_global = true;
        $reviewer->save();
        $this->actingAs($reviewer->fresh(), 'sanctum')
            ->getJson("/api/convenios/{$foreignConvenio->id_instrumento}/documentos")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson("/api/convenios/{$draftConvenio->id_instrumento}/documentos")
            ->assertForbidden();
    }

    public function test_revision_scope_is_enforced_for_document_details(): void
    {
        [$localConvenio, $reviewer] = $this->createConvenio('EnCorreccion');
        $localDocument = $this->createDocument($localConvenio);

        [$foreignConvenio] = $this->createConvenio('Rechazado');
        $foreignDocument = $this->createDocument($foreignConvenio);

        [$draftConvenio] = $this->createConvenio('Borrador');
        $draftDocument = $this->createDocument($draftConvenio);

        $reviewer = $this->assignRole($reviewer, 'Revisión');

        $this->actingAs($reviewer, 'sanctum')
            ->getJson("/api/documentos/{$localDocument->id_documento}")
            ->assertOk();

        $this->getJson("/api/documentos/{$foreignDocument->id_documento}")
            ->assertForbidden();
        $this->getJson("/api/documentos/{$draftDocument->id_documento}")
            ->assertForbidden();
        $this->getJson('/api/documentos/999999')->assertNotFound();

        $reviewer->alcance_global = true;
        $reviewer->save();

        $this->actingAs($reviewer->fresh(), 'sanctum')
            ->getJson("/api/documentos/{$foreignDocument->id_documento}")
            ->assertOk();
        $this->getJson("/api/documentos/{$draftDocument->id_documento}")
            ->assertForbidden();
    }

    public function test_revision_scope_is_checked_before_latest_file_availability(): void
    {
        [$localConvenio, $reviewer] = $this->createConvenio('Aprobado');
        $localDocument = $this->createDocument($localConvenio);
        Storage::disk('private')->put('allowed/local.pdf', '%PDF-1.4 local');
        $this->createVersion($localDocument, $reviewer, 1, 'local.pdf', 'allowed/local.pdf');

        [$foreignConvenio, $foreignOwner] = $this->createConvenio('Revisión');
        $foreignDocument = $this->createDocument($foreignConvenio);
        $this->createVersion(
            $foreignDocument,
            $foreignOwner,
            1,
            'foreign.pdf',
            'missing/foreign.pdf'
        );

        [$draftConvenio, $draftOwner] = $this->createConvenio('Borrador');
        $draftDocument = $this->createDocument($draftConvenio);
        $this->createVersion($draftDocument, $draftOwner, 1, 'draft.pdf', 'missing/draft.pdf');

        $localMissingFile = $this->createDocument($localConvenio);
        $this->createVersion(
            $localMissingFile,
            $reviewer,
            1,
            'missing.pdf',
            'missing/local.pdf'
        );

        $reviewer = $this->assignRole($reviewer, 'Revisión');

        $this->actingAs($reviewer, 'sanctum')
            ->get("/api/documentos/{$localDocument->id_documento}/descargar")
            ->assertOk()
            ->assertDownload('local.pdf');

        $this->get("/api/documentos/{$foreignDocument->id_documento}/descargar")
            ->assertForbidden();
        $this->get("/api/documentos/{$draftDocument->id_documento}/descargar")
            ->assertForbidden();
        $this->get("/api/documentos/{$localMissingFile->id_documento}/descargar")
            ->assertNotFound();
        $this->get('/api/documentos/999999/descargar')->assertNotFound();

        $reviewer->alcance_global = true;
        $reviewer->save();
        Storage::disk('private')->put('missing/foreign.pdf', '%PDF-1.4 foreign');
        $this->actingAs($reviewer->fresh(), 'sanctum')
            ->get("/api/documentos/{$foreignDocument->id_documento}/descargar")
            ->assertOk()
            ->assertDownload('foreign.pdf');
    }

    public function test_revision_scope_is_checked_before_specific_version_file_availability(): void
    {
        [$localConvenio, $reviewer] = $this->createConvenio('Archivado');
        $localDocument = $this->createDocument($localConvenio);
        Storage::disk('private')->put('allowed/local-version.pdf', '%PDF-1.4 local');
        $localVersion = $this->createVersion(
            $localDocument,
            $reviewer,
            1,
            'local-version.pdf',
            'allowed/local-version.pdf'
        );

        [$foreignConvenio, $foreignOwner] = $this->createConvenio('Revisión');
        $foreignDocument = $this->createDocument($foreignConvenio);
        $foreignVersion = $this->createVersion(
            $foreignDocument,
            $foreignOwner,
            1,
            'foreign-version.pdf',
            'missing/foreign-version.pdf'
        );

        [$draftConvenio, $draftOwner] = $this->createConvenio('Borrador');
        $draftDocument = $this->createDocument($draftConvenio);
        $draftVersion = $this->createVersion(
            $draftDocument,
            $draftOwner,
            1,
            'draft-version.pdf',
            'missing/draft-version.pdf'
        );

        $localMissingFile = $this->createDocument($localConvenio);
        $localMissingVersion = $this->createVersion(
            $localMissingFile,
            $reviewer,
            1,
            'missing-version.pdf',
            'missing/local-version.pdf'
        );

        $reviewer = $this->assignRole($reviewer, 'Revisión');

        $this->actingAs($reviewer, 'sanctum')
            ->get("/api/documentos/versiones/{$localVersion->id_version}/descargar")
            ->assertOk()
            ->assertDownload('local-version.pdf');

        $this->get("/api/documentos/versiones/{$foreignVersion->id_version}/descargar")
            ->assertForbidden();
        $this->get("/api/documentos/versiones/{$draftVersion->id_version}/descargar")
            ->assertForbidden();
        $this->get("/api/documentos/versiones/{$localMissingVersion->id_version}/descargar")
            ->assertNotFound();
        $this->get('/api/documentos/versiones/999999/descargar')->assertNotFound();

        $reviewer->alcance_global = true;
        $reviewer->save();
        Storage::disk('private')->put('missing/foreign-version.pdf', '%PDF-1.4 foreign');
        $this->actingAs($reviewer->fresh(), 'sanctum')
            ->get("/api/documentos/versiones/{$foreignVersion->id_version}/descargar")
            ->assertOk()
            ->assertDownload('foreign-version.pdf');
    }

    public function test_administracion_and_gestion_keep_existing_document_access(): void
    {
        [$targetConvenio, $targetOwner] = $this->createConvenio('Borrador');
        $documento = $this->createDocument($targetConvenio);
        $this->createVersion(
            $documento,
            $targetOwner,
            1,
            'borrador.pdf',
            'missing/borrador.pdf'
        );

        [, $administrador] = $this->createConvenio('Borrador');
        $administrador = $this->assignRole($administrador, 'Administración');
        $this->actingAs($administrador, 'sanctum')
            ->getJson("/api/documentos/{$documento->id_documento}")
            ->assertOk();

        [, $gestor] = $this->createConvenio('Borrador');
        $gestor = $this->assignRole($gestor, 'Gestión');
        $gestor->update(['id_unidad' => $targetConvenio->id_unidad_academica]);
        $this->actingAs($gestor, 'sanctum')
            ->getJson("/api/convenios/{$targetConvenio->id_instrumento}/documentos")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_document_mutations_enforce_role_and_unit_authorization(): void
    {
        [$convenio, $owner] = $this->createConvenio();
        $documento = $this->createDocument($convenio);

        [, $gestorOtraUnidad] = $this->createConvenio();
        $this->actingAs($gestorOtraUnidad, 'sanctum')
            ->post(
                "/api/convenios/{$convenio->id_instrumento}/documentos",
                $this->validUpload()
            )
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'No tiene permisos para cargar documentos en este convenio.'
            );

        $this->putJson("/api/documentos/{$documento->id_documento}", [
            'titulo' => 'Cambio no autorizado',
        ])->assertForbidden()
            ->assertJsonPath(
                'message',
                'No tiene permisos para modificar este documento.'
            );
        $this->put(
            "/api/documentos/{$documento->id_documento}",
            ['archivo' => UploadedFile::fake()->createWithContent('nuevo.pdf', '%PDF-1.4')]
        )->assertForbidden();
        $this->deleteJson("/api/documentos/{$documento->id_documento}")
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'No tiene permisos para eliminar este documento.'
            );
        $this->assertDatabaseHas('documento', [
            'id_documento' => $documento->id_documento,
        ]);

        foreach (['Revisión', 'Consulta'] as $role) {
            [$ownConvenio, $usuario] = $this->createConvenio();
            $usuario = $this->assignRole($usuario, $role);
            $ownDocument = $this->createDocument($ownConvenio);

            $this->actingAs($usuario, 'sanctum')
                ->post(
                    "/api/convenios/{$ownConvenio->id_instrumento}/documentos",
                    $this->validUpload()
                )->assertForbidden();
            $this->putJson("/api/documentos/{$ownDocument->id_documento}", [
                'titulo' => 'Cambio no autorizado',
            ])->assertForbidden();
            $this->put(
                "/api/documentos/{$ownDocument->id_documento}",
                ['archivo' => UploadedFile::fake()->createWithContent('nuevo.pdf', '%PDF-1.4')]
            )->assertForbidden();
            $this->deleteJson("/api/documentos/{$ownDocument->id_documento}")
                ->assertForbidden();
        }

        [, $administrador] = $this->createConvenio();
        $administrador = $this->assignRole($administrador, 'Administración');
        $this->actingAs($administrador, 'sanctum')
            ->post(
                "/api/convenios/{$convenio->id_instrumento}/documentos",
                $this->validUpload()
            )->assertCreated();
        $this->putJson("/api/documentos/{$documento->id_documento}", [
            'titulo' => 'Actualización de Administración',
        ])->assertOk();
        $this->deleteJson("/api/documentos/{$documento->id_documento}")
            ->assertOk();

        $this->assertDatabaseHas('usuario', [
            'id_usuario' => $owner->id_usuario,
        ]);
        $this->assertDatabaseMissing('documento', [
            'id_documento' => $documento->id_documento,
        ]);
    }

    public function test_gestion_from_another_unit_cannot_view_or_download_documents(): void
    {
        [$convenio, $owner] = $this->createConvenio();
        $documento = $this->createDocument($convenio);
        $version = $this->createVerifiableVersion(
            $documento,
            $owner,
            'convenio.pdf',
            'documentos/convenio.pdf',
            '%PDF-1.4 documento'
        );
        [, $gestorOtraUnidad] = $this->createConvenio();

        $this->actingAs($gestorOtraUnidad, 'sanctum')
            ->getJson("/api/convenios/{$convenio->id_instrumento}")
            ->assertForbidden();
        $this->getJson("/api/convenios/{$convenio->id_instrumento}/documentos")
            ->assertForbidden();
        $this->get("/api/documentos/{$documento->id_documento}/descargar")
            ->assertForbidden();
        $this->get("/api/documentos/versiones/{$version->id_version}/descargar")
            ->assertForbidden();
    }

    public function test_document_endpoints_return_not_found_when_the_owner_convenio_is_missing(): void
    {
        [, $reviewer] = $this->createConvenio('Revisión');
        $reviewer = $this->assignRole($reviewer, 'Revisión');

        $instrumento = Instrumento::query()->create([
            'titulo' => 'Instrumento sin convenio',
            'estado' => 'Revisión',
            'version_registro' => 1,
        ]);
        $documento = Documento::query()->create([
            'tipo' => 'Convenio',
            'titulo' => 'Documento huérfano',
            'id_instrumento' => $instrumento->id_instrumento,
        ]);
        $version = $this->createVersion(
            $documento,
            $reviewer,
            1,
            'huerfano.pdf',
            'missing/huerfano.pdf'
        );

        $this->actingAs($reviewer, 'sanctum')
            ->getJson("/api/documentos/{$documento->id_documento}")
            ->assertNotFound();

        $this->get("/api/documentos/versiones/{$version->id_version}/descargar")
            ->assertNotFound();
    }

    public function test_revision_with_local_scope_verifies_the_latest_version_and_audits_it(): void
    {
        [$convenio, $reviewer] = $this->createConvenio('Revisión');
        $documento = $this->createDocument($convenio);
        $contenidoAnterior = '%PDF-1.4 versión anterior';
        $this->createVerifiableVersion(
            $documento,
            $reviewer,
            'anterior.pdf',
            'verificaciones/anterior.pdf',
            $contenidoAnterior
        );

        $contenido = '%PDF-1.4 documento íntegro';
        $rutaInterna = 'verificaciones/local.pdf';
        $version = $this->createVerifiableVersion(
            $documento,
            $reviewer,
            'local.pdf',
            $rutaInterna,
            $contenido,
            null,
            2
        );
        $reviewer = $this->assignRole($reviewer, 'Revisión');

        $response = $this->actingAs($reviewer, 'sanctum')
            ->getJson("/api/documentos/{$documento->id_documento}/verificar-hash");

        $hash = hash('sha256', $contenido);
        $response
            ->assertOk()
            ->assertJsonPath('integro', true)
            ->assertJsonPath('hash_registrado', $hash)
            ->assertJsonPath('hash_calculado', $hash)
            ->assertJsonPath('mensaje', 'El hash coincide; el documento está íntegro.')
            ->assertJsonMissingPath('ruta_interna')
            ->assertDontSee($rutaInterna);

        $this->assertHashAudit(
            $documento,
            $version,
            $reviewer,
            'íntegro',
            $hash,
            $hash
        );
    }

    public function test_revision_with_global_scope_can_verify_a_document_from_another_unit(): void
    {
        [, $reviewer] = $this->createConvenio('Revisión');
        [$otherConvenio, $owner] = $this->createConvenio('Aprobado');
        $documento = $this->createDocument($otherConvenio);
        $contenido = '%PDF-1.4 otro ámbito';
        $version = $this->createVerifiableVersion(
            $documento,
            $owner,
            'global.pdf',
            'verificaciones/global.pdf',
            $contenido
        );
        $reviewer = $this->assignRole($reviewer, 'Revisión', true);

        $this->actingAs($reviewer, 'sanctum')
            ->getJson("/api/documentos/{$documento->id_documento}/verificar-hash")
            ->assertOk()
            ->assertJsonPath('integro', true)
            ->assertJsonPath('hash_calculado', hash('sha256', $contenido));

        $this->assertHashAudit(
            $documento,
            $version,
            $reviewer,
            'íntegro',
            hash('sha256', $contenido),
            hash('sha256', $contenido)
        );
    }

    public function test_revision_cannot_verify_documents_outside_its_scope_or_in_disallowed_states(): void
    {
        [, $reviewer] = $this->createConvenio('Revisión');
        [$foreignConvenio] = $this->createConvenio('Aprobado');
        $foreignDocument = $this->createDocument($foreignConvenio);
        [$draftConvenio] = $this->createConvenio('Borrador');
        $draftDocument = $this->createDocument($draftConvenio);
        $reviewer = $this->assignRole($reviewer, 'Revisión');

        $this->actingAs($reviewer, 'sanctum')
            ->getJson("/api/documentos/{$foreignDocument->id_documento}/verificar-hash")
            ->assertForbidden();

        $this->getJson("/api/documentos/{$draftDocument->id_documento}/verificar-hash")
            ->assertForbidden();

        $this->assertDatabaseCount('registro_auditoria', 0);
    }

    public function test_administracion_can_verify_a_document_from_any_unit(): void
    {
        [$convenio, $owner] = $this->createConvenio('Borrador');
        $documento = $this->createDocument($convenio);
        $contenido = '%PDF-1.4 administración';
        $version = $this->createVerifiableVersion(
            $documento,
            $owner,
            'administracion.pdf',
            'verificaciones/administracion.pdf',
            $contenido
        );
        [, $administrador] = $this->createConvenio('Borrador');
        $administrador = $this->assignRole($administrador, 'Administración');

        $this->actingAs($administrador, 'sanctum')
            ->getJson("/api/documentos/{$documento->id_documento}/verificar-hash")
            ->assertOk()
            ->assertJsonPath('integro', true);

        $this->assertHashAudit(
            $documento,
            $version,
            $administrador,
            'íntegro',
            hash('sha256', $contenido),
            hash('sha256', $contenido)
        );
    }

    public function test_gestion_and_consulta_cannot_verify_document_integrity(): void
    {
        [$convenio] = $this->createConvenio('Revisión');
        $documento = $this->createDocument($convenio);

        foreach (['Gestión', 'Consulta'] as $rol) {
            [, $usuario] = $this->createConvenio('Revisión');
            $usuario = $this->assignRole($usuario, $rol);

            $this->actingAs($usuario, 'sanctum')
                ->getJson("/api/documentos/{$documento->id_documento}/verificar-hash")
                ->assertForbidden();
        }

        $this->assertDatabaseCount('registro_auditoria', 0);
    }

    public function test_a_hash_mismatch_reports_alteration_and_records_both_hashes(): void
    {
        [$convenio, $reviewer] = $this->createConvenio('EnCorreccion');
        $documento = $this->createDocument($convenio);
        $contenido = '%PDF-1.4 contenido modificado';
        $hashRegistrado = hash('sha256', '%PDF-1.4 contenido original');
        $hashCalculado = hash('sha256', $contenido);
        $version = $this->createVerifiableVersion(
            $documento,
            $reviewer,
            'alterado.pdf',
            'verificaciones/alterado.pdf',
            $contenido,
            $hashRegistrado
        );
        $reviewer = $this->assignRole($reviewer, 'Revisión');

        $this->actingAs($reviewer, 'sanctum')
            ->getJson("/api/documentos/{$documento->id_documento}/verificar-hash")
            ->assertOk()
            ->assertJsonPath('integro', false)
            ->assertJsonPath('hash_registrado', $hashRegistrado)
            ->assertJsonPath('hash_calculado', $hashCalculado)
            ->assertJsonPath(
                'mensaje',
                'Se detectó una alteración: el hash calculado no coincide con el registrado.'
            );

        $this->assertHashAudit(
            $documento,
            $version,
            $reviewer,
            'alterado',
            $hashRegistrado,
            $hashCalculado
        );
    }

    public function test_hash_verification_returns_not_found_for_missing_document_version_or_file(): void
    {
        [$convenio, $reviewer] = $this->createConvenio('Revisión');
        $documentoSinVersion = $this->createDocument($convenio);
        $documentoSinArchivo = $this->createDocument($convenio);
        $this->createVersion(
            $documentoSinArchivo,
            $reviewer,
            1,
            'ausente.pdf',
            'verificaciones/ausente.pdf'
        );
        $reviewer = $this->assignRole($reviewer, 'Revisión');

        $this->actingAs($reviewer, 'sanctum')
            ->getJson('/api/documentos/999999/verificar-hash')
            ->assertNotFound();

        $this->getJson(
            "/api/documentos/{$documentoSinVersion->id_documento}/verificar-hash"
        )->assertNotFound();

        $this->getJson(
            "/api/documentos/{$documentoSinArchivo->id_documento}/verificar-hash"
        )->assertNotFound();

        $this->assertDatabaseCount('registro_auditoria', 0);
    }

    public function test_hash_verification_requires_authentication(): void
    {
        [$convenio] = $this->createConvenio('Revisión');
        $documento = $this->createDocument($convenio);

        $this->withToken('')
            ->getJson("/api/documentos/{$documento->id_documento}/verificar-hash")
            ->assertUnauthorized();
    }

    public function test_hash_verification_does_not_report_success_when_audit_fails(): void
    {
        [$convenio, $usuario] = $this->createConvenio('Revisión');
        $documento = $this->createDocument($convenio);
        $this->createVerifiableVersion(
            $documento,
            $usuario,
            'auditoria-falla.pdf',
            'verificaciones/auditoria-falla.pdf',
            '%PDF-1.4 auditoría'
        );
        $usuario = $this->assignRole($usuario, 'Administración');

        $auditoria = Mockery::mock(AuditoriaService::class);
        $auditoria->shouldReceive('registrar')
            ->once()
            ->andThrow(new \RuntimeException('Falla simulada de auditoría.'));
        $this->app->instance(AuditoriaService::class, $auditoria);

        $this->actingAs($usuario, 'sanctum')
            ->getJson("/api/documentos/{$documento->id_documento}/verificar-hash")
            ->assertInternalServerError()
            ->assertJsonMissingPath('integro')
            ->assertJsonPath(
                'message',
                'No se pudo completar la verificación de integridad.'
            )
            ->assertDontSee('verificaciones/auditoria-falla.pdf');
    }

    public function test_hash_verification_returns_a_controlled_error_when_the_file_cannot_be_read(): void
    {
        [$convenio, $reviewer] = $this->createConvenio('Revisión');
        $documento = $this->createDocument($convenio);
        $rutaInterna = 'verificaciones/lectura-falla.pdf';
        $this->createVersion(
            $documento,
            $reviewer,
            1,
            'lectura-falla.pdf',
            $rutaInterna
        );
        $reviewer = $this->assignRole($reviewer, 'Revisión');

        $disk = Mockery::mock();
        $disk->shouldReceive('exists')
            ->once()
            ->with($rutaInterna)
            ->andReturn(true);
        $disk->shouldReceive('readStream')
            ->once()
            ->with($rutaInterna)
            ->andThrow(new \RuntimeException('Falla de lectura simulada.'));
        Storage::shouldReceive('disk')
            ->once()
            ->with('private')
            ->andReturn($disk);

        $this->actingAs($reviewer, 'sanctum')
            ->getJson("/api/documentos/{$documento->id_documento}/verificar-hash")
            ->assertInternalServerError()
            ->assertJsonMissingPath('hash_calculado')
            ->assertJsonPath(
                'message',
                'No se pudo completar la verificación de integridad.'
            )
            ->assertDontSee($rutaInterna);

        $this->assertDatabaseCount('registro_auditoria', 0);
    }

    public function test_it_updates_document_metadata_without_creating_a_version(): void
    {
        [$documento, $usuario, , $version] = $this->createDocumentWithVersion();
        $documentId = $documento->id_documento;

        $this->actingAs($usuario, 'sanctum')
            ->putJson("/api/documentos/{$documentId}", ['titulo' => 'Nuevo título'])
            ->assertOk()
            ->assertJsonPath('data.documento.id_documento', $documentId)
            ->assertJsonPath('data.documento.titulo', 'Nuevo título')
            ->assertJsonPath('data.documento.tipo', 'Convenio')
            ->assertJsonPath('data.version.id_version', $version->id_version);

        $this->putJson("/api/documentos/{$documentId}", ['tipo' => 'Adenda'])
            ->assertOk()
            ->assertJsonPath('data.documento.id_documento', $documentId)
            ->assertJsonPath('data.documento.tipo', 'Adenda')
            ->assertJsonPath('data.documento.titulo', 'Nuevo título')
            ->assertJsonPath('data.version.numero', 1);

        $this->assertDatabaseCount('version', 1);
        $this->putJson("/api/documentos/{$documentId}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['documento']);
    }

    public function test_it_adds_a_second_pdf_version_and_preserves_the_previous_file(): void
    {
        [$documento, $usuario, , $versionAnterior] = $this->createDocumentWithVersion();
        $idDocumento = $documento->id_documento;
        $contenidoNuevo = '%PDF-1.4 segunda versión';

        $response = $this->actingAs($usuario, 'sanctum')->put(
            "/api/documentos/{$idDocumento}",
            [
                'archivo' => UploadedFile::fake()->createWithContent(
                    'segunda.pdf',
                    $contenidoNuevo
                ),
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.documento.id_documento', $idDocumento)
            ->assertJsonPath('data.version.numero', 2)
            ->assertJsonPath('data.version.nombre_archivo', 'segunda.pdf')
            ->assertJsonPath('data.version.hash_sha256', hash('sha256', $contenidoNuevo))
            ->assertJsonPath('data.version.id_usuario', $usuario->id_usuario)
            ->assertJsonMissingPath('data.version.ruta_interna');

        $this->assertDatabaseHas('version', [
            'id_version' => $versionAnterior->id_version,
            'numero' => 1,
        ]);
        $this->assertDatabaseCount('version', 2);
        Storage::disk('private')->assertExists($versionAnterior->ruta_interna);
        Storage::disk('private')->assertExists(
            Version::query()->findOrFail($response->json('data.version.id_version'))->ruta_interna
        );
    }

    public function test_it_updates_metadata_and_creates_a_version_in_the_same_request(): void
    {
        [$documento, $usuario] = $this->createDocumentWithVersion();
        $idDocumento = $documento->id_documento;

        $this->actingAs($usuario, 'sanctum')->put(
            "/api/documentos/{$idDocumento}",
            [
                'tipo' => 'Adenda',
                'titulo' => 'Título actualizado',
                'archivo' => UploadedFile::fake()->createWithContent(
                    'actualizado.pdf',
                    '%PDF-1.4 actualizado'
                ),
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.documento.id_documento', $idDocumento)
            ->assertJsonPath('data.documento.tipo', 'Adenda')
            ->assertJsonPath('data.documento.titulo', 'Título actualizado')
            ->assertJsonPath('data.version.numero', 2);

        $this->assertDatabaseCount('version', 2);
    }

    public function test_it_rejects_invalid_or_oversized_update_files(): void
    {
        [$documento, $usuario] = $this->createDocumentWithVersion();
        $url = "/api/documentos/{$documento->id_documento}";

        $this->actingAs($usuario, 'sanctum')->put($url, [
            'archivo' => UploadedFile::fake()->create('documento.txt', 5, 'text/plain'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['archivo']);

        $this->put($url, [
            'archivo' => UploadedFile::fake()->create(
                'grande.pdf',
                20481,
                'application/pdf'
            ),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['archivo']);

        $this->assertDatabaseCount('version', 1);
    }

    public function test_it_returns_not_found_for_a_missing_document_update(): void
    {
        [$documento, $usuario] = $this->createDocumentWithVersion();

        $this->actingAs($usuario, 'sanctum')
            ->putJson('/api/documentos/999999', ['titulo' => 'No existe'])
            ->assertNotFound();
    }

    public function test_document_update_requires_authentication(): void
    {
        [$documento] = $this->createDocumentWithVersion();
        $this->withToken('')
            ->putJson("/api/documentos/{$documento->id_documento}", [
                'titulo' => 'Sin autenticación',
            ])
            ->assertUnauthorized();
    }

    public function test_it_only_blocks_new_versions_when_the_convenio_state_disallows_uploads(): void
    {
        [$documento, $usuario, $instrumento, $version] = $this->createDocumentWithVersion('Firmado');
        $url = "/api/documentos/{$documento->id_documento}";

        $this->actingAs($usuario, 'sanctum')
            ->put($url, [
                'archivo' => UploadedFile::fake()->createWithContent('tercera.pdf', '%PDF-1.4'),
            ])
            ->assertStatus(409);

        $this->putJson($url, ['titulo' => 'Metadatos permitidos'])
            ->assertOk()
            ->assertJsonPath('data.documento.titulo', 'Metadatos permitidos')
            ->assertJsonPath('data.version.id_version', $version->id_version);

        $instrumento->update(['estado' => 'EnCorreccion']);
        $this->put($url, [
            'archivo' => UploadedFile::fake()->createWithContent(
                'tercera.pdf',
                '%PDF-1.4'
            ),
        ])
            ->assertOk()
            ->assertJsonPath('data.version.numero', 2);
    }

    public function test_it_removes_a_new_file_if_version_persistence_fails(): void
    {
        [$documento, , , $versionAnterior] = $this->createDocumentWithVersion();
        $usuarioInexistente = new Usuario;
        $usuarioInexistente->id_usuario = 999999;
        $usuarioInexistente->id_rol = $this->roleId('Administración');
        $usuarioInexistente->id_unidad = $documento->instrumento->convenio->id_unidad_academica;

        try {
            app(DocumentoService::class)->update(
                (string) $documento->id_documento,
                [],
                UploadedFile::fake()->createWithContent('fallo.pdf', '%PDF-1.4'),
                $usuarioInexistente
            );
            $this->fail('Se esperaba que fallara la clave foránea del usuario.');
        } catch (QueryException) {
            $this->assertDatabaseCount('version', 1);
            $this->assertDatabaseHas('version', [
                'id_version' => $versionAnterior->id_version,
            ]);
            Storage::disk('private')->assertExists($versionAnterior->ruta_interna);
            $this->assertCount(1, Storage::disk('private')->allFiles());
        }
    }

    public function test_it_deletes_a_document_versions_and_only_its_files(): void
    {
        [$documento, $usuario, , $versionUno] = $this->createDocumentWithVersion();
        $versionDos = $this->createVersion(
            $documento,
            $usuario,
            2,
            'segunda.pdf',
            'documentos/segunda.pdf'
        );
        Storage::disk('private')->put($versionDos->ruta_interna, '%PDF-1.4 segunda');

        $convenio = $documento->instrumento->convenio;
        $otroDocumento = $this->createDocument($convenio);
        $otraVersion = $this->createVersion(
            $otroDocumento,
            $usuario,
            1,
            'otro.pdf',
            'documentos/otro.pdf'
        );
        Storage::disk('private')->put($otraVersion->ruta_interna, '%PDF-1.4 otro');

        $this->actingAs($usuario, 'sanctum')
            ->deleteJson("/api/documentos/{$documento->id_documento}")
            ->assertOk()
            ->assertExactJson(['message' => 'Documento eliminado correctamente.']);

        $this->assertDatabaseMissing('documento', [
            'id_documento' => $documento->id_documento,
        ]);
        $this->assertDatabaseMissing('version', [
            'id_version' => $versionUno->id_version,
        ]);
        $this->assertDatabaseMissing('version', [
            'id_version' => $versionDos->id_version,
        ]);
        $this->assertDatabaseHas('documento', [
            'id_documento' => $otroDocumento->id_documento,
        ]);
        $this->assertDatabaseHas('convenio', [
            'id_instrumento' => $convenio->id_instrumento,
        ]);
        Storage::disk('private')->assertMissing($versionUno->ruta_interna);
        Storage::disk('private')->assertMissing($versionDos->ruta_interna);
        Storage::disk('private')->assertExists($otraVersion->ruta_interna);
    }

    public function test_it_returns_not_found_when_deleting_an_unknown_document(): void
    {
        [, $usuario] = $this->createConvenio();

        $this->actingAs($usuario, 'sanctum')
            ->deleteJson('/api/documentos/999999')
            ->assertNotFound();
    }

    public function test_it_rejects_document_deletion_when_the_convenio_state_is_not_allowed(): void
    {
        [$documento, $usuario, , $version] = $this->createDocumentWithVersion('Firmado');

        $this->actingAs($usuario, 'sanctum')
            ->deleteJson("/api/documentos/{$documento->id_documento}")
            ->assertStatus(409);

        $this->assertDatabaseHas('documento', [
            'id_documento' => $documento->id_documento,
        ]);
        $this->assertDatabaseHas('version', [
            'id_version' => $version->id_version,
        ]);
        Storage::disk('private')->assertExists($version->ruta_interna);
    }

    public function test_document_deletion_requires_authentication(): void
    {
        [$documento] = $this->createDocumentWithVersion();

        $this->withToken('')
            ->deleteJson("/api/documentos/{$documento->id_documento}")
            ->assertUnauthorized();
    }

    public function test_a_rollback_does_not_remove_document_files_prematurely(): void
    {
        [$documento, $usuario, , $version] = $this->createDocumentWithVersion();
        $connection = DB::connection();
        $connection->beginTransaction();

        app(DocumentoService::class)->destroy(
            (string) $documento->id_documento,
            $usuario
        );

        Storage::disk('private')->assertExists($version->ruta_interna);
        $connection->rollBack();

        $this->assertDatabaseHas('documento', [
            'id_documento' => $documento->id_documento,
        ]);
        $this->assertDatabaseHas('version', [
            'id_version' => $version->id_version,
        ]);
        Storage::disk('private')->assertExists($version->ruta_interna);
    }

    public function test_it_logs_file_deletion_failures_after_the_database_commit(): void
    {
        [$documento, $usuario] = $this->createDocumentWithVersion();
        $idDocumento = $documento->id_documento;
        $disk = Mockery::mock();
        $disk->shouldReceive('delete')
            ->once()
            ->with('documentos/original.pdf')
            ->andReturn(false);

        Storage::shouldReceive('disk')
            ->once()
            ->with('private')
            ->andReturn($disk);
        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn ($message, $context) => $message === 'No se pudo eliminar un archivo de documento.'
                && (string) $context['id_documento'] === (string) $idDocumento);

        $this->actingAs($usuario, 'sanctum')
            ->deleteJson("/api/documentos/{$idDocumento}")
            ->assertOk();

        $this->assertDatabaseMissing('documento', [
            'id_documento' => $idDocumento,
        ]);
    }

    private function createConvenio(string $estado = 'Borrador'): array
    {
        $institucionId = DB::table('institucion_firmante')->insertGetId([
            'razon_social' => 'Institución de prueba',
        ]);
        $unidadId = DB::table('unidad_academica')->insertGetId([
            'sigla' => 'UA',
            'nombre' => 'Unidad Académica',
            'id_institucion' => $institucionId,
        ]);
        $rolId = $this->roleId('Gestión');

        $usuario = Usuario::query()->create([
            'mail' => uniqid('documento-', true).'@example.com',
            'nombre' => 'Usuario de prueba',
            'password' => Hash::make('password-segura'),
            'activo' => true,
            'id_rol' => $rolId,
            'id_unidad' => $unidadId,
        ]);

        $instrumento = Instrumento::query()->create([
            'titulo' => 'Convenio de prueba',
            'estado' => $estado,
            'version_registro' => 1,
        ]);
        $convenio = Convenio::query()->create([
            'id_instrumento' => $instrumento->id_instrumento,
            'tipo' => 'Colaboración',
            'id_unidad_academica' => $unidadId,
        ]);

        $this->withToken($usuario->createToken('test')->plainTextToken);

        return [$convenio, $usuario, $instrumento];
    }

    private function createDocumentWithVersion(string $estado = 'Borrador'): array
    {
        [$convenio, $usuario, $instrumento] = $this->createConvenio($estado);
        $documento = $this->createDocument($convenio);
        Storage::disk('private')->put('documentos/original.pdf', '%PDF-1.4 original');
        $version = $this->createVersion(
            $documento,
            $usuario,
            1,
            'original.pdf',
            'documentos/original.pdf'
        );

        return [$documento, $usuario, $instrumento, $version];
    }

    private function createDocument(Convenio $convenio): Documento
    {
        return Documento::query()->create([
            'tipo' => 'Convenio',
            'titulo' => 'Documento de prueba',
            'id_instrumento' => $convenio->id_instrumento,
        ]);
    }

    private function createVerifiableVersion(
        Documento $documento,
        Usuario $usuario,
        string $nombre,
        string $ruta,
        string $contenido,
        ?string $hashRegistrado = null,
        int $numero = 1
    ): Version {
        Storage::disk('private')->put($ruta, $contenido);

        $version = $this->createVersion(
            $documento,
            $usuario,
            $numero,
            $nombre,
            $ruta
        );
        $version->update([
            'hash_sha256' => $hashRegistrado ?? hash('sha256', $contenido),
        ]);

        return $version->fresh();
    }

    private function createVersion(
        Documento $documento,
        Usuario $usuario,
        int $numero,
        string $nombre,
        string $ruta
    ): Version {
        return $documento->versiones()->create([
            'numero' => $numero,
            'nombre_archivo' => $nombre,
            'tamanio_bytes' => 10,
            'hash_sha256' => hash('sha256', $nombre),
            'ruta_interna' => $ruta,
            'fecha_carga' => now(),
            'id_usuario' => $usuario->id_usuario,
        ]);
    }

    private function assertHashAudit(
        Documento $documento,
        Version $version,
        Usuario $usuario,
        string $resultado,
        string $hashRegistrado,
        string $hashCalculado
    ): void {
        $registro = DB::table('registro_auditoria')
            ->where('accion', 'VERIFICACION_INTEGRIDAD_HASH')
            ->where('id_instrumento', $documento->id_instrumento)
            ->where('id_usuario', $usuario->id_usuario)
            ->first();

        $this->assertNotNull($registro);
        $this->assertSame(
            sprintf(
                'Documento #%d; versión #%d; resultado: %s; hash registrado: %s; hash calculado: %s',
                $documento->id_documento,
                $version->id_version,
                $resultado,
                $hashRegistrado,
                $hashCalculado
            ),
            $registro->detalle
        );
    }

    private function assignRole(
        Usuario $usuario,
        string $nombre,
        bool $alcanceGlobal = false
    ): Usuario {
        $rolId = DB::table('rol')->where('nombre', $nombre)->value('id_rol');

        if ($rolId === null) {
            $rolId = DB::table('rol')->insertGetId(['nombre' => $nombre]);
        }

        $usuario->update([
            'id_rol' => $rolId,
            'alcance_global' => $alcanceGlobal,
        ]);

        return $usuario->fresh();
    }

    private function roleId(string $nombre): int
    {
        $rolId = DB::table('rol')->where('nombre', $nombre)->value('id_rol');

        return $rolId ?? DB::table('rol')->insertGetId(['nombre' => $nombre]);
    }

    private function validUpload(): array
    {
        return [
            'tipo' => 'Convenio',
            'titulo' => 'Documento de prueba',
            'archivo' => UploadedFile::fake()->createWithContent('documento.pdf', '%PDF-1.4'),
        ];
    }
}
