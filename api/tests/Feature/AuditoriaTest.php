<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Instrumento;
use App\Models\Marco;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuditoriaTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/auditoria')->assertUnauthorized();
    }

    public function test_lists_newest_first_with_usuario_and_instrumento(): void
    {
        $this->authenticateWithLog();

        $this->getJson('/api/auditoria')
            ->assertOk()
            ->assertJsonPath('total', 4)
            ->assertJsonPath('data.0.accion', 'Inicio de sesión')
            ->assertJsonPath('data.0.usuario.nombre', 'Beto Gómez')
            ->assertJsonPath('data.0.instrumento', null)
            ->assertJsonPath('data.3.accion', 'Creación')
            ->assertJsonPath('data.3.instrumento.titulo', 'Convenio X')
            ->assertJsonMissingPath('data.0.usuario.password');
    }

    public function test_filters_by_usuario_and_instrumento(): void
    {
        $ctx = $this->authenticateWithLog();

        $this->getJson('/api/auditoria?id_usuario='.$ctx['beto']->id_usuario)
            ->assertOk()
            ->assertJsonPath('total', 2);

        $this->getJson('/api/auditoria?id_instrumento='.$ctx['convenioX'])
            ->assertOk()
            ->assertJsonPath('total', 2);
    }

    public function test_filters_by_accion_text_and_date_range(): void
    {
        $this->authenticateWithLog();

        $this->getJson('/api/auditoria?'.http_build_query(['accion' => 'Eliminación']))
            ->assertOk()
            ->assertJsonPath('total', 1);

        $this->getJson('/api/auditoria?'.http_build_query(['buscar' => 'convenio']))
            ->assertOk()
            ->assertJsonPath('total', 2);

        $this->getJson('/api/auditoria?desde=2026-11-05&hasta=2026-11-10')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('data.0.accion', 'Eliminación')
            ->assertJsonPath('data.1.accion', 'Modificación');
    }

    public function test_paginates_with_per_page(): void
    {
        $this->authenticateWithLog();

        $this->getJson('/api/auditoria?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('total', 4)
            ->assertJsonPath('last_page', 2);
    }

    public function test_rejects_invalid_filters(): void
    {
        $this->authenticateWithLog();

        $this->getJson('/api/auditoria?desde=2026-11-10&hasta=2026-11-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['hasta']);

        $this->getJson('/api/auditoria?per_page=500')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_registrar_creates_an_audit_record(): void
    {
        $ctx = $this->authenticateWithLog();

        $registro = app(AuditoriaService::class)->registrar(
            'Prueba de registro',
            $ctx['admin']->id_usuario,
            $ctx['convenioX'],
            'Detalle de prueba'
        );

        $this->assertDatabaseHas('registro_auditoria', [
            'id_registro' => $registro->id_registro,
            'accion' => 'Prueba de registro',
            'id_usuario' => $ctx['admin']->id_usuario,
            'id_instrumento' => $ctx['convenioX'],
        ]);

        $this->getJson('/api/auditoria?'.http_build_query(['buscar' => 'Prueba de registro']))
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    private function crearUsuario(int $rolId, int $unidadId, string $nombre, string $mail): Usuario
    {
        return Usuario::query()->create([
            'mail' => $mail,
            'nombre' => $nombre,
            'password' => Hash::make('password-segura'),
            'activo' => true,
            'id_rol' => $rolId,
            'id_unidad' => $unidadId,
        ]);
    }

    private function crearConvenio(string $titulo, int $unidadId): int
    {
        $instrumento = Instrumento::query()->create([
            'titulo' => $titulo,
            'estado' => 'Borrador',
        ]);

        Convenio::query()->create([
            'id_instrumento' => $instrumento->id_instrumento,
            'tipo' => 'Colaboración',
            'id_unidad_academica' => $unidadId,
        ]);

        Marco::query()->create([
            'id_instrumento' => $instrumento->id_instrumento,
        ]);

        return $instrumento->id_instrumento;
    }

    /**
     * Crea un administrador autenticado, otro usuario, dos convenios y 4 registros de auditoría:
     *  1) Creación (admin, convenio X)        2026-11-01 10:00
     *  2) Modificación (Beto, convenio X)     2026-11-05 09:30
     *  3) Eliminación (admin, sin convenio)   2026-11-10 18:00
     *  4) Inicio de sesión (Beto, sin conv.)  2026-11-12 08:00
     */
    private function authenticateWithLog(): array
    {
        $rolId = DB::table('rol')->insertGetId(['nombre' => 'Administración']);
        $institucionId = DB::table('institucion_firmante')->insertGetId([
            'razon_social' => 'Institución de prueba',
        ]);
        $unidadId = DB::table('unidad_academica')->insertGetId([
            'sigla' => 'UA-AUD',
            'nombre' => 'Unidad de auditoría',
            'id_institucion' => $institucionId,
        ]);

        $admin = $this->crearUsuario($rolId, $unidadId, 'Administrador de pruebas', 'admin-auditoria@example.com');
        $beto = $this->crearUsuario($rolId, $unidadId, 'Beto Gómez', 'beto-auditoria@example.com');

        $convenioX = $this->crearConvenio('Convenio X', $unidadId);

        DB::table('registro_auditoria')->insert([
            [
                'accion' => 'Creación',
                'fecha_hora' => '2026-11-01 10:00:00',
                'detalle' => 'Alta del convenio X',
                'id_instrumento' => $convenioX,
                'id_usuario' => $admin->id_usuario,
            ],
            [
                'accion' => 'Modificación',
                'fecha_hora' => '2026-11-05 09:30:00',
                'detalle' => 'Cambio de objeto',
                'id_instrumento' => $convenioX,
                'id_usuario' => $beto->id_usuario,
            ],
            [
                'accion' => 'Eliminación',
                'fecha_hora' => '2026-11-10 18:00:00',
                'detalle' => 'Baja de convenio Y',
                'id_instrumento' => null,
                'id_usuario' => $admin->id_usuario,
            ],
            [
                'accion' => 'Inicio de sesión',
                'fecha_hora' => '2026-11-12 08:00:00',
                'detalle' => null,
                'id_instrumento' => null,
                'id_usuario' => $beto->id_usuario,
            ],
        ]);

        $this->withToken($admin->createToken('auditoria-test')->plainTextToken);

        return ['admin' => $admin, 'beto' => $beto, 'convenioX' => $convenioX];
    }
}
