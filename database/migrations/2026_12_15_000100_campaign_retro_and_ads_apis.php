<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Podsumowanie kampanii po zakończeniu (wnioski) — wewnętrzne albo udostępnione klientowi. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->text('retro_worked')->nullable();     // co zadziałało
            $table->text('retro_failed')->nullable();     // co nie zadziałało
            $table->text('retro_next')->nullable();       // co zrobić następnym razem
            $table->boolean('retro_shared')->default(false); // widoczne dla klienta
            $table->foreignId('retro_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('retro_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('retro_by');
            $table->dropColumn(['retro_worked', 'retro_failed', 'retro_next', 'retro_shared', 'retro_at']);
        });
    }
};
