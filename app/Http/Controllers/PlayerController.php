<?php

namespace App\Http\Controllers;

use App\Events\Gameplay\TokenMoved;
use App\Events\Setup\PlayerJoinedGame;
use App\Events\Setup\TokensPlaced;
use App\States\BoardState;
use App\States\GameState;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class PlayerController extends Controller
{
    public function join(Request $request, int $game_id)
    {
        $player_id = snowflake_id();
        $user = Auth::user();

        $user->update(['current_player_id' => $player_id]);

        verb(new PlayerJoinedGame(
            game_id: $game_id,
            player_id: $player_id,
            name: $user->name,
        ));

        return redirect()->route('games.show', $game_id);
    }

    public function placeTokens(Request $request, int $game_id, int $player_id)
    {
        if (Auth::user()->current_player_id != $player_id) {
            return redirect()->route('games.show', $game_id);
        }

        $game = GameState::load($game_id);
        $board = $game->board();

        if (! $board) {
            return back()->withErrors('Board is not ready yet.');
        }

        verb(new TokensPlaced(
            board_id: $board->id,
            player_id: $player_id,
        ));

        return redirect()->route('games.show', $game_id);
    }

    public function moveToken(Request $request, int $game_id, int $player_id)
    {
        if (Auth::user()->current_player_id != $player_id) {
            return redirect()->route('games.show', $game_id);
        }

        $validated = $request->validate([
            'from_q' => 'required|integer',
            'from_r' => 'required|integer',
            'to_q' => 'required|integer',
            'to_r' => 'required|integer',
        ]);

        $game = GameState::load($game_id);
        $board = $game->board();

        if (! $board) {
            return back()->withErrors('Board is not ready yet.');
        }

        verb(new TokenMoved(
            board_id: $board->id,
            player_id: $player_id,
            from_q: $validated['from_q'],
            from_r: $validated['from_r'],
            to_q: $validated['to_q'],
            to_r: $validated['to_r'],
        ));

        return response()->json(['success' => true]);
    }
}

