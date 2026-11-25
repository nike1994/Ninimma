<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kampania spina klienta, cel, budżet i okres z publikacjami (etap 4 — podstawa).
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('goal')->nullable();
            $table->decimal('budget', 12, 2)->default(0);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 16)->default('draft'); // draft | review | active | finished
            $table->decimal('revenue', 12, 2)->nullable();  // do ROAS
            $table->unsignedInteger('leads')->nullable();
            $table->timestamps();
        });

        Schema::create('social_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('body', 300);
            $table->boolean('is_done')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_notes');
        Schema::dropIfExists('campaigns');
    }
};
