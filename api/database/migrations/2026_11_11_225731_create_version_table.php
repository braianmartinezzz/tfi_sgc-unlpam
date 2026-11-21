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
        Schema::create('version', function (Blueprint $table) {
            $table->id('id_version');

            $table->integer('numero')->default(1);
            $table->string('nombre_archivo', 255);
            $table->bigInteger('tamanio_bytes');
            $table->char('hash_sha256', 64);
            $table->string('ruta_interna', 500);
            $table->dateTime('fecha_carga')->useCurrent();

            $table->foreignId('id_documento')
                ->constrained('documento', 'id_documento');

            $table->foreignId('id_usuario')
                ->constrained('usuario', 'id_usuario');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('version');
    }
};
