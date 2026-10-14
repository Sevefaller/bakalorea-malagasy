<?php
namespace App\Services;

use Illuminate\Support\Facades\{Cache, DB};
use Illuminate\Validation\ValidationException;

class CategoryCatalog {
    public function all(): array {
        $translations = DB::table('category_translations')->get()->groupBy('category_id');
        return DB::table('categories')->orderBy('sort_order')->orderBy('id')->get()->map(fn ($category) => [
            'id' => $category->id,
            'code' => $category->code,
            'difficulty' => (int) $category->difficulty,
            'names' => ($translations->get($category->id) ?? collect())->pluck('name', 'locale')->all(),
        ])->all();
    }

    public function create(array $rows): array {
        // Serialize catalogue additions and lock rooms while extending their pool.
        return Cache::lock('category-catalog', 60)->block(5, fn () => DB::transaction(function () use ($rows) {
            $existing = DB::table('categories')->whereIn('code', array_column($rows, 'code'))->pluck('code')->all();
            if ($existing) throw ValidationException::withMessages(['categories' => 'Codes déjà existants : '.implode(', ', $existing)]);
            $position = (int) DB::table('categories')->max('sort_order') + 1;
            $ids = [];
            foreach ($rows as $row) {
                $id = DB::table('categories')->insertGetId(['code' => $row['code'], 'sort_order' => $position++, 'difficulty' => $row['difficulty']]);
                $this->saveNames($id, $row['names']);
                $ids[] = $id;
            }
            DB::table('games')->whereIn('status', ['lobby', 'playing'])->orderBy('id')->chunkById(100, function ($games) use ($ids) {
                foreach ($games as $game) {
                    $locked = DB::table('games')->where('id', $game->id)->lockForUpdate()->first();
                    if (!$locked || !in_array($locked->status, ['lobby', 'playing'], true)) continue;
                    $next = (int) DB::table('game_categories')->where('game_id', $game->id)->max('position') + 1;
                    foreach ($ids as $id) {
                        DB::table('game_categories')->insert(['game_id' => $game->id, 'category_id' => $id, 'position' => $next++]);
                    }
                }
            });
            return $ids;
        }));
    }

    public function update(int $id, array $row): void {
        DB::transaction(function () use ($id, $row) {
            abort_unless(DB::table('categories')->where('id', $id)->lockForUpdate()->first(), 404);
            DB::table('categories')->where('id', $id)->update(['difficulty' => $row['difficulty']]);
            $this->saveNames($id, $row['names']);
        });
    }

    private function saveNames(int $id, array $names): void {
        foreach (['mg', 'fr', 'en'] as $locale) {
            DB::table('category_translations')->updateOrInsert(['category_id' => $id, 'locale' => $locale], ['name' => $names[$locale]]);
        }
    }
}
