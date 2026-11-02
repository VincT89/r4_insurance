<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('supplier_companies', function (Blueprint $table) {
            foreach (['company_name','pec','reference_role','reference_email','address'] as $field) $table->string($field)->nullable();
            $table->string('vat_number',20)->nullable()->index();
            $table->string('tax_code',20)->nullable()->index();
            foreach (['mobile','reference_phone'] as $field) $table->string($field,50)->nullable();
            foreach (['city','country'] as $field) $table->string($field,100)->nullable();
            $table->string('province',5)->nullable();
            $table->string('postal_code',10)->nullable();
        });
        foreach (['supplier_companies','supplier_agencies'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                /** Il campo contiene l'IBAN cifrato dal modello, quindi richiede più spazio del codice in chiaro. */
                $table->text('iban')->nullable();
                $table->string('account_holder')->nullable();
                $table->string('bank_name')->nullable();
                $table->string('bic',11)->nullable();
                $table->string('billing_email')->nullable();
                $table->string('sdi_code',7)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['supplier_companies','supplier_agencies'] as $name) Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['iban','account_holder','bank_name','bic','billing_email','sdi_code']));
        Schema::table('supplier_companies', fn (Blueprint $table) => $table->dropColumn(['company_name','pec','reference_role','reference_email','address','vat_number','tax_code','mobile','reference_phone','city','country','province','postal_code']));
    }
};
