<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('unidad_academica', function (Blueprint $table) {
            $table->id('id_unidad_academica');
            $table->string('sigla', 20);
            $table->string('nombre', 150);

            $table->foreignId('id_institucion')
                ->constrained('institucion_firmante', 'id_institucion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unidad_academica');
    }
};
