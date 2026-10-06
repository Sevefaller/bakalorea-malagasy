<?php
namespace App\Events;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
class GameChanged implements ShouldBroadcastNow {
    use Dispatchable;
    public function __construct(public string $code, public string $reason) {}
    public function broadcastOn(): array { return [new PrivateChannel('game.'.$this->code)]; }
    public function broadcastAs(): string { return 'GameChanged'; }
    // No answers, tokens or per-player data are ever placed on the shared channel.
    public function broadcastWith(): array { return ['reason'=>$this->reason]; }
}
