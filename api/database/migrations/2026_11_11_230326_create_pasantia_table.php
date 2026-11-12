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
        Schema::create('pasantia', function (Blueprint $table) {
            $table->id('id_pasantia');

            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->integer('carga_horaria');
            $table->decimal('asignacion_estimulo', 12, 2);
            $table->decimal('porcentaje_gasto_admin', 5, 2)->nullable();
            $table->string('lugar', 255)->nullable();
            $table->text('tareas')->nullable();
            $table->string('seguro', 150)->nullable();

            $table->foreignId('id_convenio_especifico')
                ->constrained('especifico', 'id_instrumento');

            $table->foreignId('id_estudiante')
                ->constrained('estudiante', 'id_persona');

            $table->foreignId('id_docente_guia')
                ->constrained('docente', 'id_persona');

            $table->foreignId('id_tutor_laboral')
                ->constrained('representante', 'id_persona');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pasantia');
    }
};
