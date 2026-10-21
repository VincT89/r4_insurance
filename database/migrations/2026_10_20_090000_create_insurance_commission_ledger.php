<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('agency_commission_schedules', function (Blueprint $table) {
            $table->string('mandate_reference', 150)->nullable();
            $table->string('collection_mode', 30)->nullable();
        });
        Schema::table('agency_commission_rates', function (Blueprint $table) {
            $table->unsignedInteger('quietance_rate_units')->nullable();
            $table->string('accrual_event', 30)->nullable();
            $table->string('collection_mode', 30)->nullable();
        });
        Schema::create('insurance_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_title_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('agency_commission_rate_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('base_cents')->nullable();
            $table->string('base_reference', 500)->nullable();
            $table->unsignedBigInteger('expected_cents')->nullable();
            $table->unsignedBigInteger('accrued_cents')->default(0);
            $table->unsignedBigInteger('settled_cents')->default(0);
            $table->json('rule_snapshot')->nullable();
            $table->dateTime('locked_at')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->string('incomplete_reason', 500)->nullable();
            $table->timestamps();
        });
        Schema::create('insurance_commission_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_commission_id')->constrained()->restrictOnDelete();
            $table->string('kind', 30);
            $table->bigInteger('amount_cents');
            $table->date('effective_on');
            $table->string('reference', 150)->nullable();
            $table->text('reason');
            $table->foreignId('reversal_of_id')->nullable()->unique()->constrained('insurance_commission_movements')->restrictOnDelete();
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->string('request_fingerprint', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->index(['kind', 'effective_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_commission_movements');
        Schema::dropIfExists('insurance_commissions');
        Schema::table('agency_commission_rates', fn (Blueprint $table) => $table->dropColumn(['quietance_rate_units', 'accrual_event', 'collection_mode']));
        Schema::table('agency_commission_schedules', fn (Blueprint $table) => $table->dropColumn(['mandate_reference', 'collection_mode']));
    }
};
