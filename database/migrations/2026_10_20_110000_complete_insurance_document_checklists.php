<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['insurance_branches', 'insurance_subbranches'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->json('person_document_categories')->nullable();
                $table->json('company_document_categories')->nullable();
            });
        }
        Schema::table('insurance_form_templates', fn (Blueprint $table) => $table->string('client_type', 20)->default('all'));
        Schema::create('client_document_request_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_document_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('attachment_id')->constrained()->restrictOnDelete();
            $table->foreignId('linked_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->unique(['client_document_request_id', 'attachment_id'], 'document_request_file_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_document_request_files');
        Schema::table('insurance_form_templates', fn (Blueprint $table) => $table->dropColumn('client_type'));
        foreach (['insurance_branches', 'insurance_subbranches'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->dropColumn(['person_document_categories', 'company_document_categories']));
        }
    }
};
