<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('insurance_quote_emails', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('insurance_quote_case_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_company_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('review');
            $table->longText('payload');
            $table->char('fingerprint', 64);
            $table->char('delivery_key', 64)->nullable()->unique();
            $table->dateTime('review_expires_at');
            $table->dateTime('reviewed_at')->nullable();
            $table->dateTime('sending_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->string('message_id')->nullable();
            $table->string('error_code', 50)->nullable();
            $table->timestamps();
            $table->index(['insurance_quote_case_id', 'status'], 'quote_email_case_status');
        });
        Schema::table('attachments', fn (Blueprint $table) => $table->foreignUuid('insurance_quote_email_id')->nullable()->constrained('insurance_quote_emails')->restrictOnDelete());
    }

    public function down(): void
    {
        Schema::table('attachments', fn (Blueprint $table) => $table->dropConstrainedForeignId('insurance_quote_email_id'));
        Schema::dropIfExists('insurance_quote_emails');
    }
};
