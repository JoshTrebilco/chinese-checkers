<?php

namespace App\Events\Gameplay;

use App\States\TokenState;
use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;

#[AppliesToState(TokenState::class)]
class TokenPositionUpdated extends Event
{
    public function __construct(
        public int $token_id,
        public int $q,
        public int $r,
    ) {}

    public function applyToTokenState(TokenState $token)
    {
        if ($token->id === $this->token_id) {
            $token->q = $this->q;
            $token->r = $this->r;
        }
    }
}

