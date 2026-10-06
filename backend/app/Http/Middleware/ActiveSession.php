<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class ActiveSession {
    public function handle(Request $request, Closure $next) {
        $player = $request->user();
        abort_if($player->left_at, 403, 'left_game');
        abort_unless(hash_equals($player->session_id, (string)$request->header('X-Session-ID')), 409, 'session_replaced');
        return $next($request);
    }
}
