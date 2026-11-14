<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documento', function (Blueprint $table) {
            $table->boolean('es_documento_firmado')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('documento', function (Blueprint $table) {
            $table->dropColumn('es_documento_firmado');
        });
    }
};
