<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registry_import_entries', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20);
            $table->string('source_key', 64);
            $table->char('batch_hash', 64)->index();
            $table->char('payload_hash', 64);
            $table->string('source', 30);
            $table->string('source_name');
            $table->string('status', 20)->index();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('source_rows');
            $table->json('issues');
            $table->json('deferred_data');
            $table->timestamps();
            $table->unique(['kind', 'source_key']);
            $table->index(['kind', 'entity_id']);
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('registry_import_entries')->exists()) {
            throw new RuntimeException('Il registro contiene importazioni: conservarlo insieme alle anagrafiche.');
        }
        Schema::dropIfExists('registry_import_entries');
    }
};
