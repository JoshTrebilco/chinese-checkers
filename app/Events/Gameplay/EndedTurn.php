<?php

namespace App\Events\Gameplay;

use App\Events\BroadcastEvent;
use App\States\GameState;
use App\States\PlayerState;
use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;

#[AppliesToState(GameState::class)]
#[AppliesToState(PlayerState::class)]
class EndedTurn extends Event
{
    public function __construct(
        public int $game_id,
        public int $player_id,
    ) {}

    public function validateGame(GameState $game)
    {
        $this->assert($game->isInProgress(), 'The game is not in progress.');
    }

    public function validatePlayer(PlayerState $player)
    {
        $this->assert($player->id === $this->player_id, 'It is not your turn.');
    }

    public function applyToGame(GameState $game)
    {
        $game->last_player_id = $this->player_id;
        $game->moveToNextPlayer();
    }

    public function handle(GameState $game, PlayerState $player)
    {
        $board = $game->board();
        $boardStateForBroadcast = null;

        if ($board) {
            $boardStateForBroadcast = (object) [
                'id' => $board->id,
                'tokens' => $board->getTokensArray(),
            ];
        }

        $broadcastEvent = new BroadcastEvent;
        $broadcastEvent->setGameState($game);
        $broadcastEvent->setPlayerState($player);
        $broadcastEvent->setBoardState($boardStateForBroadcast);
        $broadcastEvent->setEvent(self::class);
        event($broadcastEvent);
    }
}
