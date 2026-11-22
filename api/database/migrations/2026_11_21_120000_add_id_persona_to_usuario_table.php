<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuario', function (Blueprint $table) {
            $table->foreignId('id_persona')
                ->nullable()
                ->unique()
                ->constrained('persona', 'id_persona');
        });
    }

    public function down(): void
    {
        Schema::table('usuario', function (Blueprint $table) {
            $table->dropForeign(['id_persona']);
            $table->dropUnique(['id_persona']);
            $table->dropColumn('id_persona');
        });
    }
};
