<?php
namespace App\Http\Controllers;

use App\Models\{Game, GamePlayer};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, Hash};

class AdminController extends Controller {
    private function cookie(Request $request, string $value, int $minutes) {
        return cookie(config('admin.cookie'), $value, $minutes, '/', null,
            $request->isSecure() || (bool) config('session.secure'), true, false, 'lax');
    }

    private function authorized(Request $request): bool {
        $token = $request->cookie(config('admin.cookie'));
        if (!is_string($token) || strlen($token) !== 64 || !ctype_xdigit($token)) return false;
        $data = Cache::get('admin-session:'.hash('sha256', $token));
        return is_array($data)
            && hash_equals(config('admin.username'), (string) ($data['username'] ?? ''))
            && hash_equals(hash('sha256', config('admin.password_hash')), (string) ($data['credential'] ?? ''));
    }

    public function login(Request $request) {
        $fields = $request->validate(['username' => 'required|string|max:80', 'password' => 'required|string']);
        abort_unless(
            hash_equals(config('admin.username'), $fields['username'])
            && Hash::check($fields['password'], config('admin.password_hash')),
            401, 'invalid_credentials'
        );
        $token = bin2hex(random_bytes(32));
        Cache::put('admin-session:'.hash('sha256', $token), [
            'username' => config('admin.username'),
            'credential' => hash('sha256', config('admin.password_hash')),
        ], now()->addHours(8));
        return response()->json(['ok' => true, 'username' => config('admin.username')])
            ->cookie($this->cookie($request, $token, 480));
    }

    public function status(Request $request) {
        abort_unless($this->authorized($request), 401);
        return response()->json(['username' => config('admin.username')])->header('Cache-Control', 'no-store');
    }

    public function dashboard(Request $request) {
        abort_unless($this->authorized($request), 401);
        $games = Game::query()->withCount([
            'players', 'rounds',
            'players as online_players_count' => fn ($query) => $query->whereNull('left_at')->where('last_seen_at', '>=', now()->subSeconds(45)),
        ])->latest()->limit(20)->get(['id', 'code', 'name', 'status', 'created_at']);
        return response()->json([
            'totals' => [
                'games' => Game::count(),
                'active_games' => Game::where('status', 'playing')->count(),
                'finished_games' => Game::where('status', 'finished')->count(),
                'players' => GamePlayer::count(),
                'online_players' => GamePlayer::whereNull('left_at')->where('last_seen_at', '>=', now()->subSeconds(45))->count(),
            ],
            'games' => $games,
        ])->header('Cache-Control', 'no-store');
    }

    public function logout(Request $request) {
        $token = $request->cookie(config('admin.cookie'));
        if (is_string($token) && strlen($token) === 64 && ctype_xdigit($token)) Cache::forget('admin-session:'.hash('sha256', $token));
        return response()->json(['ok' => true])->cookie($this->cookie($request, '', -1));
    }
}
