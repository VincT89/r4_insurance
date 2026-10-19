<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDailyNoteEntry extends Model
{
    protected $fillable = [
        'user_daily_note_id',
        'content',
        'post_script',
        'sort_order',
    ];

    public function userDailyNote()
    {
        return $this->belongsTo(UserDailyNote::class);
    }

    public function checklistItems()
    {
        return $this->hasMany(UserDailyNoteChecklistItem::class)->orderBy('sort_order');
    }
}
