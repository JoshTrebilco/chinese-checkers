<?php

namespace App\States;

use Thunk\Verbs\State;

class PlayerState extends State
{
    public bool $setup = false;

    public string $name;

    public ?int $board_id = null;

    public function board(): ?BoardState
    {
        return $this->board_id ? BoardState::load($this->board_id) : null;
    }
}
