<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ochrona materiałów przed pobieraniem przez klienta + dziennik dostępu do plików. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->boolean('client_download')->default(false); // klient może pobrać oryginał
        });

        Schema::create('file_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_usage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 16); // preview | download | blocked
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['file_usage_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_access_logs');
        Schema::table('materials', fn (Blueprint $t) => $t->dropColumn('client_download'));
    }
};
