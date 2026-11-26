<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Integracje z API platform (Meta: Facebook + Instagram, TikTok): połączenia kont, automatyczna publikacja, statystyki. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->string('external_id', 64)->nullable();       // id strony FB / konta IG / open_id TikTok
            $table->text('access_token')->nullable();             // szyfrowane
            $table->text('refresh_token')->nullable();            // szyfrowane (TikTok)
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->json('api_meta')->nullable();                 // nazwa strony, nazwa użytkownika
            $table->string('api_error', 255)->nullable();
        });

        Schema::table('publications', function (Blueprint $table) {
            $table->boolean('auto_publish')->default(false);
            // null | queued | processing | published | failed | held
            $table->string('api_status', 16)->nullable();
            $table->string('api_error', 500)->nullable();
            $table->string('external_id', 64)->nullable();
            $table->string('api_container', 64)->nullable();      // kontener IG / publish_id TikTok w trakcie
            $table->unsignedTinyInteger('api_attempts')->default(0);
            $table->timestamp('api_next_attempt_at')->nullable();
            $table->index(['auto_publish', 'status', 'scheduled_at']);
        });

        Schema::table('measurements', function (Blueprint $table) {
            $table->string('source', 8)->default('manual'); // manual | api
            $table->unsignedInteger('views')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('measurements', fn (Blueprint $t) => $t->dropColumn(['source', 'views']));
        Schema::table('publications', function (Blueprint $t) {
            $t->dropIndex(['auto_publish', 'status', 'scheduled_at']);
            $t->dropColumn(['auto_publish', 'api_status', 'api_error', 'external_id', 'api_container', 'api_attempts', 'api_next_attempt_at']);
        });
        Schema::table('social_accounts', fn (Blueprint $t) => $t->dropColumn(['external_id', 'access_token', 'refresh_token', 'token_expires_at', 'connected_at', 'api_meta', 'api_error']));
    }
};
