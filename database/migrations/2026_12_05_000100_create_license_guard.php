<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Strażnik licencji: kanały użycia, powiązanie materiał ↔ treść/reklama, przedłużenia. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collaborations', function (Blueprint $table) {
            $table->json('license_channels')->nullable();          // domyślne kanały dla materiałów
            $table->boolean('requires_takedown')->default(false);   // po wygaśnięciu trzeba zdjąć posty
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->date('license_from')->nullable()->after('publication_id');
            $table->json('license_channels')->nullable();
            $table->boolean('requires_takedown')->default(false);
            $table->string('kind', 16)->default('video'); // video | photo | story | other
        });

        // Treść zbudowana z materiału (UGC / influencer).
        Schema::create('content_material', function (Blueprint $table) {
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->primary(['content_id', 'material_id']);
        });

        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete(); // reklama z materiałem (bez posta)
        });

        Schema::create('license_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('previous_until')->nullable();
            $table->date('new_until');
            $table->json('channels')->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        // Wysłane alerty (żeby e-mail o danym progu poszedł raz).
        Schema::create('license_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->date('license_until');
            $table->unsignedSmallInteger('threshold');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['material_id', 'license_until', 'threshold']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_alerts');
        Schema::dropIfExists('license_extensions');
        Schema::table('ad_campaigns', fn (Blueprint $t) => $t->dropConstrainedForeignId('material_id'));
        Schema::dropIfExists('content_material');
        Schema::table('materials', fn (Blueprint $t) => $t->dropColumn(['license_from', 'license_channels', 'requires_takedown', 'kind']));
        Schema::table('collaborations', fn (Blueprint $t) => $t->dropColumn(['license_channels', 'requires_takedown']));
    }
};
