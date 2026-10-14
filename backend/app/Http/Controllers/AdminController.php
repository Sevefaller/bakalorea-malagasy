<?php
namespace App\Http\Controllers;

use App\Models\{Game, GamePlayer};
use App\Services\{CategoryCatalog, GameCleanup, RetentionPolicy};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, Hash, Validator};
use Illuminate\Validation\ValidationException;

class AdminController extends Controller {
    public function __construct(private GameCleanup $cleanup, private RetentionPolicy $retention) {}
    private function cookie(Request $request, string $value, int $minutes) {
        return cookie(config('admin.cookie'), $value, $minutes, '/', null,
            $request->isSecure() || (bool) config('session.secure'), true, false, 'lax');
    }

    private function session(Request $request): ?array {
        $token = $request->cookie(config('admin.cookie'));
        if (!is_string($token) || strlen($token) !== 64 || !ctype_xdigit($token)) return null;
        $data = Cache::get('admin-session:'.hash('sha256', $token));
        return is_array($data)
            && hash_equals(config('admin.username'), (string) ($data['username'] ?? ''))
            && hash_equals(hash('sha256', config('admin.password_hash')), (string) ($data['credential'] ?? ''))
            && is_string($data['csrf'] ?? null) && strlen($data['csrf']) === 64 && ctype_xdigit($data['csrf'])
            ? $data : null;
    }

    private function authorized(Request $request): bool { return $this->session($request) !== null; }

    private function authorizeWrite(Request $request): void {
        $session = $this->session($request);
        abort_unless($session, 401);
        abort_unless(hash_equals($session['csrf'], (string) $request->header('X-Admin-CSRF')), 403);
    }

    public function login(Request $request) {
        $fields = $request->validate(['username' => 'required|string|max:80', 'password' => 'required|string']);
        abort_unless(
            hash_equals(config('admin.username'), $fields['username'])
            && Hash::check($fields['password'], config('admin.password_hash')),
            401, 'invalid_credentials'
        );
        $token = bin2hex(random_bytes(32));
        $csrf = bin2hex(random_bytes(32));
        Cache::put('admin-session:'.hash('sha256', $token), [
            'username' => config('admin.username'),
            'credential' => hash('sha256', config('admin.password_hash')),
            'csrf' => $csrf,
        ], now()->addHours(8));
        return response()->json(['ok' => true, 'username' => config('admin.username'), 'csrf' => $csrf])->header('Cache-Control', 'no-store')
            ->cookie($this->cookie($request, $token, 480));
    }

    public function status(Request $request) {
        abort_unless($this->authorized($request), 401);
        return response()->json(['username' => config('admin.username'), 'csrf' => $this->session($request)['csrf']])->header('Cache-Control', 'no-store');
    }

    public function dashboard(Request $request) {
        abort_unless($this->authorized($request), 401);
        $request->validate(['page' => 'sometimes|integer|min:1|max:1000000']);
        $filter = $request->query('filter', 'all');
        abort_unless(in_array($filter, ['all', 'finished', 'lobby', 'stale'], true), 422);
        $pagination = Game::query()
            ->when($filter === 'finished', fn ($query) => $query->where('status', 'finished'))
            ->when($filter === 'lobby', fn ($query) => $query->where('status', 'lobby'))
            ->when($filter === 'stale', fn ($query) => $query->where('status', 'playing')->whereHas('rounds', fn ($rounds) => $rounds->where('number', 1)->where('started_at', '<', now()->subHours(3))))
            ->withMin('rounds as first_round_started_at', 'started_at')->withCount([
            'players', 'rounds',
            'players as online_players_count' => fn ($query) => $query->whereNull('left_at')->where('last_seen_at', '>=', now()->subSeconds(45)),
        ])->orderByDesc('created_at')->orderByDesc('id')->paginate(20, ['id', 'code', 'name', 'status', 'created_at']);
        $games = $pagination->getCollection()
            ->each(fn (Game $game) => $game->setAttribute('can_delete', $this->cleanup->canDelete($game, $game->first_round_started_at)));
        return response()->json([
            'totals' => [
                'games' => Game::count(),
                'active_games' => Game::where('status', 'playing')->count(),
                'finished_games' => Game::where('status', 'finished')->count(),
                'players' => GamePlayer::count(),
                'online_players' => GamePlayer::whereNull('left_at')->where('last_seen_at', '>=', now()->subSeconds(45))->count(),
            ],
            'games' => $games,
            'pagination' => [
                'page' => $pagination->currentPage(),
                'pages' => $pagination->lastPage(),
                'total' => $pagination->total(),
            ],
            'finished_retention_days' => $this->retention->days(),
        ])->header('Cache-Control', 'no-store');
    }

    public function destroyGame(Request $request, Game $game) {
        $this->authorizeWrite($request);
        $this->cleanup->remove($game);
        return response()->json(['deleted' => 1])->header('Cache-Control', 'no-store');
    }

    public function players(Request $request, Game $game) {
        abort_unless($this->authorized($request), 401);
        $players = $game->players()->orderBy('id')->get(['id', 'nickname', 'score', 'last_seen_at', 'left_at'])
            ->map(fn (GamePlayer $player) => [
                'id' => $player->id,
                'nickname' => $player->nickname,
                'score' => $player->score,
                'online' => $player->left_at === null && $player->last_seen_at?->gte(now()->subSeconds(45)),
                'left' => $player->left_at !== null,
            ]);
        return response()->json(['players' => $players])->header('Cache-Control', 'no-store');
    }

    public function destroyFinished(Request $request) {
        $this->authorizeWrite($request);
        $ids = Game::where('status', 'finished')->orderBy('id')->limit(50)->pluck('id');
        $deleted = 0;
        foreach ($ids as $id) {
            $game = Game::find($id);
            if ($game && $this->cleanup->remove($game)) $deleted++;
        }
        return response()->json([
            'deleted' => $deleted,
            'remaining' => Game::where('status', 'finished')->count(),
        ])->header('Cache-Control', 'no-store');
    }

    public function updateRetention(Request $request) {
        $this->authorizeWrite($request);
        $days = (int) $request->validate(['days' => 'required|integer|min:0|max:3650'])['days'];
        $this->retention->setDays($days);
        return response()->json(['finished_retention_days' => $days])->header('Cache-Control', 'no-store');
    }

    public function categories(Request $request, CategoryCatalog $catalog) {
        abort_unless($this->authorized($request), 401);
        return response()->json(['categories' => $catalog->all()])->header('Cache-Control', 'no-store');
    }

    private function validateCategories(array $rows, bool $creating = true): array {
        foreach ($rows as &$row) {
            if (!is_array($row)) continue;
            if (isset($row['code']) && is_string($row['code'])) $row['code'] = trim($row['code']);
            foreach (['mg', 'fr', 'en'] as $locale) {
                if (isset($row['names'][$locale]) && is_string($row['names'][$locale])) $row['names'][$locale] = trim($row['names'][$locale]);
            }
        }
        unset($row);
        $rules = [
            'categories' => 'required|array|min:1|max:200',
            'categories.*' => 'required|array',
            'categories.*.difficulty' => 'required|integer|in:1,2,3',
            'categories.*.names' => 'required|array:mg,fr,en',
            'categories.*.names.mg' => 'required|string|max:255',
            'categories.*.names.fr' => 'required|string|max:255',
            'categories.*.names.en' => 'required|string|max:255',
        ];
        if ($creating) $rules['categories.*.code'] = ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct', 'unique:categories,code'];
        return Validator::make(['categories' => $rows], $rules, [
            'required' => 'Le champ :attribute est obligatoire.',
            'unique' => 'Le code :input existe déjà. Modifiez la catégorie dans la liste.',
            'distinct' => 'Le code :input apparaît plusieurs fois dans le fichier.',
            'regex' => 'Le code doit commencer par une lettre minuscule et contenir uniquement a-z, 0-9 et _.',
            'in' => 'Choisissez un niveau entre 1 et 3.',
            'max' => 'Le champ :attribute dépasse la limite autorisée (:max).',
        ])->validate()['categories'];
    }

    public function createCategory(Request $request, CategoryCatalog $catalog) {
        $this->authorizeWrite($request);
        $rows = $this->validateCategories([$request->only(['code', 'difficulty', 'names'])]);
        $ids = $catalog->create($rows);
        return response()->json(['created' => count($ids)], 201)->header('Cache-Control', 'no-store');
    }

    public function updateCategory(Request $request, int $category, CategoryCatalog $catalog) {
        $this->authorizeWrite($request);
        $rows = $this->validateCategories([$request->only(['difficulty', 'names'])], false);
        $catalog->update($category, $rows[0]);
        return response()->json(['ok' => true])->header('Cache-Control', 'no-store');
    }

    public function importCategories(Request $request, CategoryCatalog $catalog) {
        $this->authorizeWrite($request);
        $request->validate(['file' => 'required|file|max:512', 'preview' => 'required|boolean']);
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $rows = [];
        if ($extension === 'json') {
            try { $rows = json_decode(file_get_contents($file->getRealPath()), true, 32, JSON_THROW_ON_ERROR); }
            catch (\JsonException $e) { throw ValidationException::withMessages(['file' => 'Fichier JSON invalide. Utilisez le modèle intégré.']); }
            if (!is_array($rows) || !array_is_list($rows)) throw ValidationException::withMessages(['file' => 'Le JSON doit contenir une liste de catégories.']);
        } elseif ($extension === 'csv') {
            $stream = fopen($file->getRealPath(), 'r');
            try {
                $first = fgets($stream);
                if ($first === false) throw ValidationException::withMessages(['file' => 'Le fichier CSV est vide.']);
                $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
                $separator = str_contains($first, ';') ? ';' : ',';
                $header = array_map('trim', str_getcsv($first, $separator, '"', ''));
                if ($header !== ['code', 'difficulty', 'mg', 'fr', 'en']) throw ValidationException::withMessages(['file' => 'Colonnes attendues : code,difficulty,mg,fr,en.']);
                $line = 1;
                while (($values = fgetcsv($stream, 0, $separator, '"', '')) !== false) {
                    $line++;
                    if ($values === [null]) continue;
                    if (count($values) !== 5) throw ValidationException::withMessages(['file' => "Ligne $line : 5 colonnes attendues."]);
                    $rows[] = ['code' => $values[0], 'difficulty' => trim($values[1]), 'names' => ['mg' => $values[2], 'fr' => $values[3], 'en' => $values[4]]];
                    if (count($rows) > 200) throw ValidationException::withMessages(['file' => 'Maximum 200 catégories par import.']);
                }
            } finally { fclose($stream); }
        } else {
            throw ValidationException::withMessages(['file' => 'Choisissez un fichier CSV ou JSON.']);
        }
        $rows = $this->validateCategories($rows);
        if ($request->boolean('preview')) return response()->json(['categories' => $rows])->header('Cache-Control', 'no-store');
        $ids = $catalog->create($rows);
        return response()->json(['created' => count($ids)], 201)->header('Cache-Control', 'no-store');
    }

    public function categoryTemplate(Request $request) {
        abort_unless($this->authorized($request), 401);
        $format = $request->query('format', 'csv');
        abort_unless(in_array($format, ['csv', 'json'], true), 422);
        $examples = [
            ['code' => 'musical_instrument', 'difficulty' => 2, 'names' => ['mg' => 'Zavamaneno', 'fr' => 'Instrument de musique', 'en' => 'Musical instrument']],
            ['code' => 'scientist', 'difficulty' => 3, 'names' => ['mg' => 'Mpahay siansa', 'fr' => 'Scientifique', 'en' => 'Scientist']],
        ];
        if ($format === 'json') $body = json_encode($examples, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        else {
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['code', 'difficulty', 'mg', 'fr', 'en'], ',', '"', '');
            foreach ($examples as $row) fputcsv($stream, [$row['code'], $row['difficulty'], ...array_values($row['names'])], ',', '"', '');
            rewind($stream); $body = stream_get_contents($stream); fclose($stream);
        }
        return response($body)->header('Content-Type', $format === 'csv' ? 'text/csv; charset=UTF-8' : 'application/json')
            ->header('Content-Disposition', 'attachment; filename="modele-categories.'.$format.'"')->header('Cache-Control', 'no-store');
    }

    public function logout(Request $request) {
        $token = $request->cookie(config('admin.cookie'));
        if (is_string($token) && strlen($token) === 64 && ctype_xdigit($token)) Cache::forget('admin-session:'.hash('sha256', $token));
        return response()->json(['ok' => true])->cookie($this->cookie($request, '', -1));
    }
}
