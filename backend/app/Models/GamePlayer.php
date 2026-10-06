<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
class GamePlayer extends Authenticatable {
    use HasApiTokens;
    protected $guarded = [];
    protected $hidden = ['session_id','nickname_key'];
    protected function casts(): array { return ['last_seen_at'=>'datetime','left_at'=>'datetime']; }
    public function game() { return $this->belongsTo(Game::class); }
}
