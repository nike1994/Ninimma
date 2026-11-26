<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Zgłoszenia i odpowiedzi przez e-mail: dziennik przyjętych wiadomości (bez przechowywania treści). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbound_emails', function (Blueprint $table) {
            $table->id();
            $table->string('message_id', 255)->unique();
            $table->string('from_email', 255)->nullable();
            $table->string('subject', 255)->nullable();
            // accepted_reply | accepted_new | rejected_sender | rejected_auth | ignored_auto | ignored_empty | rate_limited | error
            $table->string('status', 24);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_message_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('attachments')->default(0);
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['from_email', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_emails');
    }
};
