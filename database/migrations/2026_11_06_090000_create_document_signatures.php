<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('document_signatures', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('insurance_policy_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('source_attachment_id')->constrained('attachments')->restrictOnDelete();
            $table->foreignId('signed_attachment_id')->constrained('attachments')->restrictOnDelete();
            $table->foreignId('insurance_form_submission_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->text('signer_name');
            $table->text('signer_role')->nullable();
            $table->string('source_hash', 64);
            $table->string('signed_hash', 64);
            $table->string('input_hash', 64);
            $table->longText('evidence');
            $table->timestamp('signed_at');
            $table->timestamps();
            $table->index(['source_attachment_id', 'id']);
        });
    }

    public function down(): void
    {
        if (DB::table('document_signatures')->exists()) throw new RuntimeException('Le firme raccolte devono restare nello storico.');
        Schema::dropIfExists('document_signatures');
    }
};
