<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institucion_firmante', function (Blueprint $table) {
            $table->boolean('activo')->default(true);
            $table->boolean('es_propia')->default(false);
        });

        Schema::table('unidad_academica', function (Blueprint $table) {
            $table->boolean('activo')->default(true);
        });

        Schema::table('representante', function (Blueprint $table) {
            $table->boolean('activo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('representante', function (Blueprint $table) {
            $table->dropColumn('activo');
        });

        Schema::table('unidad_academica', function (Blueprint $table) {
            $table->dropColumn('activo');
        });

        Schema::table('institucion_firmante', function (Blueprint $table) {
            $table->dropColumn(['activo', 'es_propia']);
        });
    }
};
