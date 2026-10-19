<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::table('attachments', fn (Blueprint $table) => $table->string('identity_type', 30)->nullable()); }
    public function down(): void { Schema::table('attachments', fn (Blueprint $table) => $table->dropColumn('identity_type')); }
};
