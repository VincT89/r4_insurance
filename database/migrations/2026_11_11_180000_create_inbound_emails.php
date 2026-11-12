<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inbound_emails', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('source', 64);
            $table->char('delivery_key', 64)->unique();
            $table->char('content_hash', 64);
            $table->char('sender_hash', 64)->index();
            $table->longText('payload');
            $table->string('status', 20)->default('unassigned');
            $table->string('document_kind', 20)->default('other');
            $table->foreignId('client_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('insurance_quote_case_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('insurance_policy_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('assigned_at')->nullable();
            $table->text('association_note')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->dateTime('received_at')->index();
            $table->timestamps();
            $table->index(['status', 'received_at']);
        });
        Schema::create('inbound_email_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inbound_email_id')->constrained()->restrictOnDelete();
            $table->text('original_name');
            $table->string('path')->unique();
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_email_files');
        Schema::dropIfExists('inbound_emails');
    }
};
