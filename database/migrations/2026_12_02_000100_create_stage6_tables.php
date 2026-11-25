<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Etap 6: kartoteka z szyfrowanymi dostępami, podglądy wersji tablic, paczki eksportu. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            $table->string('url')->nullable();
            $table->text('username')->nullable(); // szyfrowane
            $table->text('secret')->nullable();   // szyfrowane
            $table->text('notes')->nullable();    // szyfrowane
            $table->timestamps();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->text('arrangements')->nullable(); // ustalenia ze współpracy
        });

        Schema::table('board_versions', function (Blueprint $table) {
            $table->foreignId('preview_usage_id')->nullable()->constrained('file_usages')->nullOnDelete();
        });

        Schema::create('export_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('kind', 16);  // ticket | client | offline
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('size')->default(0);
            $table->json('ticket_ids')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_packages');
        Schema::table('board_versions', fn (Blueprint $t) => $t->dropConstrainedForeignId('preview_usage_id'));
        Schema::table('clients', fn (Blueprint $t) => $t->dropColumn('arrangements'));
        Schema::dropIfExists('client_credentials');
    }
};
