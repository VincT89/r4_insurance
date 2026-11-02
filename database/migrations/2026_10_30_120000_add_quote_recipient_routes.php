<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('quote_recipient_groups', fn (Blueprint $table) => $table->json('recipient_keys')->nullable());
        Schema::table('insurance_quote_emails', function (Blueprint $table) {
            $table->foreignId('supplier_agency_id')->nullable()->constrained()->restrictOnDelete();
            $table->uuid('review_batch_id')->nullable()->index();
        });
        Schema::table('insurance_quotes', function (Blueprint $table) {
            /** Le richieste storiche erano indirizzate alla compagnia, anche quando la risposta indicava un'agenzia. */
            $table->string('recipient_key', 50)->default('direct');
            $table->json('request_recipient')->nullable();
            $table->unique(['insurance_quote_case_id', 'supplier_company_id', 'recipient_key'], 'quote_case_recipient_unique');
            $table->dropUnique('quote_case_company_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('insurance_quotes')->select('insurance_quote_case_id', 'supplier_company_id')->groupBy('insurance_quote_case_id', 'supplier_company_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Sono presenti richieste a più agenzie della stessa compagnia. Il ripristino richiede una verifica dello storico.');
        }
        Schema::table('insurance_quotes', function (Blueprint $table) {
            $table->unique(['insurance_quote_case_id', 'supplier_company_id'], 'quote_case_company_unique');
            $table->dropUnique('quote_case_recipient_unique');
            $table->dropColumn(['recipient_key', 'request_recipient']);
        });
        Schema::table('insurance_quote_emails', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_agency_id');
            $table->dropIndex(['review_batch_id']);
            $table->dropColumn('review_batch_id');
        });
        Schema::table('quote_recipient_groups', fn (Blueprint $table) => $table->dropColumn('recipient_keys'));
    }
};
