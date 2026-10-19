<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('insurance_form_templates', function (Blueprint $t) {
            $t->id(); $t->uuid('family'); $t->unsignedInteger('version')->default(1); $t->string('title'); $t->string('category',50); $t->string('scope',20)->default('policy');
            $t->foreignId('insurance_branch_id')->nullable()->constrained()->restrictOnDelete(); $t->foreignId('insurance_subbranch_id')->nullable()->constrained()->restrictOnDelete();
            $t->longText('body'); $t->boolean('signature_required')->default(true); $t->boolean('required')->default(false); $t->string('status',20)->default('draft');
            $t->string('source_sheet')->nullable(); $t->string('source_hash',64)->nullable(); $t->text('source_warning')->nullable(); $t->boolean('source_reviewed')->default(false);
            $t->foreignId('previous_id')->nullable()->unique()->constrained('insurance_form_templates')->restrictOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('approved_at')->nullable(); $t->text('revision_reason')->nullable(); $t->timestamps();
            $t->unique(['family','version']);
        });
        Schema::create('insurance_form_packages', function (Blueprint $t) {
            $t->id(); $t->foreignId('client_id')->constrained()->restrictOnDelete(); $t->foreignId('insurance_policy_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('reason',30); $t->text('note')->nullable(); $t->longText('snapshot'); $t->string('context_hash',64); $t->uuid('request_key')->unique();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps();
        });
        Schema::create('insurance_form_documents', function (Blueprint $t) {
            $t->id(); $t->foreignId('insurance_form_package_id')->constrained()->restrictOnDelete(); $t->foreignId('insurance_form_template_id')->constrained()->restrictOnDelete();
            $t->string('title'); $t->unsignedInteger('template_version'); $t->longText('body'); $t->boolean('signature_required'); $t->string('pdf_hash',64)->nullable(); $t->timestamps();
            $t->unique(['insurance_form_package_id','insurance_form_template_id'],'form_package_template_unique');
        });
        Schema::create('insurance_form_submissions', function (Blueprint $t) {
            $t->id(); $t->foreignId('insurance_form_document_id')->constrained()->restrictOnDelete(); $t->unsignedInteger('version'); $t->uuid('request_key')->unique();
            $t->string('status',20)->default('received'); $t->string('signer_name'); $t->string('signer_role')->nullable(); $t->date('signed_on'); $t->text('note')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('reviewed_at')->nullable(); $t->text('review_note')->nullable(); $t->json('file_hashes'); $t->timestamps();
            $t->unique(['insurance_form_document_id','version'],'form_submission_version_unique');
        });
        Schema::create('insurance_coverage_checks', function (Blueprint $t) {
            $t->id(); $t->foreignId('insurance_policy_id')->constrained()->restrictOnDelete(); $t->string('plate',20); $t->string('outcome',20); $t->timestamp('checked_at'); $t->text('note')->nullable(); $t->string('context_hash',64); $t->uuid('request_key')->unique(); $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps();
        });
        Schema::table('attachments', function (Blueprint $t) {
            $t->foreignId('insurance_form_document_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('insurance_form_submission_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('insurance_coverage_check_id')->nullable()->constrained()->restrictOnDelete();
        });
    }
    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $t) { foreach (['insurance_form_document_id','insurance_form_submission_id','insurance_coverage_check_id'] as $column) $t->dropConstrainedForeignId($column); });
        foreach (['insurance_coverage_checks','insurance_form_submissions','insurance_form_documents','insurance_form_packages','insurance_form_templates'] as $table) Schema::dropIfExists($table);
    }
};
