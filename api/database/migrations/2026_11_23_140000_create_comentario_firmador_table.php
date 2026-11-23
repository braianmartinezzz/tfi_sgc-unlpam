<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comentario_firmador', function (Blueprint $table) {
            $table->id('id_comentario');
            $table->foreignId('id_instrumento')->constrained('instrumento', 'id_instrumento')->cascadeOnDelete();
            $table->foreignId('id_usuario')->constrained('usuario', 'id_usuario')->cascadeOnDelete();
            $table->text('contenido');
            $table->boolean('ofensivo')->default(false);
            $table->dateTime('fecha')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comentario_firmador');
    }
};
