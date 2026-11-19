<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Persona firmante que representa a un usuario (Rector/a, Decano/a, representantes).
        Schema::table('usuario', function (Blueprint $table) {
            $table->unsignedBigInteger('id_persona')->nullable()->unique();
        });

        // Evidencia de cada firma: imagen del trazo y constancia de la confirmación biométrica.
        Schema::table('firma', function (Blueprint $table) {
            $table->string('trazo_ruta', 255)->nullable();
            $table->boolean('biometria_confirmada')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('firma', function (Blueprint $table) {
            $table->dropColumn(['trazo_ruta', 'biometria_confirmada']);
        });

        Schema::table('usuario', function (Blueprint $table) {
            $table->dropUnique(['id_persona']);
            $table->dropColumn('id_persona');
        });
    }
};
