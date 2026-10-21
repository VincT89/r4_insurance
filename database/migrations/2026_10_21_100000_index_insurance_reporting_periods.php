<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /** Period searches keep the original rows and financial values; only their access paths change. */
        Schema::table('insurance_policies', function (Blueprint $table) {
            $table->index(['starts_on', 'id'], 'policies_start_period');
            $table->index(['created_at', 'id'], 'policies_creation_period');
        });
        Schema::table('insurance_titles', fn (Blueprint $table) => $table->index(['coverage_from', 'id'], 'titles_coverage_period'));
        Schema::table('insurance_commission_movements', fn (Blueprint $table) => $table->index(['effective_on', 'id'], 'commission_movement_period'));
        Schema::table('insurance_policy_changes', fn (Blueprint $table) => $table->index(['kind', 'created_at', 'id'], 'policy_changes_creation_period'));
    }

    public function down(): void
    {
        Schema::table('insurance_policies', function (Blueprint $table) {
            $table->dropIndex('policies_start_period');
            $table->dropIndex('policies_creation_period');
        });
        Schema::table('insurance_titles', fn (Blueprint $table) => $table->dropIndex('titles_coverage_period'));
        Schema::table('insurance_commission_movements', fn (Blueprint $table) => $table->dropIndex('commission_movement_period'));
        Schema::table('insurance_policy_changes', fn (Blueprint $table) => $table->dropIndex('policy_changes_creation_period'));
    }
};
