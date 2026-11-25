<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Etap 4: influencerzy, współprace z etapami, przesyłki, materiały z licencjami, przypomnienia. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('influencers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('photo_usage_id')->nullable()->constrained('file_usages')->nullOnDelete();
            $table->json('profiles')->nullable();      // [{platform, url, handle, followers}]
            $table->string('portfolio_url')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 64)->nullable();
            $table->text('address')->nullable();
            $table->string('parcel_locker', 64)->nullable();
            $table->json('tags')->nullable();
            $table->unsignedTinyInteger('rating')->nullable(); // 1–5
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('collaborations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('influencer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->decimal('rate', 10, 2)->default(0);
            $table->string('billing', 16)->default('contract'); // contract | invoice
            $table->text('scope')->nullable();                   // ustalony zakres materiałów
            $table->string('license_scope')->nullable();
            $table->date('license_from')->nullable();
            $table->date('license_to')->nullable();
            $table->string('status', 16)->default('active');     // active | done | cancelled
            $table->string('cancel_reason')->nullable();
            $table->text('private_notes')->nullable();           // usuwane po zakończeniu współpracy
            $table->boolean('needs_shipment')->default(true);
            $table->string('tracking_link')->nullable();         // link z oznaczeniem do sprzedaży
            $table->string('discount_code', 64)->nullable();
            $table->unsignedInteger('sales')->nullable();
            $table->decimal('revenue', 12, 2)->nullable();
            $table->foreignId('contract_usage_id')->nullable()->constrained('file_usages')->nullOnDelete();
            foreach (['contract_sent', 'contract_signed', 'brief_sent', 'brief_accepted', 'materials_complete', 'published', 'paid'] as $col) {
                $table->timestamp($col.'_at')->nullable();
            }
            $table->timestamps();
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collaboration_id')->constrained()->cascadeOnDelete();
            $table->string('contents');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('value', 10, 2)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->date('sent_on')->nullable();
            $table->date('delivered_on')->nullable();
            $table->timestamps();
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collaboration_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->boolean('is_received')->default(false);
            $table->foreignId('file_usage_id')->nullable()->constrained('file_usages')->nullOnDelete();
            $table->foreignId('publication_id')->nullable()->constrained()->nullOnDelete();
            $table->date('license_until')->nullable();
            $table->timestamps();
        });

        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->morphs('remindable');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('due_on')->index();
            $table->string('body');
            $table->boolean('is_done')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['reminders', 'materials', 'shipments', 'collaborations', 'influencers'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
