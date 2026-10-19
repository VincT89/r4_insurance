<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insurance_policies', function (Blueprint $table) {
            $table->string('payment_status', 20)->nullable();
            $table->string('payment_method', 150)->nullable();
            $table->string('payment_frequency', 30)->nullable();
            $table->decimal('installment_premium', 12, 2)->nullable();
            $table->boolean('tacit_renewal')->nullable();
            $table->boolean('ad_horas')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('insurance_policies', fn (Blueprint $table) => $table->dropColumn(['payment_status', 'payment_method', 'payment_frequency', 'installment_premium', 'tacit_renewal', 'ad_horas']));
    }
};
