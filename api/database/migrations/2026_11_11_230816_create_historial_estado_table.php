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
        Schema::create('historial_estado', function (Blueprint $table) {
            $table->id('id_historial');

            $table->string('estado_anterior', 30);
            $table->string('estado_nuevo', 30);
            $table->dateTime('fecha_hora')->useCurrent();
            $table->text('motivo')->nullable();

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
        Schema::dropIfExists('historial_estado');
    }
};
