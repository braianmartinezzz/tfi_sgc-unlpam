<?php

namespace App\Providers;

use App\Models\Convenio;
use App\Models\Usuario;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define(
            'administrar-catalogos',
            fn ($user) => $user instanceof Usuario && $user->rol?->nombre === 'Administración'
                ? true
                : Response::deny('No tenés permiso para administrar catálogos.')
        );

        Gate::define(
            'editar-institucion',
            function ($user, $idInstitucion) {
                if (! $user instanceof Usuario || ! is_numeric($idInstitucion)) {
                    return Response::deny('No tenés permiso para editar esta institución.');
                }

                if ($user->rol?->nombre === 'Administración') {
                    return true;
                }

                return $user->rol?->nombre === 'Gestión'
                    && (int) $user->unidadAcademica?->id_institucion === (int) $idInstitucion
                    ? true
                    : Response::deny('No tenés permiso para editar esta institución.');
            }
        );

        Gate::define(
            'editar-unidad',
            function ($user, $idUnidad) {
                if (! $user instanceof Usuario || ! is_numeric($idUnidad)) {
                    return Response::deny('No tenés permiso para editar esta unidad académica.');
                }

                return in_array($user->rol?->nombre, ['Administración'], true)
                    || (
                        $user->rol?->nombre === 'Gestión'
                        && (int) $user->id_unidad === (int) $idUnidad
                    )
                    ? true
                    : Response::deny('No tenés permiso para editar esta unidad académica.');
            }
        );

        Gate::define(
            'gestionar-usuarios',
            function ($user): bool {
                return $user instanceof Usuario
                    && $user->rol?->nombre === 'Administración';
            }
        );

        Gate::define(
            'gestionar-convenio',
            function ($user, $idUnidadAcademica): bool {
                if (! $user instanceof Usuario || ! is_numeric($idUnidadAcademica)) {
                    return false;
                }

                $rol = $user->rol;

                if ($rol?->nombre === 'Administración') {
                    return true;
                }

                return $rol?->nombre === 'Gestión'
                    && (int) $user->id_unidad === (int) $idUnidadAcademica;
            }
        );

        Gate::define(
            'enviar-convenio-a-revision',
            function ($user, Convenio $convenio): bool {
                if (! $user instanceof Usuario) {
                    return false;
                }

                $rol = $user->rol;

                if ($rol?->nombre === 'Administración') {
                    return true;
                }

                return $rol?->nombre === 'Gestión'
                    && (int) $user->id_unidad === (int) $convenio->id_unidad_academica;
            }
        );
    }
}
