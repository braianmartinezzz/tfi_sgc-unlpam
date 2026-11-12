<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Especifico;
use App\Models\HistorialEstado;
use App\Models\Instrumento;
use App\Models\Marco;
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

        HistorialEstado::create([
            'estado_anterior' => null,
            'estado_nuevo' => 'Borrador',
            'motivo' => 'Creación del convenio',
            'id_instrumento' => $especifico->id_instrumento,
            'id_usuario' => $context['usuario']->id_usuario,
        ]);

        $this->getJson('/api/convenios/'.$especifico->id_instrumento)
            ->assertOk()
            ->assertJsonPath('data.instrumento.titulo', 'Convenio específico')
            ->assertJsonPath('data.unidad_academica.id_unidad_academica', $context['unidadPrincipal'])
            ->assertJsonPath('data.instituciones_firmantes.0.id_institucion', $context['institucion'])
            ->assertJsonPath(
                'data.especifico.marco.instrumento.titulo',
                'Convenio marco padre'
            )
            ->assertJsonPath('data.historial_estados.0.estado_nuevo', 'Borrador');
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
        ?int $idConvenioMarco = null
    ): Convenio {
        $instrumento = Instrumento::query()->create([
            'titulo' => $titulo,
            'estado' => $estado,
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

    private function authenticate(): array
    {
        $rolId = DB::table('rol')->insertGetId([
            'nombre' => 'Consulta',
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
        $usuario = Usuario::query()->create([
            'mail' => 'lectura@example.com',
            'nombre' => 'Usuario de lectura',
            'password' => Hash::make('password-segura'),
            'activo' => true,
            'id_rol' => $rolId,
            'id_unidad' => $unitId,
        ]);

        $this->withToken($usuario->createToken('read-test')->plainTextToken);

        return [
            'institucion' => $institutionId,
            'unidadPrincipal' => $unitId,
            'otraUnidad' => $otherUnitId,
            'usuario' => $usuario,
        ];
    }
}
