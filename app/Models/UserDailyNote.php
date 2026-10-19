<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDailyNote extends Model
{
    protected $fillable = [
        'user_id',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function entries()
    {
        return $this->hasMany(UserDailyNoteEntry::class)->orderBy('sort_order');
    }
}
