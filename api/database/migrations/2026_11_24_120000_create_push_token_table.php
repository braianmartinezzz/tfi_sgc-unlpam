<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_token', function (Blueprint $table) {
            $table->id('id_push_token');
            $table->foreignId('id_usuario')->constrained('usuario', 'id_usuario')->cascadeOnDelete();
            $table->string('token', 255)->unique();
            $table->string('dispositivo', 100)->nullable();
            $table->dateTime('ultimo_uso')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_token');
    }
};
