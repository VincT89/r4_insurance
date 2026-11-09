<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('signing_stations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 80)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('credential_hash', 64)->unique();
            $table->text('pairing_code')->nullable();
            $table->string('pairing_code_hash', 64)->nullable()->unique();
            $table->timestamp('pairing_expires_at');
            $table->timestamp('credential_expires_at');
            $table->foreignId('paired_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('paired_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('station_signing_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('signing_station_id')->constrained('signing_stations')->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('insurance_policy_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->text('signer_name');
            $table->text('signer_role')->nullable();
            $table->text('note')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('identity_confirmed_at');
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['signing_station_id','status','expires_at']);
        });
        Schema::create('station_signing_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('station_signing_request_id')->constrained('station_signing_requests')->restrictOnDelete();
            $table->foreignId('source_attachment_id')->constrained('attachments')->restrictOnDelete();
            $table->foreignId('document_signature_id')->nullable()->unique()->constrained('document_signatures')->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->longText('prepared_token');
            $table->string('source_hash', 64);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['station_signing_request_id','source_attachment_id']);
            $table->index('source_attachment_id');
        });
    }

    public function down(): void
    {
        if (DB::table('station_signing_requests')->exists()) throw new RuntimeException('Le richieste di firma fanno parte dello storico.');
        Schema::dropIfExists('station_signing_items');
        Schema::dropIfExists('station_signing_requests');
        Schema::dropIfExists('signing_stations');
    }
};
