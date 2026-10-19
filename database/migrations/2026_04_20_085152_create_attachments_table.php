<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();

            $table->morphs('attachable');

            $table->foreignId('uploaded_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('disk')->default('public');
            $table->string('directory')->nullable();
            $table->string('path');

            $table->string('original_name');
            $table->string('stored_name');

            $table->string('mime_type')->nullable();
            $table->string('extension')->nullable();
            $table->unsignedBigInteger('size')->default(0);

            $table->text('description')->nullable();

            $table->timestamps();

            $table->index('uploaded_by');
            $table->index('mime_type');
            $table->index('extension');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};