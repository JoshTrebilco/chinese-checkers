<?php

namespace App\States;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Thunk\Verbs\State;

class GameState extends State
{
    public bool $created = false;

    public ?int $last_player_id = null;

    public array $player_ids = [];

    public ?int $active_player_id = null;

    public ?int $winner_id = null;

    public bool $ended = false;

    public bool $started = false;

    public CarbonImmutable $created_at;

    /** @return Collection<int, PlayerState> */
    public function players(): Collection
    {
        return collect($this->player_ids)->map(fn (int $id) => PlayerState::load($id));
    }

    public function activePlayer(): ?PlayerState
    {
        return $this->active_player_id ? PlayerState::load($this->active_player_id) : null;
    }

    public function lastPlayer(): ?PlayerState
    {
        return $this->last_player_id ? PlayerState::load($this->last_player_id) : null;
    }

    public function winner(): ?PlayerState
    {
        return $this->winner_id ? PlayerState::load($this->winner_id) : null;
    }

    public function isInProgress(): bool
    {
        return $this->activePlayer() !== null && ! $this->ended;
    }

    public function hasPlayer(PlayerState|int|null $player): bool
    {
        if (! $player) {
            return false;
        }

        if ($player instanceof PlayerState) {
            $player = $player->id;
        }

        return in_array($player, $this->player_ids);
    }

    public function hasAllPlayersJoined(): bool
    {
        return count($this->player_ids) == 2;
    }

    public function moveToNextPlayer(): static
    {
        $active_index = array_search($this->active_player_id, $this->player_ids);

        $this->active_player_id = $this->player_ids[$active_index + 1] ?? $this->player_ids[0];

        return $this;
    }

    /** @return Collection<int, BoardState> */
    public function boards(): Collection
    {
        return $this->players()->map(fn (PlayerState $player) => $player->board())->filter();
    }
}
