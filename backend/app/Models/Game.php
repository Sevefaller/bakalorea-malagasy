<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Game extends Model {
    protected $guarded = [];
    protected function casts(): array { return ['used_letters'=>'array','no_repeat'=>'boolean']; }
    public function players() { return $this->hasMany(GamePlayer::class); }
    public function rounds() { return $this->hasMany(Round::class); }
}
