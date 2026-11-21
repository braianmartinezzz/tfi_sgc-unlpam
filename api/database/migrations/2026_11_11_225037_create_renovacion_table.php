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
        Schema::create('renovacion', function (Blueprint $table) {
            $table->unsignedBigInteger('id_instrumento')->primary();

            $table->foreign('id_instrumento')
                ->references('id_instrumento')
                ->on('convenio');

            $table->date('fecha');
            $table->boolean('automatica')->default(false);

            $table->foreignId('id_instrumento_renovado')
                ->constrained('convenio', 'id_instrumento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('renovacion');
    }
};
