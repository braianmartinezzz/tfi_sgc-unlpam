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
        Schema::create('instrumento', function (Blueprint $table) {
            $table->id('id_instrumento');

            $table->string('titulo', 255);
            $table->text('objeto')->nullable();
            $table->string('estado', 30);

            $table->date('fecha_firma')->nullable();
            $table->date('inicio')->nullable();
            $table->date('vencimiento')->nullable();

            $table->integer('dias_preaviso')->nullable();
            $table->string('expediente', 100)->nullable();
            $table->string('resolucion', 100)->nullable();
            $table->text('notas')->nullable();

            $table->integer('version_registro')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instrumento');
    }
};
