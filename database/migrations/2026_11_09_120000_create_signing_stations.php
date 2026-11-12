<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('signing_stations')) Schema::create('signing_stations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 80)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('credential_hash', 64)->unique();
            $table->text('pairing_code')->nullable();
            $table->string('pairing_code_hash', 64)->nullable()->unique();
            /** Expiry dates are supplied explicitly; DATETIME avoids MySQL's implicit TIMESTAMP defaults. */
            $table->dateTime('pairing_expires_at');
            $table->dateTime('credential_expires_at');
            $table->foreignId('paired_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('paired_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
        if (!Schema::hasTable('station_signing_requests')) Schema::create('station_signing_requests', function (Blueprint $table) {
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
            $table->dateTime('identity_confirmed_at');
            $table->dateTime('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['signing_station_id','status','expires_at'],'station_requests_pending_idx');
        });
        /** MySQL commits DDL before a later index error; resume those partial tables without dropping their data. */
        if (!Schema::hasColumns('signing_stations',['id','credential_hash','pairing_expires_at','credential_expires_at']) || !Schema::hasColumns('station_signing_requests',['id','signing_station_id','status','expires_at'])) throw new RuntimeException('Le tabelle di firma esistenti non corrispondono alla struttura prevista. Nessuna cancellazione eseguita.');
        if (!Schema::hasIndex('station_signing_requests',['signing_station_id','status','expires_at'])) Schema::table('station_signing_requests',fn(Blueprint $table)=>$table->index(['signing_station_id','status','expires_at'],'station_requests_pending_idx'));
        if (!Schema::hasTable('station_signing_items')) Schema::create('station_signing_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('station_signing_request_id')->constrained('station_signing_requests')->restrictOnDelete();
            $table->foreignId('source_attachment_id')->constrained('attachments')->restrictOnDelete();
            $table->foreignId('document_signature_id')->nullable()->unique()->constrained('document_signatures')->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->longText('prepared_token');
            $table->string('source_hash', 64);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['station_signing_request_id','source_attachment_id'],'station_items_request_source_uq');
            $table->index('source_attachment_id','station_items_source_idx');
        });
        if (!Schema::hasColumns('station_signing_items',['id','station_signing_request_id','source_attachment_id','prepared_token'])) throw new RuntimeException('La tabella dei documenti da firmare non corrisponde alla struttura prevista.');
        if (!Schema::hasIndex('station_signing_items',['station_signing_request_id','source_attachment_id'],'unique')) Schema::table('station_signing_items',fn(Blueprint $table)=>$table->unique(['station_signing_request_id','source_attachment_id'],'station_items_request_source_uq'));
        if (!Schema::hasIndex('station_signing_items',['source_attachment_id'])) Schema::table('station_signing_items',fn(Blueprint $table)=>$table->index('source_attachment_id','station_items_source_idx'));
    }

    public function down(): void
    {
        if (DB::table('station_signing_requests')->exists()) throw new RuntimeException('Le richieste di firma fanno parte dello storico.');
        Schema::dropIfExists('station_signing_items');
        Schema::dropIfExists('station_signing_requests');
        Schema::dropIfExists('signing_stations');
    }
};
