<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('insurance_policies', function (Blueprint $table) {
            $table->foreignId('supplier_company_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('supplier_agency_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::create('insurance_policy_imports', function (Blueprint $table) {
            $table->id();
            $table->string('source_key', 64)->unique();
            $table->string('batch_hash', 64);
            $table->string('payload_hash', 64);
            $table->foreignId('insurance_policy_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->index();
            $table->string('number', 255);
            $table->string('company', 255);
            $table->string('agency', 255)->nullable();
            $table->string('client_name', 255)->nullable();
            $table->string('source_status', 100)->nullable();
            $table->json('source_rows');
            $table->json('source_payload');
            $table->json('issues');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_policy_imports');
        Schema::table('insurance_policies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_agency_id');
            $table->dropConstrainedForeignId('supplier_company_id');
        });
    }
};
