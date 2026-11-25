<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Etap 5: strony, terminy, dziennik, przeglądy z listą kontrolną, pakiety godzin, plan SEO. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('platform', 32)->nullable(); // typ strony: wordpress | magento | other
            $table->json('items');
            $table->timestamps();
        });

        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->string('platform', 32)->default('wordpress');
            $table->string('hosting')->nullable();
            $table->decimal('care_hours', 6, 2)->nullable();      // pakiet opieki: godzin w miesiącu
            $table->foreignId('checklist_id')->nullable()->constrained()->nullOnDelete();
            $table->string('review_recurrence', 16)->nullable();  // monthly | quarterly | weekly
            $table->date('next_review_on')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('site_deadlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16); // domain | ssl | hosting | license
            $table->string('name');
            $table->date('due_on')->index();
            $table->decimal('cost', 10, 2)->nullable();
            $table->timestamp('renewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('site_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('logged_on');
            $table->string('kind', 16); // update | deploy | incident | restore | review | other
            $table->text('description');
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('checklist_id')->nullable()->constrained()->nullOnDelete();
            $table->date('due_on');
            $table->json('items');           // kopia punktów listy z chwili utworzenia
            $table->json('checked')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable()->after('campaign_id')->constrained()->nullOnDelete();
        });

        Schema::create('seo_phrases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('phrase');
            $table->string('target_url')->nullable();
            $table->unsignedTinyInteger('priority')->default(2); // 1 wysoki … 3 niski
            $table->timestamps();
        });

        // Pomiary pozycji z datą, bez nadpisywania.
        Schema::create('seo_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_phrase_id')->constrained()->cascadeOnDelete();
            $table->date('measured_on');
            $table->unsignedSmallInteger('position')->nullable(); // null = poza wynikami
            $table->timestamps();
            $table->index(['seo_phrase_id', 'measured_on']);
        });

        // Plan treści SEO: każda pozycja to zlecenie (rozmowa, akceptacja klienta, historia).
        Schema::create('seo_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seo_phrase_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('working_title');
            $table->date('due_on')->nullable();
            $table->timestamps();
        });

        Schema::create('seo_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->unsignedTinyInteger('priority')->default(2);
            $table->string('status', 16)->default('todo'); // todo | doing | done
            $table->timestamps();
        });

        Schema::create('backlinks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->date('placed_on')->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('checked_on')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['backlinks', 'seo_tasks', 'seo_contents', 'seo_positions', 'seo_phrases'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('tickets', fn (Blueprint $t) => $t->dropConstrainedForeignId('site_id'));
        foreach (['reviews', 'site_logs', 'site_deadlines', 'sites', 'checklists'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
