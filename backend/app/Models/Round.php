<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Round extends Model {
    protected $guarded = [];
    protected $dateFormat = 'Y-m-d H:i:s.u';
    protected function casts(): array { return ['started_at'=>'datetime','answer_deadline'=>'datetime','finished_at'=>'datetime']; }
    public function game() { return $this->belongsTo(Game::class); }
    public function answers() { return $this->hasMany(Answer::class); }
}
