<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Marketing płatny: reklamy i promowane posty, wyniki płatne (bez nadpisywania), linki UTM. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();     // kampania marketingowa
            $table->foreignId('publication_id')->nullable()->unique()->constrained()->nullOnDelete(); // promowany post
            $table->string('platform', 16);   // meta | google | tiktok | linkedin | pinterest | youtube | other
            $table->string('name');
            $table->string('external_name')->nullable(); // nazwa w menedżerze reklam (dopasowanie importu)
            $table->string('objective', 16)->default('traffic'); // reach | traffic | engagement | leads | conversions | video_views
            $table->decimal('budget', 10, 2)->default(0);       // budżet planowany
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 16)->default('planned');   // planned | active | paused | finished
            $table->string('target_url', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Pomiary narastające (stan na dzień, jak w menedżerze reklam) — każdy zapisany z datą.
        Schema::create('ad_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('measured_on');
            $table->decimal('spend', 10, 2)->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('reach')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('conversions')->default(0);
            $table->unsignedInteger('leads')->default(0);
            $table->decimal('revenue', 12, 2)->default(0);
            $table->string('source', 16)->default('manual'); // manual | import
            $table->timestamps();
            $table->index(['ad_campaign_id', 'measured_on']);
        });

        Schema::create('utm_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('base_url', 500);
            $table->string('source', 100);
            $table->string('medium', 100);
            $table->string('name', 150);
            $table->string('content', 150)->nullable();
            $table->string('term', 150)->nullable();
            $table->text('full_url');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utm_links');
        Schema::dropIfExists('ad_results');
        Schema::dropIfExists('ad_campaigns');
    }
};
