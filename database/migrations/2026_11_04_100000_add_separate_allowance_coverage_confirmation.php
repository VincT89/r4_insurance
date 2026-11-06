<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('insurance_titles', function (Blueprint $table) {
            $table->unsignedInteger('settlement_revision')->default(0);
            $table->timestamp('coverage_confirmed_at')->nullable();
            $table->foreignId('coverage_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('coverage_confirmation_note')->nullable();
            $table->char('coverage_confirmation_fingerprint', 64)->nullable();
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('insurance_titles')->whereNotNull('coverage_confirmed_at')->exists()) {
            throw new RuntimeException('Conserva le conferme di copertura già registrate prima di rimuovere questi campi.');
        }
        Schema::table('insurance_titles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coverage_confirmed_by');
            $table->dropColumn(['settlement_revision', 'coverage_confirmed_at', 'coverage_confirmation_note', 'coverage_confirmation_fingerprint']);
        });
    }
};
