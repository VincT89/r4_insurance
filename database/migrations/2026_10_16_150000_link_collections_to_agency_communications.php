<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if(!Schema::hasColumn('insurance_policies','coverage_origin_on')) Schema::table('insurance_policies',fn(Blueprint $t)=>$t->date('coverage_origin_on')->nullable());
        Schema::create('agency_communication_collections',function(Blueprint $t){
            $t->id();$t->foreignUuid('agency_communication_id')->constrained('agency_communications')->cascadeOnDelete();
            $t->foreignId('insurance_collection_id')->constrained('insurance_collections')->restrictOnDelete();
            $t->char('delivery_key',64)->nullable()->unique();$t->unique(['agency_communication_id','insurance_collection_id'],'agency_collection_unique');
        });
    }
    public function down(): void {Schema::dropIfExists('agency_communication_collections');}
};
