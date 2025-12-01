<?php

namespace App\Events\Setup;

use App\Events\BroadcastEvent;
use App\States\BoardState;
use App\States\GameState;
use App\States\PlayerState;
use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;

#[AppliesToState(BoardState::class)]
#[AppliesToState(PlayerState::class)]
class BoardCreated extends Event
{
    public function __construct(
        public int $player_id,
        public ?int $board_id = null,
    ) {}

    public function validatePlayer(PlayerState $player)
    {
        $this->assert(
            $player->board_id === null,
            'Player already has a board.'
        );
    }

    public function applyToBoard(BoardState $board)
    {
        $board->player_id = $this->player_id;
        $board->setup();
    }

    public function applyToPlayer(PlayerState $player)
    {
        $player->board_id = $this->board_id;
    }

    public function handle(GameState $gameState, PlayerState $player)
    {
        $broadcastEvent = new BroadcastEvent;
        $broadcastEvent->setGameState($gameState);
        $broadcastEvent->setPlayerState($player);
        $broadcastEvent->setEvent(self::class);
        event($broadcastEvent);
    }
}
