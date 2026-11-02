<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insurance_policy_changes', function (Blueprint $table) {
            $table->unsignedBigInteger('insurance_quote_id')->nullable()->change();
        });
        Schema::table('insurance_titles', function (Blueprint $table) {
            $table->json('contract_snapshot')->nullable();
            $table->foreignId('supplier_company_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('supplier_agency_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('direction', 12)->default('receipt');
            $table->boolean('affects_coverage')->default(true);
            $table->unsignedBigInteger('cancelled_residual_cents')->default(0);
        });
        Schema::table('insurance_collections', function (Blueprint $table) {
            $table->string('direction', 12)->default('receipt');
        });
        /** Preserve the currently recorded contract; earlier provider changes cannot be reconstructed from assumptions. */
        DB::table('insurance_titles')->orderBy('id')->chunkById(250, function ($titles) {
            $policies = DB::table('insurance_policies')->whereIn('id', $titles->pluck('insurance_policy_id'))->get()->keyBy('id');
            foreach ($titles as $title) {
                $policy = (array) $policies[$title->insurance_policy_id];
                unset($policy['notes']);
                DB::table('insurance_titles')->where('id', $title->id)->update([
                    'contract_snapshot'=>json_encode($policy, JSON_THROW_ON_ERROR),
                    'supplier_company_id'=>$policy['supplier_company_id'], 'supplier_agency_id'=>$policy['supplier_agency_id'],
                    'affects_coverage'=>$title->insurance_policy_change_id === null,
                ]);
            }
        });
    }

    public function down(): void
    {
        /** Financial history is deliberately retained instead of silently discarding saved attribution and refunds. */
        throw new RuntimeException('Ripristinare il backup verificato: questa migrazione conserva storico contabile e rimborsi.');
    }
};
