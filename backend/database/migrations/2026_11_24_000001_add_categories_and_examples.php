<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasColumn('category_translations','example')) {
            Schema::table('category_translations', fn (Blueprint $t) => $t->string('example',255)->nullable());
        }
        $categories = json_decode(file_get_contents(resource_path('data/everyday_categories_2026_11_24.json')),true,512,JSON_THROW_ON_ERROR);
        $examples = json_decode(file_get_contents(resource_path('data/category_examples.json')),true,512,JSON_THROW_ON_ERROR);
        DB::transaction(function () use ($categories,$examples) {
            $position = (int)DB::table('categories')->max('sort_order') + 1;
            $ids = [];
            foreach ($categories as [$code,$difficulty,$fr,$mg,$en]) {
                $id = DB::table('categories')->where('code',$code)->value('id');
                if (!$id) {
                    $id = DB::table('categories')->insertGetId(['code'=>$code,'difficulty'=>$difficulty,'sort_order'=>$position++]);
                    foreach (['mg'=>$mg,'fr'=>$fr,'en'=>$en] as $locale=>$name) {
                        DB::table('category_translations')->insert(['category_id'=>$id,'locale'=>$locale,'name'=>$name]);
                    }
                }
                $ids[] = $id;
            }
            $categoryIds = DB::table('categories')->pluck('id','code');
            foreach ($examples as $code=>$translations) {
                if (!isset($categoryIds[$code])) continue;
                foreach ($translations as $locale=>$example) {
                    DB::table('category_translations')->where('category_id',$categoryIds[$code])->where('locale',$locale)
                        ->whereNull('example')->update(['example'=>$example]);
                }
            }
            DB::table('games')->whereIn('status',['lobby','playing'])->orderBy('id')->chunkById(100,function ($games) use ($ids) {
                foreach ($games as $game) {
                    $locked = DB::table('games')->where('id',$game->id)->lockForUpdate()->first();
                    if (!$locked || !in_array($locked->status,['lobby','playing'],true)) continue;
                    $next = (int)DB::table('game_categories')->where('game_id',$game->id)->max('position') + 1;
                    $existing = DB::table('game_categories')->where('game_id',$game->id)->pluck('category_id')->all();
                    foreach (array_diff($ids,$existing) as $id) DB::table('game_categories')->insert(['game_id'=>$game->id,'category_id'=>$id,'position'=>$next++]);
                }
            });
        });
    }

    public function down(): void {
        // Keep the catalogue and examples used by existing rooms and past rounds.
    }
};
