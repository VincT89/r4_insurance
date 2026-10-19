<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $this->guardSchemaChange();
        $legacy = ['administration', 'developer', 'marketing', 'photographer', 'graphic_designer', 'operations_manager', 'commercial'];
        if (DB::table('users')->whereNull('role')->orWhereNotIn('role', [...$legacy, 'admin', 'operator'])->exists()) {
            throw new RuntimeException('Ruoli utente sconosciuti: verificarli prima della migrazione.');
        }

        DB::transaction(function () use ($legacy) {
            foreach (DB::table('users')->whereIn('role', $legacy)->lockForUpdate()->get(['id', 'role']) as $user) {
                DB::table('users')->where('id', $user->id)->update(['role' => 'operator', 'remember_token' => null]);
                DB::table('sessions')->where('user_id', $user->id)->delete();
                DB::table('audit_logs')->insert([
                    'user_id' => null, 'action' => 'roles_migrated', 'auditable_type' => 'App\\Models\\User', 'auditable_id' => $user->id,
                    'old_values' => json_encode(['role' => $user->role]), 'new_values' => json_encode(['role' => 'operator']),
                    'description' => 'Account #'.$user->id.': conversione del ruolo precedente in Operatore; nuovo accesso richiesto.', 'created_at' => now(),
                ]);
            }
        });

        Schema::table('users', fn (Blueprint $table) => $table->enum('role', ['admin', 'operator'])->default('operator')->change());
    }

    public function down(): void
    {
        $this->guardSchemaChange();
        /** Reverting the column type must not restore obsolete permissions or guess former roles. */
        Schema::table('users', fn (Blueprint $table) => $table->string('role')->default('operator')->change());
    }

    private function guardSchemaChange(): void
    {
        /** SQLite rebuilds the users table; an outer transaction would prevent safe foreign-key handling. */
        if (DB::getDriverName() === 'sqlite' && DB::transactionLevel() > 0) {
            throw new RuntimeException('La migrazione dei ruoli SQLite deve essere eseguita senza una transazione esterna.');
        }
    }
};
