<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('supplier_companies', fn(Blueprint $t)=>$t->boolean('quote_priority')->default(false));
        Schema::create('insurance_quote_cases',function(Blueprint $t){
            $t->id(); $t->foreignId('client_id')->constrained()->restrictOnDelete();
            $t->foreignId('insurance_branch_id')->constrained()->restrictOnDelete();
            $t->foreignId('insurance_subbranch_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('subject'); $t->text('notes')->nullable(); $t->string('status',20)->default('open');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps();
        });
        Schema::create('insurance_quotes',function(Blueprint $t){
            $t->id(); $t->foreignId('insurance_quote_case_id')->constrained()->restrictOnDelete();
            $t->foreignId('supplier_company_id')->constrained()->restrictOnDelete();
            $t->foreignId('supplier_agency_id')->nullable()->constrained()->restrictOnDelete();
            $t->boolean('priority')->default(false); $t->string('status',20)->default('requested'); $t->dateTime('requested_at');
            $t->string('reference',100)->nullable(); $t->string('product')->nullable(); $t->date('starts_on')->nullable(); $t->date('expires_on')->nullable(); $t->date('valid_until')->nullable();
            $t->unsignedBigInteger('premium_cents')->nullable(); $t->string('payment_frequency',30)->nullable();
            $t->dateTime('received_at')->nullable(); $t->dateTime('submitted_at')->nullable(); $t->date('approved_on')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete(); $t->text('approval_note')->nullable(); $t->timestamps();
            $t->unique(['insurance_quote_case_id','supplier_company_id'],'quote_case_company_unique');
        });
        Schema::table('insurance_policies',function(Blueprint $t){
            $t->foreignId('insurance_quote_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->date('coverage_until')->nullable(); $t->date('coverage_origin_on')->nullable(); $t->dateTime('closed_at')->nullable();
        });
        Schema::create('insurance_titles',function(Blueprint $t){
            $t->id(); $t->foreignId('insurance_policy_id')->constrained()->restrictOnDelete();
            $t->string('kind',20); $t->string('reference',100)->nullable(); $t->date('due_on'); $t->date('coverage_from'); $t->date('coverage_to');
            $t->unsignedBigInteger('amount_cents'); $t->unsignedBigInteger('collected_cents')->default(0);
            $t->string('payment_method',150); $t->string('payment_frequency',30); $t->text('notes')->nullable();
            $t->dateTime('cancelled_at')->nullable(); $t->text('cancellation_reason')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps();
            $t->index(['due_on','cancelled_at']);
        });
        Schema::create('insurance_collections',function(Blueprint $t){
            $t->id(); $t->foreignId('insurance_title_id')->constrained()->restrictOnDelete();
            $t->date('received_on'); $t->unsignedBigInteger('amount_cents'); $t->string('payment_method',150); $t->string('recipient',20); $t->string('document_type',30);
            $t->string('reference',150)->nullable(); $t->text('notes')->nullable(); $t->uuid('idempotency_key')->unique(); $t->char('request_fingerprint',64);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('consultant_id')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('voided_at')->nullable(); $t->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete(); $t->text('void_reason')->nullable(); $t->timestamps();
            $t->index(['received_on','voided_at']);
        });
        Schema::table('attachments',function(Blueprint $t){
            $t->foreignId('insurance_quote_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('insurance_title_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('insurance_collection_id')->nullable()->constrained()->restrictOnDelete();
        });
    }
    public function down(): void
    {
        Schema::table('attachments',function(Blueprint $t){foreach(['insurance_quote_id','insurance_title_id','insurance_collection_id'] as $column)$t->dropConstrainedForeignId($column);});
        Schema::dropIfExists('insurance_collections'); Schema::dropIfExists('insurance_titles');
        Schema::table('insurance_policies',function(Blueprint $t){$t->dropConstrainedForeignId('insurance_quote_id');$t->dropColumn(['coverage_until','coverage_origin_on','closed_at']);});
        Schema::dropIfExists('insurance_quotes'); Schema::dropIfExists('insurance_quote_cases');
        Schema::table('supplier_companies',fn(Blueprint $t)=>$t->dropColumn('quote_priority'));
    }
};
