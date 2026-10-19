<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::table('insurance_policies', function (Blueprint $table) { $table->unsignedTinyInteger('merit_class_cu')->nullable(); $table->string('merit_class_internal', 30)->nullable(); }); }
    public function down(): void { Schema::table('insurance_policies', fn (Blueprint $table) => $table->dropColumn(['merit_class_cu', 'merit_class_internal'])); }
};
