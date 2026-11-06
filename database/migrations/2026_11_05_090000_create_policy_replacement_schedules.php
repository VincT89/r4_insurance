<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('policy_replacement_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_policy_change_id')->unique()->constrained()->restrictOnDelete();
            $table->date('effective_on');
            $table->string('state', 16)->default('pending');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->index(['state', 'effective_on']);
        });
    }

    public function down(): void
    {
        if (DB::table('policy_replacement_schedules')->exists()) throw new RuntimeException('Sono presenti sostituzioni programmate da conservare. Ripristina un backup verificato.');
        Schema::dropIfExists('policy_replacement_schedules');
    }
};
