<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('usuario', 'id_persona')) {
            Schema::table('usuario', function (Blueprint $table) {
                $table->unsignedBigInteger('id_persona')->nullable()->unique();
            });
        }

        if (! Schema::hasColumn('firma', 'trazo_ruta')) {
            Schema::table('firma', function (Blueprint $table) {
                $table->string('trazo_ruta', 255)->nullable();
            });
        }

        if (! Schema::hasColumn('firma', 'biometria_confirmada')) {
            Schema::table('firma', function (Blueprint $table) {
                $table->boolean('biometria_confirmada')->default(false);
            });
        }
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
