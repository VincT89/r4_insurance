<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        /** A deleted notification keeps its reminder history; the link intentionally has no cascading foreign key. */
        Schema::table('insurance_deadline_reminders',fn(Blueprint $table)=>$table->uuid('notification_id')->nullable()->unique());
        if (DB::connection()->getDriverName()==='mysql') {
            /** Scan each notification once and probe the existing composite reminder key. */
            DB::statement("UPDATE notifications n STRAIGHT_JOIN insurance_deadline_reminders r
                ON r.user_id=n.notifiable_id
                AND r.kind=JSON_UNQUOTE(JSON_EXTRACT(n.data,'$.kind'))
                AND r.source_id=JSON_UNQUOTE(JSON_EXTRACT(n.data,'$.source_id'))
                AND r.due_on=JSON_UNQUOTE(JSON_EXTRACT(n.data,'$.due_on'))
                SET r.notification_id=n.id WHERE n.notifiable_type=? AND n.type=? AND JSON_VALID(n.data)",
                [(new \App\Models\User)->getMorphClass(),\App\Services\DeadlineReminders::TYPE]);
        } else {
            DB::statement("UPDATE insurance_deadline_reminders SET notification_id=(
                SELECT n.id FROM notifications n WHERE n.notifiable_id=insurance_deadline_reminders.user_id
                AND n.notifiable_type=? AND n.type=? AND json_valid(n.data)
                AND json_extract(n.data,'$.kind')=insurance_deadline_reminders.kind
                AND json_extract(n.data,'$.source_id')=insurance_deadline_reminders.source_id
                AND json_extract(n.data,'$.due_on')=date(insurance_deadline_reminders.due_on)
                ORDER BY n.id LIMIT 1)",[(new \App\Models\User)->getMorphClass(),\App\Services\DeadlineReminders::TYPE]);
        }
    }

    public function down(): void
    {
        Schema::table('insurance_deadline_reminders',function(Blueprint $table) {
            $table->dropUnique(['notification_id']);
            $table->dropColumn('notification_id');
        });
    }
};
