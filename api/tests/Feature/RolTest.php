<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RolTest extends TestCase
{
    use RefreshDatabase;

    public function test_administration_can_list_all_roles_ordered_by_name(): void
    {
        $rolesByName = [];

        foreach (['Revisión', 'Gestión', 'Consulta', 'Administración'] as $nombre) {
            $rolesByName[$nombre] = Rol::query()->create(['nombre' => $nombre]);
        }

        $this->authenticateAs('Administración');

        $expected = array_map(
            fn (string $nombre): array => [
                'id_rol' => $rolesByName[$nombre]->id_rol,
                'nombre' => $nombre,
            ],
            ['Administración', 'Consulta', 'Gestión', 'Revisión']
        );

        $this->getJson('/api/roles')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id_rol', 'nombre'],
                ],
            ])
            ->assertExactJson(['data' => $expected]);
    }

    public function test_gestion_cannot_list_roles(): void
    {
        $this->assertRoleCannotListRoles('Gestión');
    }

    public function test_revision_cannot_list_roles(): void
    {
        $this->assertRoleCannotListRoles('Revisión');
    }

    public function test_consulta_cannot_list_roles(): void
    {
        $this->assertRoleCannotListRoles('Consulta');
    }

    public function test_listing_roles_requires_authentication(): void
    {
        $this->getJson('/api/roles')->assertUnauthorized();
    }

    private function assertRoleCannotListRoles(string $roleName): void
    {
        $this->authenticateAs($roleName);

        $this->getJson('/api/roles')->assertForbidden();
    }

    private function authenticateAs(string $roleName): Usuario
    {
        $role = Rol::query()->firstOrCreate(['nombre' => $roleName]);
        $institutionId = DB::table('institucion_firmante')->insertGetId([
            'razon_social' => 'Institución de prueba',
        ]);
        $unitId = DB::table('unidad_academica')->insertGetId([
            'sigla' => 'UA-ROL',
            'nombre' => 'Unidad de prueba',
            'id_institucion' => $institutionId,
        ]);
        $mailName = strtolower(str_replace(['ó', ' '], ['o', '-'], $roleName));
        $usuario = Usuario::query()->create([
            'mail' => $mailName.'@example.com',
            'nombre' => 'Usuario de prueba',
            'password' => Hash::make('password-segura'),
            'activo' => true,
            'id_rol' => $role->id_rol,
            'id_unidad' => $unitId,
        ]);

        $this->withToken($usuario->createToken('roles-test')->plainTextToken);

        return $usuario;
    }
}
