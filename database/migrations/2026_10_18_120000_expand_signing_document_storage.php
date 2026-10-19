<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /** Encryption expands the saved text beyond the original form length. */
        foreach (['insurance_form_templates'=>'body','insurance_form_documents'=>'body','insurance_form_packages'=>'snapshot'] as $table=>$column) {
            Schema::table($table, function (Blueprint $t) use ($column) { $t->longText($column)->change(); });
        }
    }
    public function down(): void
    {
        /** Keep the larger capacity on rollback to avoid truncating existing encrypted documents. */
    }
};
