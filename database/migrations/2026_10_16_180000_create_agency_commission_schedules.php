<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('agency_commission_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_agency_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_company_id')->constrained()->restrictOnDelete();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->string('accrual_event',30)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('replaces_id')->nullable()->unique()->constrained('agency_commission_schedules')->restrictOnDelete();
            $table->text('revision_reason')->nullable();
            $table->dateTime('superseded_at')->nullable();
            $table->dateTime('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->index(['supplier_agency_id','supplier_company_id','valid_from'],'commission_scope_date');
        });
        Schema::create('agency_commission_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_commission_schedule_id')->constrained()->restrictOnDelete();
            $table->foreignId('insurance_branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('insurance_subbranch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('scope_key',60);
            $table->string('basis',30)->nullable();
            $table->string('basis_note',500)->nullable();
            $table->unsignedInteger('issue_rate_units')->nullable();
            $table->unsignedInteger('renewal_rate_units')->nullable();
            $table->timestamps();
            $table->unique(['agency_commission_schedule_id','scope_key'],'commission_rate_unique_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_commission_rates');
        Schema::dropIfExists('agency_commission_schedules');
    }
};
