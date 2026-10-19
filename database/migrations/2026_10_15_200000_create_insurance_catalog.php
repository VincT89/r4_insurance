<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('insurance_branches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('name_key', 64)->unique();
            $table->string('import_key', 64)->nullable()->unique();
            $table->string('status', 20)->default('active');
            $table->boolean('needs_review')->default(false);
            $table->text('notes')->nullable();
            $table->json('source_values')->nullable();
            $table->timestamps();
        });
        Schema::create('insurance_subbranches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_branch_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('name_key', 64);
            $table->string('import_key', 64)->nullable()->unique();
            $table->string('status', 20)->default('active');
            $table->boolean('needs_review')->default(false);
            $table->text('notes')->nullable();
            $table->json('source_values')->nullable();
            $table->timestamps();
            $table->unique(['insurance_branch_id', 'name_key']);
        });
        Schema::table('insurance_policies', function (Blueprint $table) {
            $table->foreignId('insurance_branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('insurance_subbranch_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('insurance_policies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('insurance_subbranch_id');
            $table->dropConstrainedForeignId('insurance_branch_id');
        });
        Schema::dropIfExists('insurance_subbranches');
        Schema::dropIfExists('insurance_branches');
    }
};
