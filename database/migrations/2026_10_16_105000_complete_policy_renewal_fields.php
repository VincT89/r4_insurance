<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /** Early installations already ran the payment migration before renewal fields were added. */
        if (!Schema::hasColumn('insurance_policies', 'tacit_renewal')) Schema::table('insurance_policies', fn (Blueprint $table) => $table->boolean('tacit_renewal')->nullable());
        if (!Schema::hasColumn('insurance_policies', 'ad_horas')) Schema::table('insurance_policies', fn (Blueprint $table) => $table->boolean('ad_horas')->default(false));
    }
    public function down(): void { /** Shared with the original migration; preserve existing renewal information on rollback. */ }
};
