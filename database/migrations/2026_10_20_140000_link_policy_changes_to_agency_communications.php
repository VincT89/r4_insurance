<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('agency_communication_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('agency_communication_id')->constrained('agency_communications')->cascadeOnDelete();
            $table->foreignId('insurance_policy_change_id')->constrained('insurance_policy_changes')->restrictOnDelete();
            $table->char('delivery_key', 64)->nullable()->unique();
            $table->unique(['agency_communication_id', 'insurance_policy_change_id'], 'agency_change_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_communication_changes');
    }
};
