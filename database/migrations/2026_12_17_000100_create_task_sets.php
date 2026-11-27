<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Zestawy zadań: lista linijek w zapisie szybkiego dodawania (z terminami „+N” od daty startu). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->text('body');
            $table->unsignedInteger('uses')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_sets');
    }
};
