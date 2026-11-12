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
        Schema::create('observacion', function (Blueprint $table) {
            $table->id('id_observacion');

            $table->text('contenido');
            $table->dateTime('fecha')->useCurrent();
            $table->string('estado_tramite', 30)->nullable();
            $table->string('resolucion', 50)->nullable();
            $table->text('respuesta')->nullable();
            $table->dateTime('fecha_resolucion')->nullable();

            $table->foreignId('id_instrumento')
                ->constrained('convenio', 'id_instrumento');

            $table->foreignId('id_usuario')
                ->constrained('usuario', 'id_usuario');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('observacion');
    }
};
