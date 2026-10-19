<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('insurance_quote_cases', function (Blueprint $table) {
            $table->foreignId('current_policy_id')->nullable()->constrained('insurance_policies')->restrictOnDelete();
            $table->longText('current_policy_snapshot')->nullable();
            $table->string('current_policy_reference', 500)->nullable();
            $table->string('requester_name')->nullable();
            $table->text('current_coverages')->nullable();
            $table->text('additional_coverages')->nullable();
        });
        Schema::table('attachments', fn (Blueprint $table) => $table->foreignId('insurance_quote_case_id')->nullable()->constrained()->restrictOnDelete());
    }

    public function down(): void
    {
        Schema::table('attachments', fn (Blueprint $table) => $table->dropConstrainedForeignId('insurance_quote_case_id'));
        Schema::table('insurance_quote_cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_policy_id');
            $table->dropColumn(['current_policy_snapshot', 'current_policy_reference', 'requester_name', 'current_coverages', 'additional_coverages']);
        });
    }
};
