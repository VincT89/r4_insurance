<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('insurance_claims', function (Blueprint $t) {
            $t->id();
            $t->foreignId('client_id')->constrained()->restrictOnDelete();
            $t->foreignId('insurance_policy_id')->constrained()->restrictOnDelete();
            $t->string('number',100)->nullable();
            $t->date('event_on'); $t->date('reported_on')->nullable();
            $t->longText('description'); $t->longText('policy_snapshot');
            $t->string('status',30)->default('open');
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedInteger('version')->default(1); $t->uuid('request_key')->unique();
            $t->timestamp('closed_at')->nullable(); $t->timestamps();
            $t->unique(['insurance_policy_id','number'],'claim_policy_number_unique');
            $t->index(['status','event_on']); $t->index(['assigned_to','status']);
        });
        Schema::create('insurance_claim_activities', function (Blueprint $t) {
            $t->id(); $t->foreignId('insurance_claim_id')->constrained()->restrictOnDelete();
            $t->longText('label'); $t->date('due_on'); $t->unsignedInteger('revision')->default(1);
            $t->string('status',20)->default('pending');
            $t->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('completed_at')->nullable(); $t->timestamps();
            $t->index(['status','due_on']);
        });
        Schema::create('insurance_claim_events', function (Blueprint $t) {
            $t->id(); $t->foreignId('insurance_claim_id')->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('actor_name'); $t->string('kind',30); $t->longText('details');
            $t->uuid('request_key')->unique(); $t->timestamp('created_at');
            $t->index(['insurance_claim_id','created_at']);
        });
        Schema::create('insurance_claim_reminders', function (Blueprint $t) {
            $t->id(); $t->foreignId('insurance_claim_activity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('revision'); $t->string('stage',15); $t->timestamp('created_at');
            $t->unique(['insurance_claim_activity_id','user_id','revision','stage'],'claim_reminder_once');
        });
        Schema::table('attachments', function (Blueprint $t) {
            $t->foreignId('insurance_claim_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('insurance_claim_event_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $t) {
            $t->dropConstrainedForeignId('insurance_claim_event_id'); $t->dropConstrainedForeignId('insurance_claim_id');
        });
        foreach (['insurance_claim_reminders','insurance_claim_events','insurance_claim_activities','insurance_claims'] as $table) Schema::dropIfExists($table);
    }
};
