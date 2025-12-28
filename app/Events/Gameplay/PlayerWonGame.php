<?php

namespace App\Events\Gameplay;

use App\Events\BroadcastEvent;
use App\States\GameState;
use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;

#[AppliesToState(GameState::class)]
class PlayerWonGame extends Event
{
    public function __construct(
        public int $game_id,
        public int $player_id,
    ) {}

    public function validateGame(GameState $game)
    {
        $this->assert($game->winner_id === null, 'A player has already won the game.');
    }

    public function applyToGame(GameState $game)
    {
        $game->winner_id = $this->player_id;
        $game->ended = true;
    }

    public function handle(GameState $game)
    {
        $broadcastEvent = new BroadcastEvent;
        $broadcastEvent->setGameState($game);
        $broadcastEvent->setEvent(self::class);
        event($broadcastEvent);
    }
}
