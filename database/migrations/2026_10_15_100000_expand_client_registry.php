<?php

use App\Support\ClientIdentity;
use App\Support\PhoneNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('preferred_contact_channel', 30)->nullable();
            $table->string('preferred_contact_time')->nullable();
            $table->text('contact_preferences')->nullable();
            $table->string('normalized_mobile', 50)->nullable()->index();
            $table->string('registry_name_key')->nullable()->index();
            $table->foreignId('merged_into_id')->nullable()->constrained('clients')->restrictOnDelete();
            $table->timestamp('merged_at')->nullable();
        });
        DB::table('clients')->orderBy('id')->chunkById(200, function ($clients) {
            $normalizer = new PhoneNormalizer;
            foreach ($clients as $client) {
                DB::table('clients')->where('id', $client->id)->update([
                    'registry_name_key' => ClientIdentity::nameKey($client->name),
                    'normalized_phone' => $normalizer->normalize($client->phone),
                    'normalized_mobile' => $normalizer->normalize($client->mobile),
                ]);
            }
        });

        Schema::create('client_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('role')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('mobile', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('client_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('asset_type', 20);
            $table->string('name');
            $table->string('category', 150);
            $table->string('identifier')->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 5)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('country', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::table('insurance_policies', function (Blueprint $table) {
            $table->foreignId('client_asset_id')->nullable()->constrained('client_assets')->restrictOnDelete();
            $table->string('subcategory', 100)->nullable();
        });
        Schema::create('client_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->dateTime('event_at')->index();
            $table->string('channel', 30);
            $table->string('subject');
            $table->text('notes');
            $table->string('outcome', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('client_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('purpose', 200);
            $table->string('kind', 30);
            $table->string('scope', 20);
            $table->foreignId('insurance_policy_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('signer_name');
            $table->string('signer_role')->nullable();
            $table->string('channel', 30)->nullable();
            $table->string('status', 20);
            $table->string('notice_reference');
            $table->dateTime('event_at')->index();
            $table->foreignId('attachment_id')->nullable()->constrained('attachments')->restrictOnDelete();
            $table->text('evidence_reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['client_id', 'purpose', 'channel'], 'client_consents_purpose_index');
        });
        Schema::create('client_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('related_client_id')->constrained('clients')->restrictOnDelete();
            $table->string('relationship_type', 30);
            $table->string('client_role', 150)->nullable();
            $table->string('related_role', 150)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['client_id', 'related_client_id']);
        });
        Schema::create('client_households', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('client_household_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('role', 150)->nullable();
            $table->timestamps();
            $table->unique(['client_household_id', 'client_id'], 'client_household_members_unique');
        });
        Schema::create('client_merges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('target_id')->constrained('clients')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('snapshot');
            $table->json('choices');
            $table->timestamps();
            $table->unique('source_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_merges');
        Schema::dropIfExists('client_household_members');
        Schema::dropIfExists('client_households');
        Schema::dropIfExists('client_relationships');
        Schema::dropIfExists('client_consents');
        Schema::dropIfExists('client_interactions');
        Schema::table('insurance_policies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_asset_id');
            $table->dropColumn('subcategory');
        });
        Schema::dropIfExists('client_assets');
        Schema::dropIfExists('client_contacts');
        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merged_into_id');
            $table->dropColumn(['merged_at', 'preferred_contact_channel', 'preferred_contact_time', 'contact_preferences', 'normalized_mobile', 'registry_name_key']);
        });
    }
};
