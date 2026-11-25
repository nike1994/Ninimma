<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Etap 3: plan publikacji — konta, treści, publikacje na platformach i pomiary statystyk. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 16);
            $table->string('name');
            $table->string('handle')->nullable();
            $table->string('url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Treść zapisana raz; każda platforma ma własną publikację tej treści.
        Schema::create('contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('campaign_id')->nullable()->index(); // etap 4
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('hashtags', 500)->nullable();
            $table->string('type', 16)->default('post');    // post | reel | story | video | article
            $table->string('format', 16)->default('image'); // image | carousel | video | text
            $table->string('stage', 16)->default('idea');   // idea | preparing | ready
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 16)->index();
            $table->dateTime('scheduled_at')->index();
            $table->dateTime('published_at')->nullable()->index();
            $table->string('status', 16)->default('planned'); // planned | published | cancelled
            $table->string('url')->nullable();
            $table->text('caption')->nullable();               // opcjonalna wersja opisu pod platformę
            $table->boolean('promoted')->default(false);
            $table->decimal('promo_budget', 10, 2)->nullable();
            $table->date('promo_from')->nullable();
            $table->date('promo_to')->nullable();
            $table->boolean('visible_to_client')->default(false);
            $table->string('client_status', 16)->nullable();   // null | approved | changes
            $table->text('client_comment')->nullable();
            $table->unsignedTinyInteger('checks_done')->default(0); // pomiary po 1, 7, 30 dniach
            $table->timestamps();
        });

        // Każdy pomiar zapisany z datą, bez nadpisywania.
        Schema::create('measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publication_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('measured_on');
            $table->unsignedInteger('reach')->default(0);
            $table->unsignedInteger('reactions')->default(0);
            $table->unsignedInteger('comments')->default(0);
            $table->unsignedInteger('shares')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();
            $table->index(['publication_id', 'measured_on']);
        });
    }

    public function down(): void
    {
        foreach (['measurements', 'publications', 'contents', 'social_accounts'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
