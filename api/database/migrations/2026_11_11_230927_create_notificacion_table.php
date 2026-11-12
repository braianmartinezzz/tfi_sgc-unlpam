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
        Schema::create('notificacion', function (Blueprint $table) {
            $table->id('id_notificacion');

            $table->string('tipo', 50);
            $table->text('mensaje');
            $table->date('fecha_relevante')->nullable();
            $table->dateTime('fecha_generacion')->useCurrent();
            $table->boolean('leida')->default(false);

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
        Schema::dropIfExists('notificacion');
    }
};
