<?php

namespace App\Http\Controllers;

use App\Events\Setup\BoardCreated;
use App\Events\Setup\GameCreated;
use App\States\GameState;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class GameController extends Controller
{
    public function index()
    {
        return view('game.index');
    }

    public function show(Request $request, int $game_id)
    {
        if (! Auth::check()) {
            return redirect()->route('login.index', ['game_id' => $game_id]);
        }

        return view('game.show', [
            'game' => GameState::load($game_id),
            'auth_player_id' => Auth::user()->current_player_id,
        ]);
    }

    public function store(Request $request)
    {
        $event = GameCreated::fire();

        BoardCreated::fire(game_id: $event->game_id);

        return redirect()->route('games.show', $event->game_id);
    }
}

