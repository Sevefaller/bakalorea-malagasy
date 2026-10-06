<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Answer extends Model {
    public $timestamps = false;
    protected $guarded = [];
    protected $dateFormat = 'Y-m-d H:i:s.u';
    protected function casts(): array { return ['is_locked'=>'boolean','flagged'=>'boolean','verdict'=>'boolean','tie_decision'=>'boolean','last_saved_at'=>'datetime']; }
    public function round() { return $this->belongsTo(Round::class); }
}
