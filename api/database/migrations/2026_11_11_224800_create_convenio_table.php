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
        Schema::create('convenio', function (Blueprint $table) {
            $table->unsignedBigInteger('id_instrumento')->primary();

            $table->string('tipo', 50);
            $table->text('alcance')->nullable();
            $table->text('obligaciones')->nullable();
            $table->text('condiciones_recision')->nullable();
            $table->text('marco_legal')->nullable();
            $table->text('condicion_renovacion')->nullable();
            $table->boolean('confidencialidad')->nullable()->default(false);

            $table->foreign('id_instrumento')
                ->references('id_instrumento')
                ->on('instrumento');

            $table->foreignId('id_unidad_academica')
                ->constrained('unidad_academica', 'id_unidad_academica');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('convenio');
    }
};
