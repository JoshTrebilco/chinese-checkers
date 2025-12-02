<?php

namespace App\Events\Setup;

use App\Events\BroadcastEvent;
use App\States\BoardState;
use App\States\PlayerState;
use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;

#[AppliesToState(BoardState::class)]
class TokensPlaced extends Event
{
    public function __construct(
        public int $board_id,
        public int $player_id,
    ) {}

    public function validatePlayer(PlayerState $player)
    {
        // Reload player to ensure we have the latest state (color might have been set by PlayerJoinedGame)
        $player = PlayerState::load($this->player_id);
        $this->assert(
            $player->color !== null,
            'Player must have a color assigned before placing tokens.'
        );
    }

    public function validateBoard(BoardState $board)
    {
        $player = PlayerState::load($this->player_id);
        $startingPositions = $board->getStartingPositionsForColor($player->color);
        
        $this->assert(
            count($startingPositions) === 10,
            'Starting positions must have exactly 10 positions.'
        );

        // Check that all starting positions are empty
        foreach ($startingPositions as $pos) {
            $cell = $board->getCell($pos['q'], $pos['r']);
            $this->assert(
                $cell !== null && $cell['piece'] === null,
                "Starting position ({$pos['q']}, {$pos['r']}) is already occupied."
            );
        }
    }

    public function applyToBoard(BoardState $board)
    {
        $player = PlayerState::load($this->player_id);
        $startingPositions = $board->getStartingPositionsForColor($player->color);

        // Place 10 tokens in starting positions
        foreach ($startingPositions as $pos) {
            $key = "{$pos['q']},{$pos['r']}";
            if (isset($board->cells[$key])) {
                $board->cells[$key]['piece'] = $this->player_id;
            }
        }
    }

    public function handle(BoardState $boardState)
    {
        $broadcastEvent = new BroadcastEvent;
        $game = $boardState->game();
        if ($game) {
            $broadcastEvent->setGameState($game);
        }
        $broadcastEvent->setBoardState($boardState);
        $broadcastEvent->setEvent(self::class);
        event($broadcastEvent);
    }
}
