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
        Schema::create('docente', function (Blueprint $table) {
            $table->unsignedBigInteger('id_persona')->primary();

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
        Schema::dropIfExists('docente');
    }
};
