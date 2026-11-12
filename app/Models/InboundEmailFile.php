<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboundEmailFile extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['path', 'sha256'];
    protected function casts(): array { return ['original_name'=>'encrypted', 'size'=>'integer']; }
    public function email() { return $this->belongsTo(InboundEmail::class, 'inbound_email_id'); }
}
