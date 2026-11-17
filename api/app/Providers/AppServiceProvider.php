<?php

namespace App\Providers;

use App\Models\Convenio;
use App\Models\Usuario;
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
