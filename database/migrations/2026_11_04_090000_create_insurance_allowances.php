<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insurance_titles', function (Blueprint $table) {
            $table->unsignedBigInteger('allowance_cents')->default(0);
        });

        Schema::create('insurance_allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_title_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->date('effective_on');
            $table->string('borne_by', 20);
            $table->foreignId('supplier_company_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('supplier_agency_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('bearer_name');
            $table->text('reason');
            $table->string('reference', 150)->nullable();
            $table->uuid('idempotency_key')->unique();
            $table->char('request_fingerprint', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->index(['effective_on', 'id']);
        });
    }

    public function down(): void
    {
        /** Il ripristino non deve eliminare lo storico degli abbuoni già registrati. */
        if (Schema::hasTable('insurance_allowances') && \Illuminate\Support\Facades\DB::table('insurance_allowances')->exists()) {
            throw new RuntimeException('Sono presenti abbuoni da conservare. Ripristina un backup verificato.');
        }
        Schema::dropIfExists('insurance_allowances');
        Schema::table('insurance_titles', fn (Blueprint $table) => $table->dropColumn('allowance_cents'));
    }
};
