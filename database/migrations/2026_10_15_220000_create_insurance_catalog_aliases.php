<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('insurance_catalog_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('source_key', 64)->unique();
            $table->string('source_branch', 100);
            $table->string('source_subbranch', 100)->nullable();
            $table->foreignId('insurance_branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('insurance_subbranch_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('insurance_catalog_aliases'); }
};
