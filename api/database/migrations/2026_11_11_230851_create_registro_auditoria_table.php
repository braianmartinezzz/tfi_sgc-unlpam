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
        Schema::create('registro_auditoria', function (Blueprint $table) {
            $table->id('id_registro');

            $table->string('accion', 100);
            $table->dateTime('fecha_hora')->useCurrent();
            $table->text('detalle')->nullable();

            $table->foreignId('id_instrumento')
                ->nullable()
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
        Schema::dropIfExists('registro_auditoria');
    }
};
