<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Zdjęcie profilu konta (wgrane albo pobrane z platformy) — do podglądu postów i list kont. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->foreignId('avatar_usage_id')->nullable()->constrained('file_usages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('social_accounts', fn (Blueprint $t) => $t->dropConstrainedForeignId('avatar_usage_id'));
    }
};
