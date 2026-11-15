<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Documento;
use App\Models\Especifico;
use App\Models\HistorialEstado;
use App\Models\InstitucionFirmante;
use App\Models\Instrumento;
use App\Models\Marco;
use App\Models\Persona;
use App\Models\Representante;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConvenioReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_convenios_and_paginates_fifteen_while_preserving_filters(): void
    {
        $context = $this->authenticate();

        for ($index = 1; $index <= 16; $index++) {
            $this->createConvenio(
                'Paginación '.$index,
                'Borrador',
                'Colaboración',
                $context['unidadPrincipal'],
                $context['institucion'],
                true
            );
        }
        $this->createConvenio(
            'No coincide con la búsqueda',
            'Firmado',
            'Pasantía',
            $context['otraUnidad'],
            $context['institucion'],
            true
        );

        $query = http_build_query([
            'estado' => 'Borrador',
            'tipo' => 'Colaboración',
            'es_marco' => 'true',
            'id_unidad_academica' => $context['unidadPrincipal'],
            'buscar' => 'Paginación',
        ]);

        $response = $this->getJson('/api/convenios?'.$query);

        $response
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('per_page', 15)
            ->assertJsonPath('total', 16)
            ->assertJsonPath('current_page', 1);

        $nextPageQuery = parse_url($response->json('next_page_url'), PHP_URL_QUERY);
        $this->assertNotNull($nextPageQuery);
        $this->assertStringContainsString('estado=Borrador', $nextPageQuery);
        $this->assertStringContainsString('tipo=Colaboraci', $nextPageQuery);
        $this->assertStringContainsString('buscar=Paginaci', $nextPageQuery);

        $this->getJson('/api/convenios?'.$nextPageQuery)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('current_page', 2);
    }

    public function test_index_filters_by_state_type_subtype_unit_and_title(): void
    {
        $context = $this->authenticate();

        $marco = $this->createConvenio(
            'Acuerdo Alpha',
            'Borrador',
            'Colaboración',
            $context['unidadPrincipal'],
            $context['institucion'],
            true
        );
        $especifico = $this->createConvenio(
            'Acuerdo Beta',
            'Firmado',
            'Pasantía',
            $context['unidadPrincipal'],
            $context['institucion'],
            false,
            $marco->id_instrumento
        );
        $otroMarco = $this->createConvenio(
            'Acuerdo Gamma',
            'Borrador',
            'Pasantía',
            $context['otraUnidad'],
            $context['institucion'],
            true
        );

        $this->assertSame(
            [$especifico->id_instrumento],
            $this->listedIds(['estado' => 'Firmado'])
        );
        $this->assertSame(
            [$marco->id_instrumento],
            $this->listedIds(['tipo' => 'Colaboración'])
        );
        $this->assertEqualsCanonicalizing(
            [$marco->id_instrumento, $otroMarco->id_instrumento],
            $this->listedIds(['es_marco' => 'true'])
        );
        $this->assertSame(
            [$especifico->id_instrumento],
            $this->listedIds(['es_marco' => 'false'])
        );

        $unitIds = $this->listedIds([
            'id_unidad_academica' => $context['otraUnidad'],
        ]);
        $this->assertSame([$otroMarco->id_instrumento], $unitIds);

        $this->assertSame(
            [$marco->id_instrumento],
            $this->listedIds(['buscar' => 'Alpha'])
        );
    }

    public function test_show_returns_the_convenio_with_its_related_data(): void
    {
        $context = $this->authenticate();
        $institution = InstitucionFirmante::query()->findOrFail($context['institucion']);
        $institution->update([
            'cuit' => '30123456789',
            'domicilio_legal' => 'Calle Principal 123',
            'tipo_institucion' => 'Universidad',
        ]);
        $secondInstitution = InstitucionFirmante::query()->create([
            'razon_social' => 'Institución firmante secundaria',
            'cuit' => '30987654321',
            'domicilio_legal' => 'Avenida Central 456',
            'tipo_institucion' => 'Organismo',
        ]);
        $institutionWithoutRepresentatives = InstitucionFirmante::query()->create([
            'razon_social' => 'Institución sin representantes',
        ]);
        $marco = $this->createConvenio(
            'Convenio marco padre',
            'Borrador',
            'Colaboración',
            $context['unidadPrincipal'],
            $context['institucion'],
            true
        );
        $especifico = $this->createConvenio(
            'Convenio específico',
            'Borrador',
            'Pasantía',
            $context['unidadPrincipal'],
            $context['institucion'],
            false,
            $marco->id_instrumento
        );
        $especifico->institucionesFirmantes()->attach([
            $secondInstitution->id_institucion,
            $institutionWithoutRepresentatives->id_institucion,
        ]);

        HistorialEstado::create([
            'estado_anterior' => null,
            'estado_nuevo' => 'Borrador',
            'motivo' => 'Creación del convenio',
            'id_instrumento' => $especifico->id_instrumento,
            'id_usuario' => $context['usuario']->id_usuario,
        ]);

        $representantesByInstitution = [
            $context['institucion'] => [
                $this->createRepresentative($context['institucion'], 'Ana', 'Pérez', 'Directora'),
                $this->createRepresentative($context['institucion'], 'Luis', 'Gómez', 'Apoderado'),
            ],
            $secondInstitution->id_institucion => [
                $this->createRepresentative(
                    $secondInstitution->id_institucion,
                    'María',
                    'López',
                    'Representante legal'
                ),
            ],
            $institutionWithoutRepresentatives->id_institucion => [],
        ];

        $response = $this->getJson('/api/convenios/'.$especifico->id_instrumento);
        $response
            ->assertOk()
            ->assertJsonPath('data.instrumento.titulo', 'Convenio específico')
            ->assertJsonPath('data.unidad_academica.id_unidad_academica', $context['unidadPrincipal'])
            ->assertJsonPath(
                'data.especifico.marco.instrumento.titulo',
                'Convenio marco padre'
            )
            ->assertJsonPath('data.historial_estados.0.estado_nuevo', 'Borrador');

        $institutions = collect($response->json('data.instituciones_firmantes'))
            ->keyBy('id_institucion');
        $this->assertCount(3, $institutions);
        $this->assertSame(
            'Institución de lectura de prueba',
            $institutions[$context['institucion']]['razon_social']
        );
        $this->assertSame('30123456789', $institutions[$context['institucion']]['cuit']);
        $this->assertSame(
            'Calle Principal 123',
            $institutions[$context['institucion']]['domicilio_legal']
        );
        $this->assertSame(
            'Universidad',
            $institutions[$context['institucion']]['tipo_institucion']
        );
        $this->assertSame(
            'Avenida Central 456',
            $institutions[$secondInstitution->id_institucion]['domicilio_legal']
        );
        $this->assertSame(
            [],
            $institutions[$institutionWithoutRepresentatives->id_institucion]['representantes']
        );

        foreach ($representantesByInstitution as $institutionId => $representantes) {
            $actualRepresentatives = collect($institutions[$institutionId]['representantes']);
            $this->assertEqualsCanonicalizing(
                collect($representantes)->pluck('id_persona')->all(),
                $actualRepresentatives->pluck('id_persona')->all()
            );

            foreach ($actualRepresentatives as $representante) {
                $this->assertSame($institutionId, $representante['id_institucion']);
                $expectedRepresentative = collect($representantes)
                    ->firstWhere('id_persona', $representante['id_persona']);
                $this->assertSame($expectedRepresentative->cargo, $representante['cargo']);
                $this->assertSame(
                    $expectedRepresentative->persona->nombre,
                    $representante['persona']['nombre']
                );
                $this->assertSame(
                    $expectedRepresentative->persona->apellido,
                    $representante['persona']['apellido']
                );
                $this->assertArrayNotHasKey('dni', $representante['persona']);
                $this->assertArrayNotHasKey('cuil', $representante['persona']);
            }
        }
    }

    public function test_show_includes_documents_and_only_the_latest_version_metadata(): void
    {
        $context = $this->authenticate();
        $convenio = $this->createConvenio(
            'Convenio con documentos',
            'Borrador',
            'Colaboración',
            $context['unidadPrincipal'],
            $context['institucion'],
            true
        );
        $documento = Documento::query()->create([
            'tipo' => 'Anexo',
            'titulo' => 'Anexo técnico',
            'id_instrumento' => $convenio->id_instrumento,
        ]);
        $documentoSinVersion = Documento::query()->create([
            'tipo' => 'Nota',
            'titulo' => 'Nota sin versión',
            'id_instrumento' => $convenio->id_instrumento,
        ]);

        $documento->versiones()->create([
            'numero' => 1,
            'nombre_archivo' => 'anexo-v1.pdf',
            'tamanio_bytes' => 10,
            'hash_sha256' => hash('sha256', 'contenido versión 1'),
            'ruta_interna' => 'documentos/anexo-v1.pdf',
            'fecha_carga' => '2026-11-13 10:00:00',
            'id_usuario' => $context['usuario']->id_usuario,
        ]);
        $ultimaVersion = $documento->versiones()->create([
            'numero' => 2,
            'nombre_archivo' => 'anexo-final.pdf',
            'tamanio_bytes' => 20,
            'hash_sha256' => hash('sha256', 'contenido versión final'),
            'ruta_interna' => 'documentos/anexo-final.pdf',
            'fecha_carga' => '2026-11-14 10:00:00',
            'id_usuario' => $context['usuario']->id_usuario,
        ]);

        $response = $this->getJson('/api/convenios/'.$convenio->id_instrumento);

        $response->assertOk();
        $documentos = collect($response->json('data.instrumento.documentos'))
            ->keyBy('id_documento');
        $this->assertCount(2, $documentos);

        $documentoData = $documentos[$documento->id_documento];
        $this->assertSame($documento->id_documento, $documentoData['id_documento']);
        $this->assertSame('Anexo', $documentoData['tipo']);
        $this->assertSame('Anexo técnico', $documentoData['titulo']);
        $this->assertArrayNotHasKey('versiones', $documentoData);

        $versionData = $documentoData['ultima_version'];
        $this->assertSame($ultimaVersion->id_version, $versionData['id_version']);
        $this->assertSame(2, $versionData['numero']);
        $this->assertSame('anexo-final.pdf', $versionData['nombre_archivo']);
        $this->assertSame(20, $versionData['tamanio_bytes']);
        $this->assertSame(
            hash('sha256', 'contenido versión final'),
            $versionData['hash_sha256']
        );
        $this->assertNotEmpty($versionData['fecha_carga']);
        $this->assertArrayNotHasKey('ruta_interna', $versionData);

        $documentoSinVersionData = $documentos[$documentoSinVersion->id_documento];
        $this->assertSame('Nota', $documentoSinVersionData['tipo']);
        $this->assertNull($documentoSinVersionData['ultima_version']);
    }

    public function test_show_returns_an_empty_document_collection_when_none_exist(): void
    {
        $context = $this->authenticate();
        $convenio = $this->createConvenio(
            'Convenio sin documentos',
            'Borrador',
            'Colaboración',
            $context['unidadPrincipal'],
            $context['institucion'],
            true
        );

        $this->getJson('/api/convenios/'.$convenio->id_instrumento)
            ->assertOk()
            ->assertJsonPath('data.instrumento.documentos', []);
    }

    public function test_show_returns_404_for_a_missing_convenio(): void
    {
        $this->authenticate();

        $this->getJson('/api/convenios/999999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Convenio no encontrado.');
    }

    public function test_read_endpoints_require_authentication(): void
    {
        $this->getJson('/api/convenios')->assertUnauthorized();
        $this->getJson('/api/convenios/1')->assertUnauthorized();
    }

    public function test_gestion_is_limited_to_its_unit_and_allowed_states_even_if_scope_is_global(): void
    {
        $context = $this->authenticate('Gestión', true);
        $states = [
            'Borrador',
            'EnCorreccion',
            'Revisión',
            'Aprobado',
            'Firmado',
            'Rescindido',
            'Rechazado',
            'Archivado',
        ];
        $visibleStates = [
            'Borrador',
            'EnCorreccion',
            'Revisión',
            'Aprobado',
            'Firmado',
            'Rescindido',
        ];
        $ownIds = [];

        foreach ($states as $state) {
            $own = $this->createConvenio(
                'Gestión propia '.$state,
                $state,
                'Colaboración',
                $context['unidadPrincipal'],
                $context['institucion'],
                true
            );
            $other = $this->createConvenio(
                'Gestión otra '.$state,
                $state,
                'Colaboración',
                $context['otraUnidad'],
                $context['institucion'],
                true
            );

            if (in_array($state, $visibleStates, true)) {
                $ownIds[] = $own->id_instrumento;
            }
        }

        $this->assertEqualsCanonicalizing($ownIds, $this->listedIds([]));
        $this->assertSame(
            [],
            $this->listedIds(['id_unidad_academica' => $context['otraUnidad']])
        );
        $this->assertSame(
            [],
            $this->listedIds(['estado' => 'Rechazado'])
        );
    }

    public function test_revision_scope_and_visible_states_follow_scope_setting(): void
    {
        $context = $this->authenticate('Revisión', false);
        $states = [
            'Borrador',
            'EnCorreccion',
            'Revisión',
            'Aprobado',
            'Firmado',
            'Rescindido',
            'Rechazado',
            'Archivado',
        ];
        $visibleStates = [
            'Revisión',
            'EnCorreccion',
            'Aprobado',
            'Rechazado',
            'Rescindido',
            'Archivado',
        ];
        $ownIds = [];
        $globalIds = [];
        $ownByState = [];
        $otherByState = [];

        foreach ($states as $state) {
            $own = $this->createConvenio(
                'Revisión propia '.$state,
                $state,
                'Colaboración',
                $context['unidadPrincipal'],
                $context['institucion'],
                true
            );
            $other = $this->createConvenio(
                'Revisión otra '.$state,
                $state,
                'Colaboración',
                $context['otraUnidad'],
                $context['institucion'],
                true
            );

            $ownByState[$state] = $own;
            $otherByState[$state] = $other;

            if (in_array($state, $visibleStates, true)) {
                $ownIds[] = $own->id_instrumento;
                $globalIds[] = $own->id_instrumento;
                $globalIds[] = $other->id_instrumento;

                $this->getJson('/api/convenios/'.$own->id_instrumento)
                    ->assertOk()
                    ->assertJsonPath('data.instrumento.estado', $state);
            }
        }

        $this->assertEqualsCanonicalizing($ownIds, $this->listedIds([]));
        $this->getJson('/api/convenios/'.$otherByState['Revisión']->id_instrumento)
            ->assertForbidden();
        $this->getJson('/api/convenios/'.$ownByState['Borrador']->id_instrumento)
            ->assertForbidden();
        $this->getJson('/api/convenios/999999')->assertNotFound();

        $context['usuario']->alcance_global = true;
        $context['usuario']->save();
        $this->actingAs($context['usuario']->fresh(), 'sanctum');
        $this->assertEqualsCanonicalizing($globalIds, $this->listedIds([]));
        $this->getJson('/api/convenios/'.$otherByState['Revisión']->id_instrumento)
            ->assertOk()
            ->assertJsonPath('data.instrumento.estado', 'Revisión');
    }

    public function test_show_preserves_access_for_administracion(): void
    {
        $adminContext = $this->authenticate('Administración', false);
        $otherUnitDraft = $this->createConvenio(
            'Borrador de otra unidad para Administración',
            'Borrador',
            'Colaboración',
            $adminContext['otraUnidad'],
            $adminContext['institucion'],
            true
        );

        $this->getJson('/api/convenios/'.$otherUnitDraft->id_instrumento)
            ->assertOk()
            ->assertJsonPath('data.instrumento.estado', 'Borrador');
    }

    public function test_show_preserves_access_for_gestion(): void
    {
        $gestionContext = $this->authenticate('Gestión', false);
        $ownDraft = $this->createConvenio(
            'Borrador propio para Gestión',
            'Borrador',
            'Colaboración',
            $gestionContext['unidadPrincipal'],
            $gestionContext['institucion'],
            true
        );

        $this->getJson('/api/convenios/'.$ownDraft->id_instrumento)
            ->assertOk()
            ->assertJsonPath('data.instrumento.estado', 'Borrador');
    }

    public function test_revision_filter_returns_only_revision_state_within_configured_scope(): void
    {
        $context = $this->authenticate('Revisión', false);
        $ownRevision = $this->createConvenio(
            'Revisión propia pendiente',
            'Revisión',
            'Colaboración',
            $context['unidadPrincipal'],
            $context['institucion'],
            true
        );
        $otherUnitRevision = $this->createConvenio(
            'Revisión de otra unidad',
            'Revisión',
            'Colaboración',
            $context['otraUnidad'],
            $context['institucion'],
            true
        );

        foreach (['EnCorreccion', 'Aprobado', 'Rechazado', 'Rescindido', 'Archivado'] as $state) {
            $this->createConvenio(
                'Otro estado '.$state,
                $state,
                'Colaboración',
                $context['unidadPrincipal'],
                $context['institucion'],
                true
            );
        }

        $filters = http_build_query(['estado' => 'Revisión']);
        $localResponse = $this->getJson('/api/convenios?'.$filters);

        $localResponse
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_instrumento', $ownRevision->id_instrumento)
            ->assertJsonPath('data.0.instrumento.estado', 'Revisión');

        $context['usuario']->alcance_global = true;
        $context['usuario']->save();
        $this->actingAs($context['usuario']->fresh(), 'sanctum');

        $globalResponse = $this->getJson('/api/convenios?'.$filters);
        $globalResponse->assertOk()->assertJsonCount(2, 'data');

        $globalIds = collect($globalResponse->json('data'))
            ->pluck('id_instrumento')
            ->all();

        $this->assertEqualsCanonicalizing(
            [$ownRevision->id_instrumento, $otherUnitRevision->id_instrumento],
            $globalIds
        );

        foreach ($globalResponse->json('data') as $convenio) {
            $this->assertSame('Revisión', $convenio['instrumento']['estado']);
        }
    }

    public function test_consulta_scope_allows_only_configured_states_even_when_other_states_are_expired(): void
    {
        $context = $this->authenticate('Consulta', false);
        $expiredBorrador = $this->createConvenio(
            'Consulta vencido local',
            'Borrador',
            'Colaboración',
            $context['unidadPrincipal'],
            $context['institucion'],
            true,
            null,
            today()->subDay()->toDateString()
        );
        $notExpiredBorrador = $this->createConvenio(
            'Consulta no vencido local',
            'Borrador',
            'Colaboración',
            $context['unidadPrincipal'],
            $context['institucion'],
            true,
            null,
            today()->toDateString()
        );
        $expiredRevision = $this->createConvenio(
            'Consulta revisión vencida',
            'Revisión',
            'Colaboración',
            $context['unidadPrincipal'],
            $context['institucion'],
            true,
            null,
            today()->subDay()->toDateString()
        );
        $firmado = $this->createConvenio(
            'Consulta firmado local',
            'Firmado',
            'Colaboración',
            $context['unidadPrincipal'],
            $context['institucion'],
            true
        );
        $rescindido = $this->createConvenio(
            'Consulta rescindido local',
            'Rescindido',
            'Colaboración',
            $context['unidadPrincipal'],
            $context['institucion'],
            true
        );
        $archivado = $this->createConvenio(
            'Consulta archivado local',
            'Archivado',
            'Colaboración',
            $context['unidadPrincipal'],
            $context['institucion'],
            true
        );
        $expiredOtherUnit = $this->createConvenio(
            'Consulta vencido global',
            'Aprobado',
            'Colaboración',
            $context['otraUnidad'],
            $context['institucion'],
            true,
            null,
            today()->subDay()->toDateString()
        );
        $notExpiredOtherUnit = $this->createConvenio(
            'Consulta no vencido global',
            'Aprobado',
            'Colaboración',
            $context['otraUnidad'],
            $context['institucion'],
            true,
            null,
            today()->toDateString()
        );
        $localIds = [
            $firmado->id_instrumento,
            $rescindido->id_instrumento,
            $archivado->id_instrumento,
        ];

        $this->assertEqualsCanonicalizing($localIds, $this->listedIds([]));
        $this->assertSame([], $this->listedIds(['estado' => 'Borrador']));
        $this->assertSame([], $this->listedIds(['estado' => 'Revisión']));

        $context['usuario']->alcance_global = true;
        $context['usuario']->save();
        $this->actingAs($context['usuario']->fresh(), 'sanctum');
        $this->assertEqualsCanonicalizing(
            $localIds,
            $this->listedIds([])
        );
        $this->assertSame([], $this->listedIds(['estado' => 'Aprobado']));
        $this->assertNotContains($expiredBorrador->id_instrumento, $this->listedIds([]));
        $this->assertNotContains($expiredRevision->id_instrumento, $this->listedIds([]));
        $this->assertNotContains($expiredOtherUnit->id_instrumento, $this->listedIds([]));
        $this->assertNotContains(
            $notExpiredBorrador->id_instrumento,
            $this->listedIds([])
        );
        $this->assertNotContains(
            $notExpiredOtherUnit->id_instrumento,
            $this->listedIds([])
        );
    }

    public function test_administracion_sees_all_units_and_states_regardless_of_scope_setting(): void
    {
        $context = $this->authenticate('Administración', false);
        $states = [
            'Borrador',
            'EnCorreccion',
            'Revisión',
            'Aprobado',
            'Firmado',
            'Rescindido',
            'Rechazado',
            'Archivado',
        ];
        $expectedIds = [];

        foreach ($states as $index => $state) {
            $unitId = $index === 0
                ? $context['otraUnidad']
                : $context['unidadPrincipal'];

            $convenio = $this->createConvenio(
                'Administración '.$state,
                $state,
                'Colaboración',
                $unitId,
                $context['institucion'],
                true
            );
            $expectedIds[] = $convenio->id_instrumento;
        }

        $this->assertEqualsCanonicalizing($expectedIds, $this->listedIds([]));
    }

    public function test_scope_defaults_to_false_and_client_filters_cannot_expand_visibility(): void
    {
        $context = $this->authenticate('Gestión', null);
        $this->assertFalse($context['usuario']->fresh()->alcance_global);

        $ownBorrador = $this->createConvenio(
            'Filtro borrador propio',
            'Borrador',
            'Colaboración',
            $context['unidadPrincipal'],
            $context['institucion'],
            true
        );
        $otherBorrador = $this->createConvenio(
            'Filtro borrador ajeno',
            'Borrador',
            'Colaboración',
            $context['otraUnidad'],
            $context['institucion'],
            true
        );
        $ownRejected = $this->createConvenio(
            'Filtro rechazado propio',
            'Rechazado',
            'Colaboración',
            $context['unidadPrincipal'],
            $context['institucion'],
            true
        );

        $this->assertSame(
            [$ownBorrador->id_instrumento],
            $this->listedIds([
                'estado' => 'Borrador',
                'id_unidad_academica' => $context['unidadPrincipal'],
            ])
        );
        $this->assertSame(
            [],
            $this->listedIds([
                'estado' => 'Borrador',
                'id_unidad_academica' => $context['otraUnidad'],
            ])
        );
        $this->assertNotContains(
            $otherBorrador->id_instrumento,
            $this->listedIds([])
        );
        $this->assertNotContains(
            $ownRejected->id_instrumento,
            $this->listedIds([])
        );
    }

    private function listedIds(array $filters): array
    {
        $query = http_build_query($filters);
        $response = $this->getJson('/api/convenios'.($query === '' ? '' : '?'.$query));

        $response->assertOk();

        return collect($response->json('data'))
            ->pluck('id_instrumento')
            ->all();
    }

    private function createConvenio(
        string $titulo,
        string $estado,
        string $tipo,
        int $unitId,
        int $institutionId,
        bool $esMarco,
        ?int $idConvenioMarco = null,
        ?string $vencimiento = null
    ): Convenio {
        $instrumento = Instrumento::query()->create([
            'titulo' => $titulo,
            'estado' => $estado,
            'vencimiento' => $vencimiento,
        ]);

        $convenio = Convenio::query()->create([
            'id_instrumento' => $instrumento->id_instrumento,
            'tipo' => $tipo,
            'id_unidad_academica' => $unitId,
        ]);

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

        $convenio->institucionesFirmantes()->attach([$institutionId]);

        return $convenio;
    }

    private function createRepresentative(
        int $institutionId,
        string $nombre,
        string $apellido,
        string $cargo
    ): Representante {
        $persona = Persona::query()->create([
            'nombre' => $nombre,
            'apellido' => $apellido,
            'dni' => uniqid('dni-'),
        ]);

        return Representante::query()->create([
            'id_persona' => $persona->id_persona,
            'cargo' => $cargo,
            'id_institucion' => $institutionId,
        ])->load('persona');
    }

    private function authenticate(
        string $role = 'Administración',
        ?bool $alcanceGlobal = true
    ): array {
        $rolId = DB::table('rol')->insertGetId([
            'nombre' => $role,
        ]);
        $institutionId = DB::table('institucion_firmante')->insertGetId([
            'razon_social' => 'Institución de lectura de prueba',
        ]);
        $unitId = DB::table('unidad_academica')->insertGetId([
            'sigla' => 'UA-READ-01',
            'nombre' => 'Unidad de lectura principal',
            'id_institucion' => $institutionId,
        ]);
        $otherUnitId = DB::table('unidad_academica')->insertGetId([
            'sigla' => 'UA-READ-02',
            'nombre' => 'Unidad de lectura secundaria',
            'id_institucion' => $institutionId,
        ]);
        $usuarioData = [
            'mail' => 'lectura@example.com',
            'nombre' => 'Usuario de lectura',
            'password' => Hash::make('password-segura'),
            'activo' => true,
            'id_rol' => $rolId,
            'id_unidad' => $unitId,
        ];

        if ($alcanceGlobal !== null) {
            $usuarioData['alcance_global'] = $alcanceGlobal;
        }

        $usuario = Usuario::query()->create($usuarioData);

        $this->withToken($usuario->createToken('read-test')->plainTextToken);

        return [
            'institucion' => $institutionId,
            'unidadPrincipal' => $unitId,
            'otraUnidad' => $otherUnitId,
            'usuario' => $usuario,
        ];
    }
}
