<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('tax_id', 32)->nullable();
            $table->text('address')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 64)->nullable();
            $table->unsignedSmallInteger('retention_months')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('name');
            $table->string('role', 20)->default('client');
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('locale', 5)->default('pl');
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->unsignedBigInteger('campaign_id')->nullable()->index(); // etap 4
            $table->foreignId('template_id')->nullable();
            $table->string('title');
            $table->string('type', 32)->default('task');
            $table->string('status', 32)->default('with_agency');
            $table->string('blocked_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->unsignedBigInteger('last_public_message_id')->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamp('waiting_since')->nullable();
            $table->unsignedTinyInteger('reminders_sent')->default(0);
            $table->timestamp('last_reminder_at')->nullable();
            $table->timestamp('stalled_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('archive_state', 16)->default('active'); // active | archived | offline
            $table->string('offline_package')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'status']);
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
            $table->index(['ticket_id', 'id']);
        });

        Schema::create('ticket_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 48);
            $table->json('data')->nullable();
            $table->boolean('is_internal')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['ticket_id', 'created_at']);
        });

        Schema::create('reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('readable');
            $table->unsignedBigInteger('last_seen_id')->default(0);
            $table->timestamp('seen_at');
            $table->unique(['user_id', 'readable_type', 'readable_id']);
        });

        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->char('sha256', 64)->unique();
            $table->unsignedBigInteger('size');
            $table->string('mime', 128);
            $table->string('path');
            $table->string('location', 16)->default('hosting'); // hosting | offline
            $table->string('offline_package')->nullable();
            $table->timestamps();
        });

        Schema::create('file_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('usable');
            $table->string('name');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('upload_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('mime', 128)->nullable();
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('total_chunks');
            $table->unsignedInteger('received_chunks')->default(0);
            $table->timestamps();
        });

        Schema::create('ticket_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('title');
            $table->string('type', 32)->default('task');
            $table->text('body')->nullable();
            $table->string('recurrence', 16)->default('none'); // none | weekly | monthly
            $table->date('next_run_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 48)->index();
            $table->string('level', 16)->default('info');
            $table->string('message');
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        foreach (['system_logs', 'ticket_templates', 'upload_sessions', 'file_usages', 'files', 'reads',
            'ticket_events', 'ticket_messages', 'tickets', 'invitations', 'clients'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
