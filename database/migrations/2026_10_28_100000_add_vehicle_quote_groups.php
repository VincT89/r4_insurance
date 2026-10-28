<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_recipient_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->unsignedTinyInteger('cu_from');
            $table->unsignedTinyInteger('cu_to');
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });
        Schema::create('quote_recipient_group_company', function (Blueprint $table) {
            $table->foreignId('quote_recipient_group_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_company_id')->constrained()->restrictOnDelete();
            $table->primary(['quote_recipient_group_id', 'supplier_company_id'], 'quote_group_company_primary');
        });
        Schema::table('client_vehicles', function (Blueprint $table) {
            $table->unsignedTinyInteger('merit_class_cu')->nullable();
            $table->string('merit_class_internal', 30)->nullable();
            $table->foreignId('merit_class_company_id')->nullable()->constrained('supplier_companies')->restrictOnDelete();
        });
        Schema::table('insurance_quote_cases', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable()->constrained('client_vehicles')->restrictOnDelete();
            $table->unsignedTinyInteger('merit_class_cu')->nullable();
            $table->string('merit_class_internal', 30)->nullable();
            $table->text('vehicle_snapshot')->nullable();
            $table->string('vehicle_plate', 20)->nullable()->index();
            $table->foreignId('quote_recipient_group_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('recipient_group_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('insurance_quote_cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehicle_id');
            $table->dropConstrainedForeignId('quote_recipient_group_id');
            $table->dropColumn(['merit_class_cu', 'merit_class_internal', 'vehicle_snapshot', 'vehicle_plate', 'recipient_group_snapshot']);
        });
        Schema::table('client_vehicles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merit_class_company_id');
            $table->dropColumn(['merit_class_cu', 'merit_class_internal']);
        });
        Schema::dropIfExists('quote_recipient_group_company');
        Schema::dropIfExists('quote_recipient_groups');
    }
};
