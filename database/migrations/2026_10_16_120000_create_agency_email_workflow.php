<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('outbound_mail_settings', function (Blueprint $table) {
            $table->id();
            $table->longText('credentials');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('agency_communications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('supplier_agency_id')->nullable()->constrained('supplier_agencies')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('review')->index();
            $table->longText('payload');
            $table->char('fingerprint', 64);
            $table->timestamp('review_expires_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('sending_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('message_id')->nullable();
            $table->string('error_code', 40)->nullable();
            $table->timestamps();
        });
        Schema::create('agency_communication_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('agency_communication_id')->constrained('agency_communications')->cascadeOnDelete();
            $table->foreignId('insurance_policy_id')->nullable()->constrained('insurance_policies')->nullOnDelete();
            $table->char('policy_fingerprint', 64);
            $table->char('delivery_key', 64)->nullable()->unique();
            $table->unique(['agency_communication_id','insurance_policy_id'], 'agency_email_policy_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_communication_policies');
        Schema::dropIfExists('agency_communications');
        Schema::dropIfExists('outbound_mail_settings');
    }
};
