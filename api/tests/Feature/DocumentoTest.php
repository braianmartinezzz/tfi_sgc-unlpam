<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Documento;
use App\Models\Instrumento;
use App\Models\Usuario;
use App\Models\Version;
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
                10241,
                'application/pdf'
            ),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['archivo']);

        $this->assertDatabaseCount('documento', 0);
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

    public function test_it_removes_the_uploaded_file_when_database_persistence_fails(): void
    {
        [$convenio] = $this->createConvenio();
        $usuarioInexistente = new Usuario;
        $usuarioInexistente->id_usuario = 999999;

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
            ->getJson("/api/documentos/{$documento->id_documento}/descargar")
            ->assertUnauthorized();
        $this->actingAs($usuario, 'sanctum')
            ->get("/api/documentos/{$documento->id_documento}/descargar")
            ->assertNotFound();
        $this->get("/api/documentos/{$version->id_version}/descargar")
            ->assertNotFound();
        $this->get('/api/documentos/versiones/999999/descargar')->assertNotFound();
        $this->get('/api/documentos/999999/descargar')->assertNotFound();
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
                10241,
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
        [$documento, , , $version] = $this->createDocumentWithVersion();
        $connection = DB::connection();
        $connection->beginTransaction();

        app(DocumentoService::class)->destroy((string) $documento->id_documento);

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
        $rolId = DB::table('rol')->insertGetId(['nombre' => 'Prueba']);

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

    private function validUpload(): array
    {
        return [
            'tipo' => 'Convenio',
            'titulo' => 'Documento de prueba',
            'archivo' => UploadedFile::fake()->createWithContent('documento.pdf', '%PDF-1.4'),
        ];
    }
}
