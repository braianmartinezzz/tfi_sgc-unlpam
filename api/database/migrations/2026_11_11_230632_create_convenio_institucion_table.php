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
        Schema::create('convenio_institucion', function (Blueprint $table) {
            $table->unsignedBigInteger('id_instrumento');
            $table->unsignedBigInteger('id_institucion');

            $table->primary(['id_instrumento', 'id_institucion']);

            $table->foreign('id_instrumento')
                ->references('id_instrumento')
                ->on('convenio');

            $table->foreign('id_institucion')
                ->references('id_institucion')
                ->on('institucion_firmante');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('convenio_institucion');
    }
};
