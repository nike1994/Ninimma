<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Kampanie marketingowe: lejek (wejścia na stronę, koszyk) i odbiorcy (płeć, wiek, miasta, kraje). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_results', function (Blueprint $table) {
            $table->unsignedInteger('landing_views')->default(0); // wejścia na stronę docelową
            $table->unsignedInteger('add_to_cart')->default(0);   // dodania do koszyka
        });

        // Struktura odbiorców kampanii (udział %, z menedżera reklam / statystyk konta).
        Schema::create('campaign_audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('dimension', 12); // gender | age | city | country
            $table->string('label', 80);
            $table->decimal('share', 5, 2);  // procent 0–100
            $table->timestamps();
            $table->unique(['campaign_id', 'dimension', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_audiences');
        Schema::table('ad_results', fn (Blueprint $t) => $t->dropColumn(['landing_views', 'add_to_cart']));
    }
};
