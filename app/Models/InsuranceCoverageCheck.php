<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsuranceCoverageCheck extends Model
{
    public const URL = 'https://www.ilportaledellautomobilista.it/web/portale-automobilista/ext/verifica-copertura-rc';
    public const OUTCOMES = ['covered'=>'Copertura presente','not_covered'=>'Copertura non risultante','unavailable'=>'Verifica non conclusa'];
    protected $guarded = ['id'];
    protected function casts(): array { return ['checked_at'=>'datetime']; }
    public function policy() { return $this->belongsTo(InsurancePolicy::class,'insurance_policy_id'); }
    public function files() { return $this->hasMany(Attachment::class); }
    public function author() { return $this->belongsTo(User::class,'created_by'); }
}
