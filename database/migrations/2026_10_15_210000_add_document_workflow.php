<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['insurance_branches', 'insurance_subbranches'] as $name) Schema::table($name, fn (Blueprint $table) => $table->json('document_categories')->nullable());
        Schema::create('client_document_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('insurance_policy_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('insurance_branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('source_key', 64)->nullable()->unique();
            $table->string('label');
            $table->string('document_category', 50);
            $table->date('due_on')->nullable()->index();
            $table->text('notes')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::table('attachments', function (Blueprint $table) {
            $table->foreignId('previous_version_id')->nullable()->unique()->constrained('attachments')->restrictOnDelete();
            $table->unsignedInteger('version_number')->default(1);
            $table->foreignId('document_request_id')->nullable()->constrained('client_document_requests')->restrictOnDelete();
            $table->string('review_status', 20)->default('received');
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->string('validation_note', 500)->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('previous_version_id');
            $table->dropConstrainedForeignId('document_request_id');
            $table->dropConstrainedForeignId('validated_by');
            $table->dropColumn(['version_number', 'review_status', 'validated_at', 'validation_note']);
        });
        Schema::dropIfExists('client_document_requests');
        foreach (['insurance_branches', 'insurance_subbranches'] as $name) Schema::table($name, fn (Blueprint $table) => $table->dropColumn('document_categories'));
    }
};
