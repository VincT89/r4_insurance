<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_agencies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('company_name')->nullable();
            $table->string('agency_code', 50)->nullable()->index();
            $table->string('vat_number', 20)->nullable()->index();
            $table->string('tax_code', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('pec')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('mobile', 50)->nullable();
            $table->string('reference_person')->nullable();
            $table->string('reference_role')->nullable();
            $table->string('reference_email')->nullable();
            $table->string('reference_phone', 50)->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 5)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_agencies');
    }
};
