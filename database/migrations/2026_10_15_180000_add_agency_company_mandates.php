<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_agency_company', function (Blueprint $table) {
            $table->foreignId('supplier_agency_id')->constrained('supplier_agencies')->cascadeOnDelete();
            $table->foreignId('supplier_company_id')->constrained('supplier_companies')->restrictOnDelete();
            $table->primary(['supplier_agency_id', 'supplier_company_id'], 'agency_company_primary');
        });
        DB::table('supplier_agencies')->whereNotNull('supplier_company_id')->orderBy('id')->chunkById(500, function ($agencies) {
            DB::table('supplier_agency_company')->insert($agencies->map(fn ($a) => [
                'supplier_agency_id' => $a->id, 'supplier_company_id' => $a->supplier_company_id,
            ])->all());
        });
    }

    public function down(): void
    {
        if (DB::table('supplier_agency_company')->select('supplier_agency_id')->groupBy('supplier_agency_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Sono presenti agenzie plurimandatarie: preservare i collegamenti prima del rollback.');
        }
        Schema::dropIfExists('supplier_agency_company');
    }
};
