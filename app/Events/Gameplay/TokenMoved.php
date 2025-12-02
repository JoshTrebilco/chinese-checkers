<?php

namespace App\Events\Gameplay;

use App\Events\BroadcastEvent;
use App\States\BoardState;
use App\States\GameState;
use App\States\PlayerState;
use App\States\TokenState;
use Thunk\Verbs\Attributes\Autodiscovery\AppliesToState;
use Thunk\Verbs\Event;

#[AppliesToState(GameState::class)]
#[AppliesToState(BoardState::class)]
#[AppliesToState(PlayerState::class)]
#[AppliesToState(TokenState::class)]
class TokenMoved extends Event
{
    public function __construct(
        public int $game_id,
        public int $board_id,
        public int $player_id,
        public int $from_q,
        public int $from_r,
        public int $to_q,
        public int $to_r,
        public ?int $token_id = null,
    ) {}

    public function validateGame(GameState $game)
    {
        $this->assert($game->isInProgress(), 'The game is not in progress.');
        $this->assert($game->last_player_id !== $this->player_id, 'It is not your turn.');
    }

    public function validateBoard(BoardState $board)
    {
        // Validate from position exists on the board
        $fromCell = $board->getCell($this->from_q, $this->from_r);
        $this->assert(
            $fromCell !== null,
            "From position ({$this->from_q}, {$this->from_r}) is not on the board."
        );

        // Validate to position exists on the board
        $toCell = $board->getCell($this->to_q, $this->to_r);
        $this->assert(
            $toCell !== null,
            "To position ({$this->to_q}, {$this->to_r}) is not on the board."
        );

        // Find token at from position - TokenState is the source of truth
        $token = $board->getTokenAtPosition($this->from_q, $this->from_r);
        $this->assert(
            $token !== null,
            "Token not found at position ({$this->from_q}, {$this->from_r})."
        );
        
        $this->assert(
            $token->player_id === $this->player_id,
            "Token at position ({$this->from_q}, {$this->from_r}) does not belong to player."
        );

        // Set token_id if not already set (needed for Verbs to apply to TokenState)
        if ($this->token_id === null) {
            $this->token_id = $token->id;
        }

        // Validate to position is empty (check both TokenState and cells array)
        $toToken = $board->getTokenAtPosition($this->to_q, $this->to_r);
        $this->assert(
            $toToken === null,
            "To position ({$this->to_q}, {$this->to_r}) is already occupied."
        );

        // Ensure valid moves are calculated
        if (empty($token->valid_moves)) {
            $token->calculateValidMoves($board);
        }

        // Validate move is in token's valid_moves list
        $isValidMove = false;
        foreach ($token->valid_moves as $move) {
            if ($move['q'] === $this->to_q && $move['r'] === $this->to_r) {
                $isValidMove = true;
                break;
            }
        }
        
        $this->assert(
            $isValidMove,
            "Move to position ({$this->to_q}, {$this->to_r}) is not a valid move for this token."
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

    public function applyToToken(TokenState $token)
    {
        // Update token position if it matches the token_id
        if ($token->id === $this->token_id) {
            $token->q = $this->to_q;
            $token->r = $this->to_r;
        }
    }

    public function fired(BoardState $board)
    {
        // Recalculate valid moves for all tokens after move
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
        $broadcastEvent->setBoardState((object) $boardStateArray);
        $broadcastEvent->setPlayerState($playerState);

        $broadcastEvent->setEvent(self::class);
        // Set event as object with type and move data
        $broadcastEvent->setEvent([
            'type' => self::class,
            'from_q' => $this->from_q,
            'from_r' => $this->from_r,
            'to_q' => $this->to_q,
            'to_r' => $this->to_r,
            'player_id' => $this->player_id,
        ]);

        event($broadcastEvent);
    }
}
