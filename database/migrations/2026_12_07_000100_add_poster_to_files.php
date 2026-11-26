<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Kadr filmu (JPEG zrobiony w przeglądarce przy wgrywaniu) — miniatura działa w każdej przeglądarce, także dla .mov. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->foreignId('poster_id')->nullable()->constrained('files')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('files', fn (Blueprint $t) => $t->dropConstrainedForeignId('poster_id'));
    }
};
