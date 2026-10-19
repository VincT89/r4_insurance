<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDailyNoteChecklistItem extends Model
{
    protected $fillable = [
        'user_daily_note_entry_id',
        'label',
        'is_completed',
        'sort_order',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
    ];

    public function userDailyNoteEntry()
    {
        return $this->belongsTo(UserDailyNoteEntry::class);
    }
}
