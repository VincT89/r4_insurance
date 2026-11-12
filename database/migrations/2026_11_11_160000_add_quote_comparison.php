<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('insurance_quote_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_quote_id')->unique()->constrained()->cascadeOnDelete();
            $table->longText('overrides')->nullable();
            $table->longText('history')->nullable();
            $table->text('verification_note')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->dateTime('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('insurance_quote_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attachment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('ai_usage_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('extraction');
            $table->timestamps();
        });
        Schema::table('insurance_quote_cases', function (Blueprint $table) {
            $table->foreignId('preferred_quote_id')->nullable()->constrained('insurance_quotes')->nullOnDelete();
            $table->foreignId('preferred_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('preferred_at')->nullable();
        });
        Schema::table('ai_usages', function (Blueprint $table) {
            $table->foreignId('insurance_quote_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('attachment_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_usages', function (Blueprint $table) { $table->dropConstrainedForeignId('attachment_id'); $table->dropConstrainedForeignId('insurance_quote_id'); });
        Schema::table('insurance_quote_cases', function (Blueprint $table) { $table->dropConstrainedForeignId('preferred_quote_id'); $table->dropConstrainedForeignId('preferred_by'); $table->dropColumn('preferred_at'); });
        Schema::dropIfExists('insurance_quote_readings');
        Schema::dropIfExists('insurance_quote_reviews');
    }
};
