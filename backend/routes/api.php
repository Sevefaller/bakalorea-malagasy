<?php
use Illuminate\Support\Facades\{Route,Broadcast,DB};
use App\Http\Controllers\GameController;
use App\Http\Controllers\AdminController;
use App\Http\Middleware\ActiveSession;

Route::get('/health',function () { DB::select('SELECT 1'); return ['status'=>'ok']; });
Route::get('/config',fn()=> ['realtime'=>config('broadcasting.default')==='reverb','key'=>env('REVERB_APP_KEY'),'host'=>env('REVERB_PUBLIC_HOST'),'port'=>(int)env('REVERB_PUBLIC_PORT',8080),'scheme'=>env('REVERB_PUBLIC_SCHEME','http')]);
Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminController::class, 'login'])->middleware('throttle:5,1,admin-login:');
    Route::get('/status', [AdminController::class, 'status']);
    Route::get('/dashboard', [AdminController::class, 'dashboard']);
    Route::delete('/games/finished', [AdminController::class, 'destroyFinished']);
    Route::get('/games/{game}/players', [AdminController::class, 'players']);
    Route::delete('/games/{game}', [AdminController::class, 'destroyGame']);
    Route::post('/logout', [AdminController::class, 'logout']);
});
Route::middleware('throttle:12,1')->group(function () { Route::post('/games',[GameController::class,'create']); Route::post('/games/{code}/join',[GameController::class,'join']); });
Route::middleware(['auth:sanctum','throttle:240,1'])->group(function () {
    Route::post('/session/claim',[GameController::class,'claim']);
    Route::middleware(ActiveSession::class)->group(function () {
        Broadcast::routes(['middleware'=>['auth:sanctum',ActiveSession::class]]);
        Route::get('/games/{code}',[GameController::class,'show']);
        Route::post('/games/{game}/start',[GameController::class,'start']);
        Route::post('/games/{game}/rounds/start',[GameController::class,'start']);
        Route::post('/games/{game}/leave',[GameController::class,'leave']);
        // Keep action limits separate from the room's frequent status polling.
        Route::patch('/rounds/{round}/answer',[GameController::class,'answer'])->middleware('throttle:120,1,answers:');
        Route::post('/answers/{answer}/votes',[GameController::class,'vote']);
        Route::post('/answers/{answer}/decision',[GameController::class,'decide']);
        Route::post('/rounds/{round}/comments',[GameController::class,'comment'])->middleware('throttle:30,1,comments:');
        Route::post('/rounds/{round}/finish-judging',[GameController::class,'finish']);
        Route::post('/anti-cheat/events',[GameController::class,'antiCheat']);
        Route::get('/games/{code}/scores',[GameController::class,'show']);
    });
});
Broadcast::channel('game.{code}',fn ($player,$code) => !$player->left_at && $player->game->code === $code);
