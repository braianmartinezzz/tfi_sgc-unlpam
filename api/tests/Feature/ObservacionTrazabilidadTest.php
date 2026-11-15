<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\InstitucionFirmante;
use App\Models\Instrumento;
use App\Models\Observacion;
use App\Models\RegistroAuditoria;
use App\Models\Rol;
use App\Models\UnidadAcademica;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ObservacionTrazabilidadTest extends TestCase
{
    use RefreshDatabase;

    private int $number = 1;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/convenios/1/trazabilidad')->assertUnauthorized();
    }

    public function test_returns_observaciones_summary_and_movimientos_in_chronological_order(): void
    {
        $ctx = $this->createContext();
        $otro = $this->createConvenio($ctx['unit'], 'Otro convenio', 'Revisión');

        $this->createObservacion($ctx, 'Pendiente', '2026-11-10 09:05:00');
        $this->createObservacion($ctx, 'Resuelta', '2026-11-10 09:00:00');

        // Se cargan desordenados a propósito para comprobar el orden de la respuesta.
        $this->createAudit($ctx['convenio'], 'RESPUESTA_OBSERVACION', '2026-11-12 11:00:00', $ctx['manager']);
        $this->createAudit($ctx['convenio'], 'CREAR_OBSERVACION', '2026-11-10 09:05:00', $ctx['reviewer']);
        $this->createAudit($ctx['convenio'], 'ELIMINAR_OBSERVACION', '2026-11-11 10:00:00', $ctx['reviewer'], 'Observación #9 eliminada.');
        $this->createAudit($ctx['convenio'], 'CREAR_OBSERVACION', '2026-11-10 09:00:00', $ctx['reviewer']);

        // No deben aparecer: otra acción del mismo convenio y una acción de otro convenio.
        $this->createAudit($ctx['convenio'], 'SOLICITUD_REVISION', '2026-11-09 08:00:00', $ctx['manager']);
        $this->createAudit($otro, 'CREAR_OBSERVACION', '2026-11-10 09:00:00', $ctx['reviewer']);

        $this->authenticate($ctx['reviewer']);

        $this->getJson("/api/convenios/{$ctx['convenio']->id_instrumento}/trazabilidad")
            ->assertOk()
            ->assertJsonPath('data.id_instrumento', $ctx['convenio']->id_instrumento)
            ->assertJsonPath('data.resumen.total', 2)
            ->assertJsonPath('data.resumen.pendientes', 1)
            ->assertJsonPath('data.resumen.resueltas', 1)
            ->assertJsonCount(2, 'data.observaciones')
            ->assertJsonPath('data.observaciones.0.estado_tramite', 'Resuelta')
            ->assertJsonPath('data.observaciones.0.respuesta', 'Respuesta de Gestión.')
            ->assertJsonPath('data.observaciones.0.usuario.id_usuario', $ctx['reviewer']->id_usuario)
            ->assertJsonPath('data.observaciones.1.estado_tramite', 'Pendiente')
            ->assertJsonCount(4, 'data.movimientos')
            ->assertJsonPath('data.movimientos.0.accion', 'CREAR_OBSERVACION')
            ->assertJsonPath('data.movimientos.1.accion', 'CREAR_OBSERVACION')
            ->assertJsonPath('data.movimientos.2.accion', 'ELIMINAR_OBSERVACION')
            ->assertJsonPath('data.movimientos.2.detalle', 'Observación #9 eliminada.')
            ->assertJsonPath('data.movimientos.3.accion', 'RESPUESTA_OBSERVACION')
            ->assertJsonPath('data.movimientos.3.usuario.id_usuario', $ctx['manager']->id_usuario);
    }

    public function test_returns_empty_collections_for_a_convenio_without_observaciones(): void
    {
        $ctx = $this->createContext();
        $this->authenticate($ctx['reviewer']);

        $this->getJson("/api/convenios/{$ctx['convenio']->id_instrumento}/trazabilidad")
            ->assertOk()
            ->assertJsonPath('data.resumen.total', 0)
            ->assertJsonPath('data.resumen.pendientes', 0)
            ->assertJsonPath('data.resumen.resueltas', 0)
            ->assertJsonCount(0, 'data.observaciones')
            ->assertJsonCount(0, 'data.movimientos');
    }

    public function test_returns_not_found_for_a_missing_convenio(): void
    {
        $ctx = $this->createContext();
        $this->authenticate($ctx['reviewer']);

        $this->getJson('/api/convenios/999999/trazabilidad')
            ->assertNotFound()
            ->assertJsonPath('message', 'Convenio no encontrado.');
    }

    public function test_gestion_of_the_same_unit_and_administracion_can_consult(): void
    {
        $ctx = $this->createContext();
        $this->createObservacion($ctx, 'Pendiente', '2026-11-10 09:00:00');
        $admin = $this->createUser('Administración', $this->createUnit()->id_unidad_academica);

        $this->authenticate($ctx['manager']);
        $this->getJson("/api/convenios/{$ctx['convenio']->id_instrumento}/trazabilidad")
            ->assertOk()
            ->assertJsonPath('data.resumen.total', 1);

        $this->authenticate($admin);
        $this->getJson("/api/convenios/{$ctx['convenio']->id_instrumento}/trazabilidad")
            ->assertOk()
            ->assertJsonPath('data.resumen.total', 1);
    }

    public function test_gestion_from_another_unit_and_consulta_are_forbidden(): void
    {
        $ctx = $this->createContext();
        $gestionOtraUnidad = $this->createUser('Gestión', $this->createUnit()->id_unidad_academica);
        $consulta = $this->createUser('Consulta', $ctx['unit']->id_unidad_academica);

        $this->authenticate($gestionOtraUnidad);
        $this->getJson("/api/convenios/{$ctx['convenio']->id_instrumento}/trazabilidad")
            ->assertForbidden();

        $this->authenticate($consulta);
        $this->getJson("/api/convenios/{$ctx['convenio']->id_instrumento}/trazabilidad")
            ->assertForbidden();
    }

    private function createContext(): array
    {
        $unit = $this->createUnit();

        return [
            'unit' => $unit,
            'manager' => $this->createUser('Gestión', $unit->id_unidad_academica),
            'reviewer' => $this->createUser('Revisión', $unit->id_unidad_academica),
            'convenio' => $this->createConvenio($unit, 'Convenio con observaciones', 'Revisión'),
        ];
    }

    private function createConvenio(UnidadAcademica $unit, string $titulo, string $estado): Convenio
    {
        $instrumento = Instrumento::query()->create([
            'titulo' => $titulo,
            'estado' => $estado,
        ]);

        return Convenio::query()->create([
            'id_instrumento' => $instrumento->id_instrumento,
            'tipo' => 'Colaboración',
            'id_unidad_academica' => $unit->id_unidad_academica,
        ]);
    }

    private function createObservacion(array $ctx, string $estado, string $fecha): Observacion
    {
        $resuelta = $estado === 'Resuelta';

        return Observacion::query()->create([
            'contenido' => 'Actualizar la cláusula de vigencia.',
            'fecha' => $fecha,
            'estado_tramite' => $estado,
            'resolucion' => $resuelta ? 'Aceptada' : null,
            'respuesta' => $resuelta ? 'Respuesta de Gestión.' : null,
            'fecha_resolucion' => $resuelta ? '2026-11-12 11:00:00' : null,
            'id_instrumento' => $ctx['convenio']->id_instrumento,
            'id_usuario' => $ctx['reviewer']->id_usuario,
        ]);
    }

    private function createAudit(
        Convenio $convenio,
        string $accion,
        string $fechaHora,
        Usuario $usuario,
        ?string $detalle = null
    ): RegistroAuditoria {
        return RegistroAuditoria::query()->create([
            'accion' => $accion,
            'fecha_hora' => $fechaHora,
            'detalle' => $detalle ?? 'Detalle de '.$accion,
            'id_instrumento' => $convenio->id_instrumento,
            'id_usuario' => $usuario->id_usuario,
        ]);
    }

    private function createUnit(): UnidadAcademica
    {
        $institution = InstitucionFirmante::query()->create([
            'razon_social' => 'Institución '.$this->number++,
        ]);

        return UnidadAcademica::query()->create([
            'sigla' => 'TRZ-'.$this->number,
            'nombre' => 'Unidad '.$this->number++,
            'id_institucion' => $institution->id_institucion,
        ]);
    }

    private function createUser(string $roleName, int $unitId): Usuario
    {
        $role = Rol::query()->firstOrCreate(['nombre' => $roleName]);

        return Usuario::query()->create([
            'mail' => 'trazabilidad-'.$this->number++.'@example.com',
            'nombre' => 'Usuario '.$roleName.' '.$this->number,
            'password' => Hash::make('password-segura'),
            'activo' => true,
            'id_rol' => $role->id_rol,
            'id_unidad' => $unitId,
            'alcance_global' => false,
        ]);
    }

    private function authenticate(Usuario $usuario): void
    {
        Auth::forgetGuards();
        $this->withToken($usuario->createToken('test')->plainTextToken);
    }
}
