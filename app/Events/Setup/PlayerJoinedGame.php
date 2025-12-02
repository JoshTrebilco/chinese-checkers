<?php

namespace App\Events\Setup;

use App\Events\BroadcastEvent;
use App\States\GameState;
use App\States\PlayerState;
use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;

#[AppliesToState(PlayerState::class)]
#[AppliesToState(GameState::class)]
class PlayerJoinedGame extends Event
{
    public function __construct(
        public int $game_id,
        public int $player_id,
        public string $name,
    ) {}

    public function validateGame(GameState $game)
    {
        $this->assert(
            count($game->player_ids) < 6,
            'Game is full (maximum 6 players).'
        );
    }

    public function validatePlayer(PlayerState $player)
    {
        $this->assert(
            ! $player->setup,
            'Player has already joined.'
        );
    }

    public function applyToPlayer(PlayerState $player)
    {
        $player->name = $this->name;
        $player->setup = true;
        
        // Auto-assign color based on join order
        $game = GameState::load($this->game_id);
        $colors = ['blue', 'red', 'yellow', 'green', 'teal', 'purple'];
        $usedColors = collect($game->player_ids)
            ->reject(fn (int $id) => $id === $this->player_id) // Exclude current player
            ->map(fn (int $id) => PlayerState::load($id))
            ->pluck('color')
            ->filter()
            ->toArray();
        
        $availableColors = array_values(array_diff($colors, $usedColors));
        $player->color = $availableColors[0] ?? null;
    }

    public function applyToGame(GameState $game)
    {
        if (! in_array($this->player_id, $game->player_ids)) {
            $game->player_ids[] = $this->player_id;
        }
    }

    public function handle(GameState $gameState)
    {
        $broadcastEvent = new BroadcastEvent;
        $broadcastEvent->setGameState($gameState);
        $broadcastEvent->setEvent(self::class);
        $player = PlayerState::load($this->player_id);
        $broadcastEvent->setPlayerState($player);
        event($broadcastEvent);
    }
}
