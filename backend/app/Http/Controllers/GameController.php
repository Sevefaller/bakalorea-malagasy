<?php
namespace App\Http\Controllers;
use App\Models\{Game,GamePlayer,Round,Answer};
use App\Services\GameEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Log};
use Illuminate\Support\Str;

class GameController extends Controller {
    public function __construct(private GameEngine $engine) {}
    private function identity(Request $r): array { return $r->validate(['nickname'=>'required|string|min:2|max:24','locale'=>'required|in:mg,fr,en','session_id'=>'required|uuid']); }
    private function member(Request $r, Game $game): GamePlayer { $p=$r->user(); abort_unless($p->game_id === $game->id,403,'wrong_game'); return $p; }
    private function credentials(Game $game, GamePlayer $p): array { return ['code'=>$game->code,'token'=>$p->createToken('game', ['*'], now()->addDays(7))->plainTextToken]; }

    public function create(Request $r) {
        $identity=$this->identity($r);
        $settings=$r->validate(['name'=>'required|string|max:60','target_score'=>'required|integer|min:10|max:2000','answer_duration'=>'required|integer|in:10,15,30','anti_cheat_mode'=>'required|in:soft,normal,strict','letters'=>'required|string|regex:/^[A-Z]{2,26}$/','no_repeat'=>'required|boolean','unique_points'=>'required|integer|min:1|max:100','duplicate_points'=>'required|integer|min:0|lte:unique_points']);
        $settings['letters']=implode('',array_unique(str_split($settings['letters'])));
        abort_if(strlen($settings['letters'])<2,422,'invalid_letters');
        return DB::transaction(function () use ($identity,$settings) {
            do { $code=Str::upper(Str::random(6)); } while (Game::where('code',$code)->exists());
            $game=Game::create([...$settings,'code'=>$code]);
            $p=$game->players()->create([...$identity,'nickname_key'=>GameEngine::normalize($identity['nickname']),'last_seen_at'=>now()]);
            $game->update(['host_id'=>$p->id]);
            foreach (DB::table('categories')->orderBy('sort_order')->get() as $c) DB::table('game_categories')->insert(['game_id'=>$game->id,'category_id'=>$c->id,'position'=>$c->sort_order]);
            return response()->json($this->credentials($game,$p),201);
        });
    }
    public function join(Request $r,string $code) {
        $identity=$this->identity($r); $game=Game::where('code',strtoupper($code))->firstOrFail();
        $p=$this->engine->locked($game,function (Game $game) use ($identity) {
            abort_unless(in_array($game->status,['lobby','playing']),409,'game_finished');
            abort_if($game->players()->whereNull('left_at')->count()>=12,422,'room_full');
            $key=GameEngine::normalize($identity['nickname']);
            abort_if($game->players()->where('nickname_key',$key)->exists(),422,'nickname_taken');
            return $game->players()->create([...$identity,'nickname_key'=>$key,'last_seen_at'=>now()]);
        });
        $this->engine->notify($game,'PlayerJoined');
        return response()->json($this->credentials($game,$p),201);
    }
    public function claim(Request $r) {
        $v=$r->validate(['session_id'=>'required|uuid']);
        $player=$r->user(); $wasAway=(bool)$player->left_at;
        $this->engine->locked($player->game,function (Game $game) use ($player,$v) {
            if ($player->left_at) abort_if($game->players()->whereNull('left_at')->count()>=12,422,'room_full');
            if ($player->session_id!==$v['session_id']) Log::notice('Player session replaced',['player_id'=>$player->id]);
            $player->update(['session_id'=>$v['session_id'],'last_seen_at'=>now(),'left_at'=>null]);
        });
        if ($wasAway) $this->engine->notify($player->game,'PlayerRejoined');
        return response()->json(['ok'=>true]);
    }
    public function show(Request $r,string $code) {
        $game=Game::where('code',strtoupper($code))->firstOrFail(); $p=$this->member($r,$game);
        $p->update(['last_seen_at'=>now()]);
        return response()->json($this->engine->snapshot($game,$p))->header('Cache-Control','no-store, private');
    }
    public function start(Request $r,Game $game) { $this->engine->start($game,$this->member($r,$game)); return response()->json(['ok'=>true]); }
    public function answer(Request $r,Round $round) { $p=$this->member($r,$round->game); $v=$r->validate(['answer'=>'present|nullable|string|max:120','revision'=>'required|integer|min:1|max:100000']); $this->engine->answer($round,$p,$v['answer']??'',$v['revision']); return response()->json(['ok'=>true,'revision'=>$v['revision']]); }
    public function vote(Request $r,Answer $answer) { $p=$this->member($r,$answer->round->game); $v=$r->validate(['vote'=>'required|in:valid,invalid,uncertain']); $this->engine->vote($answer,$p,$v['vote']); return response()->json(['ok'=>true]); }
    public function decide(Request $r,Answer $answer) { $p=$this->member($r,$answer->round->game); $v=$r->validate(['valid'=>'required|boolean']); $this->engine->decide($answer,$p,$v['valid']); return response()->json(['ok'=>true]); }
    public function comment(Request $r,Round $round) {
        $p=$this->member($r,$round->game);
        $v=$r->validate(['body'=>'required|string|max:300','client_id'=>'sometimes|uuid']);
        $body=trim($v['body']);
        abort_if($body==='',422,'empty_comment');
        $result=$this->engine->comment($round,$p,$body,$v['client_id']??(string)Str::uuid());
        return response()->json(['ok'=>true,'id'=>$result['id']],$result['created']?201:200);
    }
    public function finish(Request $r,Round $round) { $this->engine->finish($round,$this->member($r,$round->game)); return response()->json(['ok'=>true]); }
    public function antiCheat(Request $r) { $v=$r->validate(['round_id'=>'required|integer','event'=>'required|in:away,back']); $round=Round::findOrFail($v['round_id']); $this->engine->antiCheat($round,$this->member($r,$round->game),$v['event']); return response()->json(['ok'=>true]); }
    public function leave(Request $r,Game $game) {
        $p=$this->member($r,$game);
        $this->engine->locked($game,function () use ($p) { $p->update(['left_at'=>now()]); });
        $this->engine->notify($game,'PlayerLeft'); return response()->json(['ok'=>true]);
    }
}
