<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('reference_person')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::table('supplier_agencies', function (Blueprint $table) {
            // Existing agencies remain unassigned until an operator selects their company.
            $table->foreignId('supplier_company_id')->nullable()->constrained('supplier_companies')->restrictOnDelete();
            $table->foreignId('parent_agency_id')->nullable()->constrained('supplier_agencies')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_agencies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_agency_id');
            $table->dropConstrainedForeignId('supplier_company_id');
        });
        Schema::dropIfExists('supplier_companies');
    }
};
