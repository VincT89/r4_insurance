<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /** Resolve each recipient's notification types without rereading the complete inbox for every source. */
        Schema::table('notifications', fn (Blueprint $table) => $table->index(['notifiable_type', 'notifiable_id', 'type'], 'notifications_recipient_type'));
    }

    public function down(): void
    {
        Schema::table('notifications', fn (Blueprint $table) => $table->dropIndex('notifications_recipient_type'));
    }
};
