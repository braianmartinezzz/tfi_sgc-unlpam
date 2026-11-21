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
        Schema::create('firma', function (Blueprint $table) {
            $table->unsignedBigInteger('id_instrumento');
            $table->unsignedBigInteger('id_persona');

            $table->dateTime('fecha_hora_firma')->nullable();
            $table->decimal('latitud', 9, 6)->nullable();
            $table->decimal('longitud', 9, 6)->nullable();

            $table->primary(['id_instrumento', 'id_persona']);

            $table->foreign('id_instrumento')
                ->references('id_instrumento')
                ->on('convenio');

            $table->foreign('id_persona')
                ->references('id_persona')
                ->on('persona');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('firma');
    }
};
