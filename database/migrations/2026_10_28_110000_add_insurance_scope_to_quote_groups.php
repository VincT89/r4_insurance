<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_recipient_groups', function (Blueprint $table) {
            $table->foreignId('insurance_branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('insurance_subbranch_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('cu_from')->nullable()->change();
            $table->unsignedTinyInteger('cu_to')->nullable()->change();
        });
        $auto = DB::table('insurance_branches')->whereRaw('UPPER(TRIM(name)) = ?', ['RC AUTO'])->pluck('id');
        /** Existing groups were defined for RC Auto; only attach an unambiguous catalog match. */
        if ($auto->count() === 1) DB::table('quote_recipient_groups')->whereNotNull('cu_from')->update(['insurance_branch_id'=>$auto->first()]);
    }

    public function down(): void
    {
        Schema::table('quote_recipient_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('insurance_subbranch_id');
            $table->dropConstrainedForeignId('insurance_branch_id');
            /** Preserve empty merit classes rather than inventing values for non-motor groups. */
        });
    }
};
