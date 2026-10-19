<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('insurance_policies', function (Blueprint $table) {
            /** Operational completion is explicit; existing contract statuses must not close a workflow. */
            $table->timestamp('work_closed_at')->nullable();
            $table->foreignId('work_closed_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::create('insurance_policy_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_policy_id')->constrained('insurance_policies')->restrictOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('request_key')->unique();
            $table->text('message');
            $table->string('status', 24)->default('open');
            $table->text('resolution')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['insurance_policy_id', 'status'], 'policy_corrections_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_policy_corrections');
        Schema::table('insurance_policies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_closed_by');
            $table->dropColumn('work_closed_at');
        });
    }
};
