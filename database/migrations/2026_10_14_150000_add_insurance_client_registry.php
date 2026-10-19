<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('client_type', 20)->nullable()->index();
            $table->string('title', 50)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->string('mobile', 50)->nullable();
            $table->string('fax', 50)->nullable();
            $table->string('target')->nullable();
            $table->string('reference_email')->nullable();
            $table->string('reference_role')->nullable();
            $table->string('reference_phone', 50)->nullable();
            $table->string('office')->nullable();
            $table->string('consultant')->nullable();
            $table->string('collaborator')->nullable();
            $table->string('operator')->nullable();
            $table->text('salient_notes')->nullable();
            $table->index('tax_code');
        });

        Schema::create('client_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('plate', 20);
            $table->string('make', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('vin', 30)->nullable();
            $table->date('registered_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['client_id', 'plate']);
            $table->index('plate');
        });

        Schema::create('insurance_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('client_vehicles')->restrictOnDelete();
            $table->string('category', 100);
            $table->string('company', 150);
            $table->string('number', 100);
            $table->string('product')->nullable();
            $table->string('insured_subject')->nullable();
            $table->date('starts_on');
            $table->date('expires_on')->index();
            $table->decimal('premium', 12, 2)->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['client_id', 'company', 'number']);
            $table->index(['client_id', 'category']);
        });

        Schema::table('attachments', function (Blueprint $table) {
            $table->string('document_category', 50)->nullable();
            $table->foreignId('insurance_policy_id')->nullable()->constrained('insurance_policies')->restrictOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('client_vehicles')->restrictOnDelete();
            $table->date('expires_on')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('insurance_policy_id');
            $table->dropConstrainedForeignId('vehicle_id');
            $table->dropIndex(['expires_on']);
            $table->dropColumn(['document_category', 'expires_on']);
        });
        Schema::dropIfExists('insurance_policies');
        Schema::dropIfExists('client_vehicles');
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['client_type']);
            $table->dropIndex(['tax_code']);
            $table->dropColumn(['client_type', 'title', 'birth_date', 'birth_place', 'mobile', 'fax', 'target', 'reference_email', 'reference_role', 'reference_phone', 'office', 'consultant', 'collaborator', 'operator', 'salient_notes']);
        });
    }
};
