<?php

namespace App\Http\Controllers;

use App\Events\Gameplay\TokenMoved;
use App\Events\Setup\GameStarted;
use App\Events\Setup\PlayerColorSelected;
use App\Events\Setup\PlayerJoinedGame;
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

        verb(new PlayerColorSelected(
            game_id: $game_id,
            player_id: $player_id,
            color: $request->color,
        ));

        verb(new PlayerJoinedGame(
            game_id: $game_id,
            player_id: $player_id,
            name: $user->name,
        ));

        return redirect()->route('games.show', $game_id);
    }

    public function startGame(int $game_id)
    {
        $game = GameState::load($game_id);

        $player_id = $game->players()->random()->id;

        verb(new GameStarted(
            game_id: $game_id,
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

        $game_state = GameState::load($game_id);
        $board_state = BoardState::load($game_state->board_id);

        // Get token_id from the board state
        $token = $board_state->getTokenAtPosition($validated['from_q'], $validated['from_r']);
        $token_id = $token?->id;

        verb(new TokenMoved(
            game_id: $game_id,
            board_id: $game_state->board_id,
            player_id: $player_id,
            from_q: $validated['from_q'],
            from_r: $validated['from_r'],
            to_q: $validated['to_q'],
            to_r: $validated['to_r'],
            token_id: $token_id,
        ));

        return response()->json(['success' => true]);
    }
}
