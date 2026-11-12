<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('ai_settings')) Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->text('api_key')->nullable();
            $table->boolean('enabled')->default(false);
            $table->string('model', 64)->default('gpt-6.1-terra');
            $table->unsignedBigInteger('monthly_budget_microusd')->default(10000000);
            $table->unsignedBigInteger('document_budget_microusd')->default(250000);
            $table->uuid('revision');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        if(!Schema::hasColumns('ai_settings',['id','api_key','enabled','model','revision','monthly_budget_microusd','document_budget_microusd'])) throw new RuntimeException('Struttura delle impostazioni AI non riconosciuta. Nessuna configurazione sovrascritta.');
        DB::table('ai_settings')->insertOrIgnore(['id'=>1,'revision'=>(string)\Illuminate\Support\Str::uuid(),'created_at'=>now(),'updated_at'=>now()]);
        if (!Schema::hasTable('ai_usages')) Schema::create('ai_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 20)->default('openai');
            $table->string('feature', 50);
            $table->string('model', 64);
            $table->string('state', 20);
            $table->string('request_key', 64)->unique();
            $table->string('document_hash', 64);
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('cached_input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('reasoning_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->unsignedBigInteger('input_rate');
            $table->unsignedBigInteger('cached_rate');
            $table->unsignedBigInteger('output_rate');
            $table->unsignedBigInteger('cost_microusd')->nullable();
            $table->unsignedBigInteger('reserved_microusd')->default(0);
            $table->string('response_id', 100)->nullable();
            $table->string('error_code', 50)->nullable();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reconciled_at')->nullable();
            $table->text('reconciliation_note')->nullable();
            $table->longText('result')->nullable();
            $table->dateTime('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['started_at','state']);
            $table->index(['user_id','started_at']);
        });
        if(!Schema::hasColumns('ai_usages',['id','request_key','document_hash','state','cost_microusd','reserved_microusd','started_at'])) throw new RuntimeException('Struttura del registro AI non riconosciuta. Nessun consumo cancellato.');
        if(!Schema::hasIndex('ai_usages',['request_key'],'unique')) Schema::table('ai_usages',fn(Blueprint $table)=>$table->unique('request_key','ai_usages_request_key_unique'));
    }

    public function down(): void
    {
        if(DB::table('ai_usages')->exists()) throw new RuntimeException('Il registro dei consumi AI fa parte dello storico.');
        Schema::dropIfExists('ai_usages');
        Schema::dropIfExists('ai_settings');
    }
};
