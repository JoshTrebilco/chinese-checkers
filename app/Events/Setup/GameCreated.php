<?php

namespace App\Events\Setup;

use App\Events\BroadcastEvent;
use App\States\GameState;
use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;

#[AppliesToState(GameState::class)]
class GameCreated extends Event
{
    public function __construct(
        public ?int $game_id = null,
    ) {}

    public function validate(GameState $game)
    {
        $this->assert(! $game->created, 'The game has already been created');
    }

    public function applyToGame(GameState $game)
    {
        $game->created = true;
        $game->created_at = now()->toImmutable();
        $game->player_ids = [];
    }

    public function handle(GameState $game)
    {
        $broadcastEvent = new BroadcastEvent;
        $broadcastEvent->setGameState($game);
        $broadcastEvent->setEvent(self::class);
        event($broadcastEvent);
    }
}
