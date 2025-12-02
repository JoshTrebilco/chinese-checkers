<?php

namespace App\Events\Gameplay;

use App\Events\BroadcastEvent;
use App\States\BoardState;
use App\States\PlayerState;
use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;

#[AppliesToState(BoardState::class)]
class TokenMoved extends Event
{
    public function __construct(
        public int $board_id,
        public int $player_id,
        public int $from_q,
        public int $from_r,
        public int $to_q,
        public int $to_r,
    ) {}

    public function validateBoard(BoardState $board)
    {
        // Validate from position exists and has player's token
        $fromCell = $board->getCell($this->from_q, $this->from_r);
        $this->assert(
            $fromCell !== null,
            "From position ({$this->from_q}, {$this->from_r}) is not on the board."
        );
        
        $this->assert(
            $fromCell['piece'] === $this->player_id,
            "From position ({$this->from_q}, {$this->from_r}) does not contain player's token."
        );

        // Validate to position exists and is empty
        $toCell = $board->getCell($this->to_q, $this->to_r);
        $this->assert(
            $toCell !== null,
            "To position ({$this->to_q}, {$this->to_r}) is not on the board."
        );
        
        $this->assert(
            $toCell['piece'] === null,
            "To position ({$this->to_q}, {$this->to_r}) is already occupied."
        );

        // Validate move is to an adjacent position
        $adjacentPositions = $board->getAdjacentPositions($this->from_q, $this->from_r);
        $isAdjacent = false;
        foreach ($adjacentPositions as $adj) {
            if ($adj['q'] === $this->to_q && $adj['r'] === $this->to_r) {
                $isAdjacent = true;
                break;
            }
        }
        
        $this->assert(
            $isAdjacent,
            "To position ({$this->to_q}, {$this->to_r}) is not adjacent to from position ({$this->from_q}, {$this->from_r})."
        );
    }

    public function applyToBoard(BoardState $board)
    {
        // Clear from position
        $fromKey = "{$this->from_q},{$this->from_r}";
        if (isset($board->cells[$fromKey])) {
            $board->cells[$fromKey]['piece'] = null;
        }

        // Set to position
        $toKey = "{$this->to_q},{$this->to_r}";
        if (isset($board->cells[$toKey])) {
            $board->cells[$toKey]['piece'] = $this->player_id;
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
        
        // Set event as object with type and move data
        $broadcastEvent->setEvent([
            'type' => self::class,
            'from_q' => $this->from_q,
            'from_r' => $this->from_r,
            'to_q' => $this->to_q,
            'to_r' => $this->to_r,
            'player_id' => $this->player_id,
        ]);
        
        $player = PlayerState::load($this->player_id);
        $broadcastEvent->setPlayerState($player);
        event($broadcastEvent);
    }
}
