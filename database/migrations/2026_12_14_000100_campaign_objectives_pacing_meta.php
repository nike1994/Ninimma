<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cel kampanii (rodzaj + wartość docelowa), alerty tempa budżetu i synchronizacja z Meta Ads. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('objective', 16)->nullable()->after('goal'); // sales | leads | traffic | awareness | engagement
            $table->decimal('target_value', 14, 2)->nullable()->after('objective');
        });
        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->string('external_id', 40)->nullable()->after('external_name'); // ID kampanii w menedżerze reklam (Meta)
            $table->timestamp('synced_at')->nullable();
            $table->string('sync_error', 300)->nullable();
        });
        Schema::table('campaign_audiences', function (Blueprint $table) {
            $table->string('source', 12)->default('manual'); // manual | import | meta
        });
        // Wysłane alerty tempa budżetu (najwyżej jeden danego rodzaju na tydzień).
        Schema::create('campaign_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 12); // over | under
            $table->date('sent_on');
            $table->timestamps();
            $table->index(['campaign_id', 'kind', 'sent_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_alerts');
        Schema::table('campaign_audiences', fn (Blueprint $t) => $t->dropColumn('source'));
        Schema::table('ad_campaigns', fn (Blueprint $t) => $t->dropColumn(['external_id', 'synced_at', 'sync_error']));
        Schema::table('campaigns', fn (Blueprint $t) => $t->dropColumn(['objective', 'target_value']));
    }
};
