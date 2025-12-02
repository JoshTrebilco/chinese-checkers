<?php

namespace App\Events\Setup;

use App\Events\BroadcastEvent;
use App\Events\Setup\TokenCreated;
use App\States\GameState;
use App\States\BoardState;
use App\States\PlayerState;
use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;

#[AppliesToState(GameState::class)]
#[AppliesToState(BoardState::class)]
#[AppliesToState(PlayerState::class)]
class TokensPlaced extends Event
{
    public function __construct(
        public int $game_id,
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

    public function fired(BoardState $board)
    {
        $player = PlayerState::load($this->player_id);
        $startingPositions = $board->getStartingPositionsForColor($player->color);

        // Create TokenState for each token
        foreach ($startingPositions as $pos) {
            TokenCreated::fire(
                board_id: $this->board_id,
                player_id: $this->player_id,
                q: $pos['q'],
                r: $pos['r'],
                token_id: snowflake_id(),
            );
        }

        // Recalculate valid moves for all tokens after all are created
        $board = BoardState::load($this->board_id);
        $board->recalculateAllTokenMoves();
    }

    public function handle(GameState $gameState, BoardState $boardState, PlayerState $playerState)
    {
        // Reload board to get updated token states
        $boardState = BoardState::load($this->board_id);
        
        // Add tokens to boardState for frontend
        $boardStateArray = (array) $boardState;
        $boardStateArray['tokens'] = $boardState->getTokensArray();
        
        $broadcastEvent = new BroadcastEvent;
        $broadcastEvent->setGameState($gameState);
        $broadcastEvent->setPlayerState($playerState);
        $broadcastEvent->setBoardState((object) $boardStateArray);
        $broadcastEvent->setEvent(self::class);
        event($broadcastEvent);
    }
}
