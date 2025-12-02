<?php

namespace App\Events\Setup;

use App\States\BoardState;
use App\States\TokenState;
use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;

#[AppliesToState(BoardState::class)]
#[AppliesToState(TokenState::class)]
class TokenCreated extends Event
{
    public function __construct(
        public int $board_id,
        public int $player_id,
        public int $q,
        public int $r,
        public int $token_id,
    ) {}

    public function applyToBoard(BoardState $board)
    {
        // Add token ID to board's token_ids array
        if (!in_array($this->token_id, $board->token_ids ?? [])) {
            $board->token_ids[] = $this->token_id;
        }
    }

    public function applyToTokenState(TokenState $token)
    {
        $token->board_id = $this->board_id;
        $token->player_id = $this->player_id;
        $token->q = $this->q;
        $token->r = $this->r;
        $token->valid_moves = [];
    }
}

