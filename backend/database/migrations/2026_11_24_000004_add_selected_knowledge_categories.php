<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        $rows = json_decode(file_get_contents(resource_path('data/selected_categories_2026_11_24.json')),true,512,JSON_THROW_ON_ERROR);
        DB::transaction(function () use ($rows) {
            $position = (int)DB::table('categories')->max('sort_order') + 1;
            $ids = [];
            foreach ($rows as [$code,$difficulty,$fr,$mg,$en,$exFr,$exMg,$exEn]) {
                $id = DB::table('categories')->where('code',$code)->value('id');
                if (!$id) {
                    $id = DB::table('categories')->insertGetId(['code'=>$code,'difficulty'=>$difficulty,'sort_order'=>$position++]);
                    foreach (['fr'=>[$fr,$exFr],'mg'=>[$mg,$exMg],'en'=>[$en,$exEn]] as $locale=>[$name,$example]) {
                        DB::table('category_translations')->insert(['category_id'=>$id,'locale'=>$locale,'name'=>$name,'example'=>$example]);
                    }
                }
                $ids[] = $id;
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
        // Preserve the catalogue and played-round history.
    }
};
