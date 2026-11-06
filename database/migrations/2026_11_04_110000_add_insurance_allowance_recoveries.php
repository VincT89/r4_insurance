<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('insurance_allowances', function (Blueprint $table) {
            $table->unsignedBigInteger('recovered_cents')->default(0);
            $table->unsignedInteger('recovery_revision')->default(0);
        });
        Schema::create('insurance_allowance_recoveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_allowance_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->date('received_on');
            $table->string('method', 20);
            $table->string('reference', 150)->nullable();
            $table->text('notes')->nullable();
            $table->uuid('idempotency_key')->unique();
            $table->char('request_fingerprint', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamps();
            $table->index(['received_on', 'id']);
        });
    }

    public function down(): void
    {
        /** I rientri e le rettifiche devono rimanere nello storico anche dopo uno storno. */
        if (Schema::hasTable('insurance_allowance_recoveries') && DB::table('insurance_allowance_recoveries')->exists()) {
            throw new RuntimeException('Sono presenti rientri da conservare. Ripristina un backup verificato.');
        }
        Schema::dropIfExists('insurance_allowance_recoveries');
        Schema::table('insurance_allowances', fn (Blueprint $table) => $table->dropColumn(['recovered_cents', 'recovery_revision']));
    }
};
