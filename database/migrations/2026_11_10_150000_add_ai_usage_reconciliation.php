<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $missing=array_filter(['reconciled_by','reconciled_at','reconciliation_note'],fn($column)=>!Schema::hasColumn('ai_usages',$column));
        if(!$missing) return;
        Schema::table('ai_usages',function(Blueprint $table)use($missing) {
            if(in_array('reconciled_by',$missing,true)) $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            if(in_array('reconciled_at',$missing,true)) $table->dateTime('reconciled_at')->nullable();
            if(in_array('reconciliation_note',$missing,true)) $table->text('reconciliation_note')->nullable();
        });
    }

    public function down(): void
    {
        if(\Illuminate\Support\Facades\DB::table('ai_usages')->whereNotNull('reconciled_at')->exists()) throw new RuntimeException('Le verifiche dei consumi fanno parte dello storico.');
    }
};
