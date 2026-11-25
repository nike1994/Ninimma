<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('priority', 16)->default('normal')->after('status'); // normal | high | urgent
            $table->date('due_on')->nullable()->after('priority')->index();
            $table->foreignId('assigned_to')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable()->after('closed_at');
        });

        // Wpisy czasu pracy (ze specyfikacji: wpisy_czasu).
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('spent_on')->index();
            $table->unsignedInteger('minutes');
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn(['priority', 'due_on', 'started_at']);
        });
    }
};
