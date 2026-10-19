<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('insurance_deadline_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->unsignedBigInteger('source_id');
            $table->date('due_on');
            $table->timestamp('created_at');
            $table->unique(['user_id', 'kind', 'source_id', 'due_on'], 'deadline_reminder_unique');
        });
    }
    public function down(): void { Schema::dropIfExists('insurance_deadline_reminders'); }
};
