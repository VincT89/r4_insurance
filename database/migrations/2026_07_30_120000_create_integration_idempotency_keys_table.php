<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->char('key_hash', 64);
            $table->char('request_hash', 64);
            $table->string('route');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('content_type')->nullable();
            $table->longText('response_body')->nullable();
            $table->timestamp('completed_at')->nullable();
            // DATETIME evita il default TIMESTAMP implicito non valido con
            // alcune configurazioni MySQL che disabilitano explicit_defaults.
            $table->dateTime('expires_at')->index();
            $table->timestamps();

            $table->unique(
                ['provider', 'key_hash'],
                'integration_idempotency_provider_key_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_idempotency_keys');
    }
};
