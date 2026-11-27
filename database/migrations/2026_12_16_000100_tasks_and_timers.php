<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Zadania i czas pracy: szacunek czasu zlecenia, stoper osoby, praca rozliczalna, stawka godzinowa klienta. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedInteger('estimate_minutes')->nullable();
        });
        Schema::table('users', function (Blueprint $table) {
            // Jeden działający stoper na osobę (bez stałych procesów: czas liczymy z różnicy dat).
            $table->foreignId('timer_ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->timestamp('timer_started_at')->nullable();
        });
        Schema::table('time_entries', function (Blueprint $table) {
            $table->boolean('billable')->default(true);
            $table->string('source', 8)->default('manual'); // manual | timer
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->decimal('hourly_rate', 8, 2)->nullable(); // puste = stawka domyślna z ustawień
        });
    }

    public function down(): void
    {
        Schema::table('clients', fn (Blueprint $t) => $t->dropColumn('hourly_rate'));
        Schema::table('time_entries', fn (Blueprint $t) => $t->dropColumn(['billable', 'source']));
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('timer_ticket_id');
            $t->dropColumn('timer_started_at');
        });
        Schema::table('tickets', fn (Blueprint $t) => $t->dropColumn('estimate_minutes'));
    }
};
