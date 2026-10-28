<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /** Cover dropdown lookups and outstanding-title scans without changing balances or visibility conditions. */
        Schema::table('insurance_policies', function (Blueprint $table) {
            $table->index(['subcategory', 'client_id'], 'policies_subcategory_options');
            $table->index(['payment_method', 'client_id'], 'policies_payment_method_options');
        });
        Schema::table('insurance_titles', fn (Blueprint $table) => $table->index(
            ['cancelled_at', 'due_on', 'id', 'insurance_policy_id', 'amount_cents', 'collected_cents'], 'titles_pending_due'));
    }

    public function down(): void
    {
        Schema::table('insurance_policies', function (Blueprint $table) {
            $table->dropIndex('policies_subcategory_options');
            $table->dropIndex('policies_payment_method_options');
        });
        Schema::table('insurance_titles', fn (Blueprint $table) => $table->dropIndex('titles_pending_due'));
    }
};
