<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insurance_quote_cases', function (Blueprint $table) {
            $table->string('purpose', 20)->default('new')->index();
        });
        Schema::create('insurance_policy_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_policy_id')->constrained('insurance_policies')->restrictOnDelete();
            $table->foreignId('result_policy_id')->constrained('insurance_policies')->restrictOnDelete();
            $table->foreignId('insurance_quote_id')->unique()->constrained()->restrictOnDelete();
            $table->string('kind', 20);
            $table->date('effective_on');
            $table->date('expires_on');
            $table->string('reference', 100);
            $table->text('description');
            $table->unsignedBigInteger('premium_cents');
            $table->longText('snapshot');
            $table->uuid('request_key')->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['source_policy_id', 'kind', 'effective_on'], 'policy_changes_source_kind_date');
        });
        Schema::table('insurance_form_packages', function (Blueprint $table) {
            $table->foreignId('insurance_policy_change_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('attachments', function (Blueprint $table) {
            $table->foreignId('insurance_policy_change_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('insurance_titles', function (Blueprint $table) {
            $table->foreignId('insurance_policy_change_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('insurance_titles', fn (Blueprint $table) => $table->dropConstrainedForeignId('insurance_policy_change_id'));
        Schema::table('attachments', fn (Blueprint $table) => $table->dropConstrainedForeignId('insurance_policy_change_id'));
        Schema::table('insurance_form_packages', fn (Blueprint $table) => $table->dropConstrainedForeignId('insurance_policy_change_id'));
        Schema::dropIfExists('insurance_policy_changes');
        Schema::table('insurance_quote_cases', fn (Blueprint $table) => $table->dropColumn('purpose'));
    }
};
