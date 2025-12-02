<?php

namespace App\Events\Setup;

use App\Events\BroadcastEvent;
use App\States\BoardState;
use App\States\GameState;
use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;

#[AppliesToState(BoardState::class)]
#[AppliesToState(GameState::class)]
class BoardCreated extends Event
{
    public function __construct(
        public int $game_id,
        public ?int $board_id = null,
    ) {}

    public function validateGame(GameState $game)
    {
        $this->assert(
            $game->board_id === null,
            'Game already has a board.'
        );
    }

    public function applyToBoard(BoardState $board)
    {
        $board->game_id = $this->game_id;
        $board->setup();
    }

    public function applyToGame(GameState $game)
    {
        $game->board_id = $this->board_id ?? snowflake_id();
    }

    public function handle(GameState $gameState, BoardState $boardState)
    {
        $broadcastEvent = new BroadcastEvent;
        $broadcastEvent->setGameState($gameState);
        $broadcastEvent->setBoardState($boardState);
        $broadcastEvent->setEvent(self::class);
        event($broadcastEvent);
    }
}
