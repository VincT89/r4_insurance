<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgencyCommunicationAttachment extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['path', 'details', 'sha256'];
    protected function casts(): array { return ['details'=>'encrypted:array', 'size'=>'integer', 'included'=>'boolean']; }
    public function communication() { return $this->belongsTo(AgencyCommunication::class, 'agency_communication_id'); }
}
