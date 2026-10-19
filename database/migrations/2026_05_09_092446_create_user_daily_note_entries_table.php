<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_daily_note_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_daily_note_id')->constrained()->cascadeOnDelete();
            $table->text('content')->nullable();
            $table->text('post_script')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_daily_note_entries');
    }
};
