<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('agency_communications', function (Blueprint $table) {
            $table->unsignedInteger('attachment_revision')->default(0);
        });
        Schema::create('agency_communication_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('agency_communication_id')->constrained('agency_communications')->cascadeOnDelete();
            $table->foreignId('source_attachment_id')->nullable()->constrained('attachments')->nullOnDelete();
            $table->text('details');
            $table->string('path');
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->boolean('included')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_communication_attachments');
        Schema::table('agency_communications', fn (Blueprint $table) => $table->dropColumn('attachment_revision'));
    }
};
