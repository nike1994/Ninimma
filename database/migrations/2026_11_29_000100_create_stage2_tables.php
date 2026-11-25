<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Etap 2: tablice (Excalidraw), wersje, wyceny z limitem, odbiór, dokumenty kosztowe, regulamin. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->longText('elements')->nullable();   // JSON: id => element
            $table->longText('revisions')->nullable();  // JSON: id => numer zmiany
            $table->longText('app_state')->nullable();
            $table->unsignedBigInteger('revision')->default(0); // licznik zmian
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('board_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('elements');
            $table->string('description')->nullable();
            $table->boolean('is_auto')->default(false); // po zakończeniu sesji edycji
            $table->unsignedBigInteger('revision');
            $table->timestamp('created_at')->useCurrent();
        });

        // Obrazy wklejone na tablicę: id pliku Excalidraw → plik w magazynie.
        Schema::create('board_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->string('excalidraw_id', 100);
            $table->foreignId('file_usage_id')->constrained()->cascadeOnDelete();
            $table->string('mime', 64);
            $table->unique(['board_id', 'excalidraw_id']);
        });

        Schema::create('board_presence', function (Blueprint $table) {
            $table->foreignId('board_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('seen_at');
            $table->primary(['board_id', 'user_id']);
        });

        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->decimal('hours_limit', 6, 2)->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->text('justification')->nullable();
            $table->json('options')->nullable();           // [{label, description, hours, amount}]
            $table->boolean('is_diagnosis')->default(false); // płatna diagnoza z limitem
            $table->string('status', 16)->default('pending'); // pending | accepted | rejected | superseded
            $table->unsignedSmallInteger('chosen_option')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cost_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 64);
            $table->decimal('amount', 10, 2);
            $table->date('issued_on');
            $table->date('due_on')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->date('paid_on')->nullable();
            $table->foreignId('file_usage_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->longText('value')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('terms_accepted_at')->nullable();
            $table->unsignedSmallInteger('terms_version')->nullable();
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('handover_requested_at')->nullable(); // prośba o odbiór
            $table->timestamp('accepted_at')->nullable();           // odbiór przez klienta
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', fn (Blueprint $t) => [$t->dropConstrainedForeignId('accepted_by'), $t->dropColumn(['handover_requested_at', 'accepted_at'])]);
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['terms_accepted_at', 'terms_version']));
        foreach (['settings', 'cost_documents', 'quotes', 'board_presence', 'board_files', 'board_versions', 'boards'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
