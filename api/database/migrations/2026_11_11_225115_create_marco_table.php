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
        Schema::create('marco', function (Blueprint $table) {
            $table->unsignedBigInteger('id_instrumento')->primary();

            $table->foreign('id_instrumento')
                ->references('id_instrumento')
                ->on('convenio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marco');
    }
};
