<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }
};
