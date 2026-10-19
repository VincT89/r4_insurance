<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        foreach (['team_user', 'teams'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Il modulo Team contiene dati: verificare la copia preventiva prima della rimozione.');
            }
        }
        Schema::dropIfExists('team_user');
        Schema::dropIfExists('teams');
    }

    public function down(): void
    {
        throw new RuntimeException('La rimozione del modulo Team non prevede il ripristino automatico.');
    }
};
