<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Link docelowy publikacji (Pinterest: przycisk „Odwiedź”). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            $table->string('link', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('publications', fn (Blueprint $t) => $t->dropColumn('link'));
    }
};
