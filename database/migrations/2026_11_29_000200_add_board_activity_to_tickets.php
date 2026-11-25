<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('board_version_at')->nullable();   // kropka przy zleceniu po nowej wersji tablicy
            $table->unsignedBigInteger('board_version_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', fn (Blueprint $t) => $t->dropColumn(['board_version_at', 'board_version_by']));
    }
};
