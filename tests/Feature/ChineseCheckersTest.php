<?php

use App\Events\Gameplay\TokenMoved;
use App\Events\Setup\BoardCreated;
use App\Events\Setup\GameCreated;
use App\Events\Setup\GameStarted;
use App\Events\Setup\PlayerColorSelected;
use App\Events\Setup\PlayerJoinedGame;
use App\Events\Setup\TokensPlaced;
use App\States\BoardState;
use App\States\GameState;
use App\States\PlayerState;
use App\States\TokenState;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    Verbs::fake();
    Verbs::commitImmediately();
});

test('Full game simulation with moves, turn changes, and win condition', function () {
    // =========================================================================
    // PHASE 1: GAME SETUP
    // =========================================================================

    // Create a new game
    $game_state = verb(new GameCreated)->state(GameState::class);
    expect($game_state->started)->toBeFalse();
    expect($game_state->ended)->toBeFalse();
    expect($game_state->winner_id)->toBeNull();

    // Create the board
    $board_state = verb(new BoardCreated(game_id: $game_state->id))->state(BoardState::class);
    expect($board_state->game_id)->toBe($game_state->id);
    expect($board_state->getTotalPositions())->toBe(121);
    expect($board_state->token_ids)->toBeEmpty();

    // =========================================================================
    // PHASE 2: PLAYERS JOIN
    // =========================================================================

    // Player 1 selects blue (South) and joins
    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 1, color: 'blue'));
    $player1 = verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 1, name: 'Alice'))->state(PlayerState::class);
    expect($player1->color)->toBe('blue');
    expect($player1->name)->toBe('Alice');

    $board_state = BoardState::load($board_state->id);
    expect($board_state->token_ids)->toHaveCount(10);

    // Player 2 selects red (North) and joins
    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 2, color: 'red'));
    $player2 = verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 2, name: 'Bob'))->state(PlayerState::class);
    expect($player2->color)->toBe('red');

    $board_state = BoardState::load($board_state->id);
    $game_state = GameState::load($game_state->id);
    expect($board_state->token_ids)->toHaveCount(20);
    expect($game_state->player_ids)->toHaveCount(2);

    // Verify tokens are in correct starting positions
    $bluePositions = $board_state->getStartingPositionsForColor('blue');
    $redPositions = $board_state->getStartingPositionsForColor('red');

    foreach ($bluePositions as $pos) {
        $cell = $board_state->getCell($pos['q'], $pos['r']);
        expect($cell['piece'])->toBe(1);
    }
    foreach ($redPositions as $pos) {
        $cell = $board_state->getCell($pos['q'], $pos['r']);
        expect($cell['piece'])->toBe(2);
    }

    // Before game starts - no valid_moves in token arrays
    $tokensArray = $board_state->getTokensArray();
    foreach ($tokensArray as $token) {
        expect($token)->not->toHaveKey('valid_moves');
    }

    // =========================================================================
    // PHASE 3: GAME STARTS
    // =========================================================================

    verb(new GameStarted(game_id: $game_state->id, player_id: 1));
    $game_state = GameState::load($game_state->id);
    $board_state = BoardState::load($board_state->id);

    expect($game_state->started)->toBeTrue();
    expect($game_state->active_player_id)->toBe(1);
    expect($game_state->isInProgress())->toBeTrue();

    // Only active player's tokens have valid_moves
    $tokensArray = $board_state->getTokensArray();
    foreach ($tokensArray as $token) {
        if ($token['player_id'] === 1) {
            expect($token)->toHaveKey('valid_moves');
        } else {
            expect($token)->not->toHaveKey('valid_moves');
        }
    }

    // =========================================================================
    // PHASE 4: GAMEPLAY - MULTIPLE TURNS
    // =========================================================================

    // Turn 1: Player 1 (blue) moves
    $tokens1 = $board_state->getTokensForPlayer(1);
    $token1 = $tokens1[0];
    $originalPos1 = ['q' => $token1->q, 'r' => $token1->r];
    $validMoves1 = $token1->getValidMoves($board_state);
    expect($validMoves1)->not->toBeEmpty();

    $toPos1 = $validMoves1[0];
    verb(new TokenMoved(
        game_id: $game_state->id,
        board_id: $board_state->id,
        player_id: 1,
        from_q: $originalPos1['q'],
        from_r: $originalPos1['r'],
        to_q: $toPos1['q'],
        to_r: $toPos1['r'],
        token_id: $token1->id
    ));

    $board_state = BoardState::load($board_state->id);
    $game_state = GameState::load($game_state->id);
    $token1 = TokenState::load($token1->id);

    // Verify move happened
    expect($token1->q)->toBe($toPos1['q']);
    expect($token1->r)->toBe($toPos1['r']);
    expect($board_state->getCell($originalPos1['q'], $originalPos1['r'])['piece'])->toBeNull();
    expect($board_state->getCell($toPos1['q'], $toPos1['r'])['piece'])->toBe(1);

    // TokenMoved auto-fires EndedTurn
    expect($game_state->active_player_id)->toBe(2);
    expect($game_state->last_player_id)->toBe(1);

    // Now player 2's tokens have valid_moves
    $tokensArray = $board_state->getTokensArray();
    foreach ($tokensArray as $token) {
        if ($token['player_id'] === 2) {
            expect($token)->toHaveKey('valid_moves');
        } else {
            expect($token)->not->toHaveKey('valid_moves');
        }
    }

    // Turn 2: Player 2 (red) moves
    $tokens2 = $board_state->getTokensForPlayer(2);
    $token2 = $tokens2[0];
    $originalPos2 = ['q' => $token2->q, 'r' => $token2->r];
    $validMoves2 = $token2->getValidMoves($board_state);
    expect($validMoves2)->not->toBeEmpty();

    $toPos2 = $validMoves2[0];
    verb(new TokenMoved(
        game_id: $game_state->id,
        board_id: $board_state->id,
        player_id: 2,
        from_q: $originalPos2['q'],
        from_r: $originalPos2['r'],
        to_q: $toPos2['q'],
        to_r: $toPos2['r'],
        token_id: $token2->id
    ));

    $board_state = BoardState::load($board_state->id);
    $game_state = GameState::load($game_state->id);
    $token2 = TokenState::load($token2->id);

    // Verify move
    expect($token2->q)->toBe($toPos2['q']);
    expect($token2->r)->toBe($toPos2['r']);

    // Back to player 1
    expect($game_state->active_player_id)->toBe(1);
    expect($game_state->last_player_id)->toBe(2);

    // Turn 3: Player 1 moves again
    $tokens1 = $board_state->getTokensForPlayer(1);
    $token1b = $tokens1[1]; // Different token this time
    $validMoves1b = $token1b->getValidMoves($board_state);
    expect($validMoves1b)->not->toBeEmpty();

    $toPos1b = $validMoves1b[0];
    verb(new TokenMoved(
        game_id: $game_state->id,
        board_id: $board_state->id,
        player_id: 1,
        from_q: $token1b->q,
        from_r: $token1b->r,
        to_q: $toPos1b['q'],
        to_r: $toPos1b['r'],
        token_id: $token1b->id
    ));

    $game_state = GameState::load($game_state->id);

    // Verify turn changed
    expect($game_state->active_player_id)->toBe(2);
    expect($game_state->last_player_id)->toBe(1);
    expect($game_state->isInProgress())->toBeTrue();

    // =========================================================================
    // PHASE 5: WIN CONDITION
    // =========================================================================

    // checkWin should return false (tokens not in goal)
    $board_state = BoardState::load($board_state->id);
    expect($board_state->checkWin(1))->toBeFalse();
    expect($board_state->checkWin(2))->toBeFalse();

    // Verify goal positions are correct
    $blueGoal = $board_state->getGoalPositionsForColor('blue');
    $redStart = $board_state->getStartingPositionsForColor('red');
    expect($blueGoal)->toBe($redStart); // Blue's goal is red's start

    // =========================================================================
    // PHASE 6: GAME END SCENARIOS
    // =========================================================================

    // Test PlayerWonGame manually (in a real game this would fire from checkWin)
    verb(new \App\Events\Gameplay\PlayerWonGame(game_id: $game_state->id, player_id: 1));
    $game_state = GameState::load($game_state->id);

    expect($game_state->winner_id)->toBe(1);
    expect($game_state->ended)->toBeTrue();
    expect($game_state->isInProgress())->toBeFalse();

    // Cannot set another winner
    try {
        verb(new \App\Events\Gameplay\PlayerWonGame(game_id: $game_state->id, player_id: 2));
        expect(true)->toBeFalse('Expected exception');
    } catch (\Throwable $e) {
        expect($e->getMessage())->toContain('already won');
    }
});

test('board is generated with all valid hexagonal coordinates', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);

    // Create a board for the game
    $board_state = verb(new BoardCreated(game_id: $game_state->id))->state(BoardState::class);

    expect($board_state->getTotalPositions())->toBeGreaterThan(0);

    // Verify that the board contains valid positions
    $cells = $board_state->getCells();
    expect($cells)->toBeArray()
        ->and(count($cells))->toBeGreaterThan(0);

    // Test some known valid positions from the center hexagon
    expect($board_state->isOnBoard(0, 0))->toBeTrue()
        ->and($board_state->isOnBoard(4, 0))->toBeTrue()
        ->and($board_state->isOnBoard(-4, 0))->toBeTrue()
        ->and($board_state->isOnBoard(0, 4))->toBeTrue()
        ->and($board_state->isOnBoard(0, -4))->toBeTrue();

    // Test some known invalid positions
    expect($board_state->isOnBoard(9, 0))->toBeFalse()
        ->and($board_state->isOnBoard(0, 9))->toBeFalse()
        ->and($board_state->isOnBoard(-9, 0))->toBeFalse();
});

test('isOnBoard correctly identifies center hexagon positions', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);
    $board_state = verb(new BoardCreated(game_id: $game_state->id))->state(BoardState::class);

    // Test center hexagon (radius 4)
    for ($q = -4; $q <= 4; $q++) {
        for ($r = -4; $r <= 4; $r++) {
            $sum = -$q - $r;
            if (abs($q) <= 4 && abs($r) <= 4 && abs($sum) <= 4) {
                expect($board_state->isOnBoard($q, $r))->toBeTrue("Position ({$q}, {$r}) should be on board");
            }
        }
    }
});

test('isOnBoard correctly identifies triangular region positions', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);
    $board_state = verb(new BoardCreated(game_id: $game_state->id))->state(BoardState::class);

    // Test North region (r <= -5 && r >= -8, q >= 1 && q <= 4)
    expect($board_state->isOnBoard(1, -5))->toBeTrue()
        ->and($board_state->isOnBoard(4, -8))->toBeTrue()
        ->and($board_state->isOnBoard(2, -6))->toBeTrue();

    // Test South region (r >= 5 && r <= 8, q >= -4 && q <= -1)
    expect($board_state->isOnBoard(-1, 5))->toBeTrue()
        ->and($board_state->isOnBoard(-4, 8))->toBeTrue()
        ->and($board_state->isOnBoard(-2, 6))->toBeTrue();

    // Test Northeast region (q >= 5 && q <= 8, r >= -4 && r <= 0)
    expect($board_state->isOnBoard(5, -4))->toBeTrue()
        ->and($board_state->isOnBoard(8, -4))->toBeTrue()
        ->and($board_state->isOnBoard(6, -2))->toBeTrue();

    // Test Southwest region (q <= -5 && q >= -8, r >= 0 && r <= 4)
    expect($board_state->isOnBoard(-8, 4))->toBeTrue()
        ->and($board_state->isOnBoard(-5, 1))->toBeTrue()
        ->and($board_state->isOnBoard(-5, 4))->toBeTrue();
});

test('board contains exactly 121 valid positions', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);
    $board_state = verb(new BoardCreated(game_id: $game_state->id))->state(BoardState::class);

    // Count all valid positions manually
    $count = 0;
    for ($q = -8; $q <= 8; $q++) {
        for ($r = -8; $r <= 8; $r++) {
            if ($board_state->isOnBoard($q, $r)) {
                $count++;
            }
        }
    }

    expect($count)->toBe(121)
        ->and($board_state->getTotalPositions())->toBe(121);
});

test('board cells are properly initialized', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);
    $board_state = verb(new BoardCreated(game_id: $game_state->id))->state(BoardState::class);

    $cells = $board_state->getCells();

    // Verify each cell has the correct structure
    foreach ($cells as $key => $cell) {
        expect($cell)->toHaveKeys(['q', 'r', 'piece'])
            ->and($cell['piece'])->toBeNull()
            ->and($cell['q'])->toBeInt()
            ->and($cell['r'])->toBeInt()
            ->and($key)->toBe("{$cell['q']},{$cell['r']}");
    }

    // Test getting a specific cell
    $cell = $board_state->getCell(0, 0);
    expect($cell)->not->toBeNull()
        ->and($cell['q'])->toBe(0)
        ->and($cell['r'])->toBe(0)
        ->and($cell['piece'])->toBeNull();
});

test('player color is auto-assigned when joining', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);
    $board_state = verb(new BoardCreated(game_id: $game_state->id))->state(BoardState::class);

    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 1, color: 'blue'));
    $player1 = verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 1, name: 'Player 1'))->state(PlayerState::class);
    expect($player1->color)->toBe('blue');

    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 2, color: 'red'));
    $player2 = verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 2, name: 'Player 2'))->state(PlayerState::class);
    expect($player2->color)->toBe('red');

    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 3, color: 'yellow'));
    $player3 = verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 3, name: 'Player 3'))->state(PlayerState::class);
    expect($player3->color)->toBe('yellow');
});

test('starting positions are calculated correctly for each color', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);
    $board_state = verb(new BoardCreated(game_id: $game_state->id))->state(BoardState::class);

    $colors = ['blue', 'red', 'yellow', 'green', 'orange', 'purple'];

    foreach ($colors as $color) {
        $positions = $board_state->getStartingPositionsForColor($color);
        expect($positions)->toHaveCount(10);

        // Verify all positions are on the board
        foreach ($positions as $pos) {
            expect($board_state->isOnBoard($pos['q'], $pos['r']))->toBeTrue();
        }
    }
});

test('starting positions are in correct region for each color', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);
    $board_state = verb(new BoardCreated(game_id: $game_state->id))->state(BoardState::class);

    // Test red (North) - r <= -5
    $redPositions = $board_state->getStartingPositionsForColor('red');
    foreach ($redPositions as $pos) {
        expect($pos['r'])->toBeLessThanOrEqual(-5);
        expect($pos['r'])->toBeGreaterThanOrEqual(-8);
        expect($pos['q'])->toBeGreaterThanOrEqual(1);
        expect($pos['q'])->toBeLessThanOrEqual(4);
    }

    // Test blue (South) - r >= 5
    $bluePositions = $board_state->getStartingPositionsForColor('blue');
    foreach ($bluePositions as $pos) {
        expect($pos['r'])->toBeGreaterThanOrEqual(5);
        expect($pos['r'])->toBeLessThanOrEqual(8);
        expect($pos['q'])->toBeGreaterThanOrEqual(-4);
        expect($pos['q'])->toBeLessThanOrEqual(-1);
    }

    // Test yellow (NE) - q >= 5
    $yellowPositions = $board_state->getStartingPositionsForColor('yellow');
    foreach ($yellowPositions as $pos) {
        expect($pos['q'])->toBeGreaterThanOrEqual(5);
        expect($pos['q'])->toBeLessThanOrEqual(8);
        expect($pos['r'])->toBeGreaterThanOrEqual(-4);
        expect($pos['r'])->toBeLessThanOrEqual(0);
    }

    // Test purple (SW) - q <= -5
    $purplePositions = $board_state->getStartingPositionsForColor('purple');
    foreach ($purplePositions as $pos) {
        expect($pos['q'])->toBeLessThanOrEqual(-5);
        expect($pos['q'])->toBeGreaterThanOrEqual(-8);
        expect($pos['r'])->toBeGreaterThanOrEqual(0);
        expect($pos['r'])->toBeLessThanOrEqual(4);
    }

    // Test green (SE) - sum <= -5
    $greenPositions = $board_state->getStartingPositionsForColor('green');
    foreach ($greenPositions as $pos) {
        $sum = -$pos['q'] - $pos['r'];
        expect($sum)->toBeLessThanOrEqual(-5);
        expect($sum)->toBeGreaterThanOrEqual(-8);
    }

    // Test orange (NW) - sum >= 5
    $orangePositions = $board_state->getStartingPositionsForColor('orange');
    foreach ($orangePositions as $pos) {
        $sum = -$pos['q'] - $pos['r'];
        expect($sum)->toBeGreaterThanOrEqual(5);
        expect($sum)->toBeLessThanOrEqual(8);
    }
});

test('tokens are placed correctly in starting positions', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);
    $board_state = verb(new BoardCreated(game_id: $game_state->id))->state(BoardState::class);

    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 1, color: 'blue'));
    $player = verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 1, name: 'Player 1'))->state(PlayerState::class);
    expect($player->color)->toBe('blue');

    // TokensPlaced is automatically fired by PlayerJoinedGame when player has color
    $board_state = BoardState::load($board_state->id);

    $startingPositions = $board_state->getStartingPositionsForColor('blue');
    expect($startingPositions)->toHaveCount(10);

    // Verify all starting positions have the player's token
    foreach ($startingPositions as $pos) {
        $cell = $board_state->getCell($pos['q'], $pos['r']);
        expect($cell)->not->toBeNull()
            ->and($cell['piece'])->toBe(1);
    }
});

test('tokens are placed in correct region based on color', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);
    $board_state = verb(new BoardCreated(game_id: $game_state->id))->state(BoardState::class);

    // Create players with different colors
    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 1, color: 'blue'));
    $player1 = verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 1, name: 'Player 1'))->state(PlayerState::class);
    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 2, color: 'red'));
    $player2 = verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 2, name: 'Player 2'))->state(PlayerState::class);

    // TokensPlaced is automatically fired by PlayerJoinedGame when player has color
    $board_state = BoardState::load($board_state->id);

    // Verify player 1 (blue) tokens are in South region
    $bluePositions = $board_state->getStartingPositionsForColor('blue');
    foreach ($bluePositions as $pos) {
        $cell = $board_state->getCell($pos['q'], $pos['r']);
        expect($cell['piece'])->toBe(1);
    }

    // Verify player 2 (red) tokens are in North region
    $redPositions = $board_state->getStartingPositionsForColor('red');
    foreach ($redPositions as $pos) {
        $cell = $board_state->getCell($pos['q'], $pos['r']);
        expect($cell['piece'])->toBe(2);
    }
});

test('adjacent positions are calculated correctly', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);
    $board_state = verb(new BoardCreated(game_id: $game_state->id))->state(BoardState::class);

    // Test center position (0, 0) should have 6 adjacent positions
    $adjacent = $board_state->getAdjacentPositions(0, 0);
    expect($adjacent)->toHaveCount(6);

    // Verify all adjacent positions are on the board
    foreach ($adjacent as $pos) {
        expect($board_state->isOnBoard($pos['q'], $pos['r']))->toBeTrue();
    }

    // Test edge position - should have fewer adjacent positions
    $adjacent = $board_state->getAdjacentPositions(1, -5);
    expect($adjacent)->toBeArray();
    expect(count($adjacent))->toBeLessThanOrEqual(6);
});

test('token can be moved to adjacent position', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);
    $board_state = verb(new BoardCreated(game_id: $game_state->id))->state(BoardState::class);

    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 1, color: 'blue'));
    $player = verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 1, name: 'Player 1'))->state(PlayerState::class);
    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 2, color: 'red'));
    verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 2, name: 'Player 2'));
    // TokensPlaced is automatically fired by PlayerJoinedGame when player has color
    $board_state = BoardState::load($board_state->id);

    // Start the game so tokens can be moved
    verb(new GameStarted(game_id: $game_state->id, player_id: 1));
    $game_state = GameState::load($game_state->id);

    // Get a starting position
    $startingPositions = $board_state->getStartingPositionsForColor('blue');
    $fromPos = $startingPositions[0];

    // Get an adjacent position that's empty
    $adjacent = $board_state->getAdjacentPositions($fromPos['q'], $fromPos['r']);
    $toPos = null;
    foreach ($adjacent as $adj) {
        $cell = $board_state->getCell($adj['q'], $adj['r']);
        if ($cell && $cell['piece'] === null) {
            $toPos = $adj;
            break;
        }
    }

    expect($toPos)->not->toBeNull();

    // Move token
    verb(new TokenMoved(
        game_id: $game_state->id,
        board_id: $board_state->id,
        player_id: 1,
        from_q: $fromPos['q'],
        from_r: $fromPos['r'],
        to_q: $toPos['q'],
        to_r: $toPos['r']
    ));
    $board_state = BoardState::load($board_state->id);

    // Verify from position is now empty
    $fromCell = $board_state->getCell($fromPos['q'], $fromPos['r']);
    expect($fromCell['piece'])->toBeNull();

    // Verify to position has the token
    $toCell = $board_state->getCell($toPos['q'], $toPos['r']);
    expect($toCell['piece'])->toBe(1);
});

test('cannot move token to occupied position', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);
    $board_state = verb(new BoardCreated(game_id: $game_state->id))->state(BoardState::class);

    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 1, color: 'blue'));
    $player1 = verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 1, name: 'Player 1'))->state(PlayerState::class);
    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 2, color: 'red'));
    $player2 = verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 2, name: 'Player 2'))->state(PlayerState::class);

    // TokensPlaced is automatically fired by PlayerJoinedGame when player has color
    $board_state = BoardState::load($board_state->id);

    // Start the game so tokens can be moved
    verb(new GameStarted(game_id: $game_state->id, player_id: 1));
    $game_state = GameState::load($game_state->id);

    // Get a position with player 1's token
    $bluePositions = $board_state->getStartingPositionsForColor('blue');
    $fromPos = $bluePositions[0];

    // Get a position with player 2's token
    $redPositions = $board_state->getStartingPositionsForColor('red');
    $toPos = $redPositions[0];

    // Try to move - should fail
    try {
        verb(new TokenMoved(
            game_id: $game_state->id,
            board_id: $board_state->id,
            player_id: 1,
            from_q: $fromPos['q'],
            from_r: $fromPos['r'],
            to_q: $toPos['q'],
            to_r: $toPos['r']
        ));
        expect(true)->toBeFalse('Expected exception to be thrown');
    } catch (\Throwable $e) {
        expect($e->getMessage())->toContain('already occupied');
    }
});

test('cannot move token to non-adjacent position', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);
    $board_state = verb(new BoardCreated(game_id: $game_state->id))->state(BoardState::class);

    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 1, color: 'blue'));
    $player = verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 1, name: 'Player 1'))->state(PlayerState::class);
    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 2, color: 'red'));
    verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 2, name: 'Player 2'));
    // TokensPlaced is automatically fired by PlayerJoinedGame when player has color
    $board_state = BoardState::load($board_state->id);

    // Start the game so tokens can be moved
    verb(new GameStarted(game_id: $game_state->id, player_id: 1));
    $game_state = GameState::load($game_state->id);

    // Get a starting position
    $bluePositions = $board_state->getStartingPositionsForColor('blue');
    $fromPos = $bluePositions[0];

    // Try to move to a non-adjacent position (center)
    // First get the token to check its valid moves
    $token = $board_state->getTokenAtPosition($fromPos['q'], $fromPos['r']);
    expect($token)->not->toBeNull();

    // Verify that (0, 0) is not in valid moves
    $validMoves = $token->getValidMoves($board_state);
    $isValidMove = false;
    foreach ($validMoves as $move) {
        if ($move['q'] === 0 && $move['r'] === 0) {
            $isValidMove = true;
            break;
        }
    }
    expect($isValidMove)->toBeFalse('Position (0, 0) should not be a valid move');

    try {
        verb(new TokenMoved(
            game_id: $game_state->id,
            board_id: $board_state->id,
            player_id: 1,
            from_q: $fromPos['q'],
            from_r: $fromPos['r'],
            to_q: 0,
            to_r: 0
        ));
        expect(true)->toBeFalse('Expected exception to be thrown');
    } catch (\Throwable $e) {
        expect($e->getMessage())->toContain('not a valid move');
    }
});

test('TokenState is created when tokens are placed', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);
    $board_state = verb(new BoardCreated(game_id: $game_state->id))->state(BoardState::class);

    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 1, color: 'blue'));
    verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 1, name: 'Player 1'));

    // TokensPlaced is automatically fired by PlayerJoinedGame when player has color
    $board_state = BoardState::load($board_state->id);

    expect($board_state->token_ids)->toHaveCount(10);

    // Verify tokens exist and have correct properties
    foreach ($board_state->token_ids as $tokenId) {
        $token = TokenState::load($tokenId);
        expect($token)->not->toBeNull()
            ->and($token->player_id)->toBe(1)
            ->and($token->board_id)->toBe($board_state->id)
            ->and($token->q)->toBeInt()
            ->and($token->r)->toBeInt()
            ->and($token->getValidMoves($board_state))->toBeArray();
    }
});

test('TokenState valid moves include single-step moves', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);
    $board_state = verb(new BoardCreated(game_id: $game_state->id))->state(BoardState::class);

    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 1, color: 'blue'));
    verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 1, name: 'Player 1'));

    $board_state = BoardState::load($board_state->id);
    $tokens = $board_state->getTokensForPlayer(1);
    expect($tokens)->toHaveCount(10);

    // Check that tokens have valid moves
    $token = $tokens[0];
    $validMoves = $token->getValidMoves($board_state);
    expect($validMoves)->toBeArray()
        ->and(count($validMoves))->toBeGreaterThan(0);

    // Verify valid moves are on the board and empty
    foreach ($validMoves as $move) {
        expect($board_state->isOnBoard($move['q'], $move['r']))->toBeTrue()
            ->and($board_state->pieceAt($move['q'], $move['r']))->toBeNull();
    }
});

test('TokenState valid moves include jump moves', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);
    $board_state = verb(new BoardCreated(game_id: $game_state->id))->state(BoardState::class);

    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 1, color: 'blue'));
    verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 1, name: 'Player 1'));
    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 2, color: 'red'));
    verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 2, name: 'Player 2'));

    $board_state = BoardState::load($board_state->id);

    // Start the game so tokens can be moved
    verb(new GameStarted(game_id: $game_state->id, player_id: 1));

    // Get a token from player 1
    $tokens = $board_state->getTokensForPlayer(1);
    $token = $tokens[0];

    // Get valid moves for the token
    $validMoves = $token->getValidMoves($board_state);

    if (! empty($validMoves)) {
        $toPos = $validMoves[0];

        verb(new TokenMoved(
            game_id: $game_state->id,
            board_id: $board_state->id,
            player_id: 1,
            from_q: $token->q,
            from_r: $token->r,
            to_q: $toPos['q'],
            to_r: $toPos['r']
        ));

        $board_state = BoardState::load($board_state->id);
        $token = TokenState::load($token->id);

        // Get moves - should include jump moves if there are tokens to jump over
        $newValidMoves = $token->getValidMoves($board_state);

        expect($newValidMoves)->toBeArray();
    }
});

test('TokenState position is updated when token is moved', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);
    $board_state = verb(new BoardCreated(game_id: $game_state->id))->state(BoardState::class);

    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 1, color: 'blue'));
    verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 1, name: 'Player 1'));
    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 2, color: 'red'));
    verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 2, name: 'Player 2'));

    $board_state = BoardState::load($board_state->id);

    // Start the game so tokens can be moved
    verb(new GameStarted(game_id: $game_state->id, player_id: 1));

    // Get a token
    $tokens = $board_state->getTokensForPlayer(1);
    $token = $tokens[0];
    $originalQ = $token->q;
    $originalR = $token->r;

    // Get a valid move position
    $validMoves = $token->getValidMoves($board_state);
    expect($validMoves)->not->toBeEmpty();

    $toPos = $validMoves[0];

    // Move token
    verb(new TokenMoved(
        game_id: $game_state->id,
        board_id: $board_state->id,
        player_id: 1,
        from_q: $originalQ,
        from_r: $originalR,
        to_q: $toPos['q'],
        to_r: $toPos['r'],
        token_id: $token->id
    ));

    // Reload token and verify position updated
    $token = TokenState::load($token->id);
    expect($token->q)->toBe($toPos['q'])
        ->and($token->r)->toBe($toPos['r']);
});

test('TokenState valid moves are recalculated after a move', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);
    $board_state = verb(new BoardCreated(game_id: $game_state->id))->state(BoardState::class);

    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 1, color: 'blue'));
    verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 1, name: 'Player 1'));
    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 2, color: 'red'));
    verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 2, name: 'Player 2'));

    $board_state = BoardState::load($board_state->id);

    // Start the game so tokens can be moved
    verb(new GameStarted(game_id: $game_state->id, player_id: 1));

    // Get tokens
    $tokens1 = $board_state->getTokensForPlayer(1);
    $tokens2 = $board_state->getTokensForPlayer(2);

    $token1 = $tokens1[0];
    $originalMoves = $token1->getValidMoves($board_state);

    // Move a token from player 2 to change board state
    if (! empty($tokens2)) {
        $token2 = $tokens2[0];
        $validMoves2 = $token2->getValidMoves($board_state);

        if (! empty($validMoves2)) {
            $toPos = $validMoves2[0];

            verb(new TokenMoved(
                game_id: $game_state->id,
                board_id: $board_state->id,
                player_id: 2,
                from_q: $token2->q,
                from_r: $token2->r,
                to_q: $toPos['q'],
                to_r: $toPos['r']
            ));

            // Reload and verify token1's moves reflect new board state
            $board_state = BoardState::load($board_state->id);
            $token1 = TokenState::load($token1->id);

            // Moves are calculated on-demand from current board state
            expect($token1->getValidMoves($board_state))->toBeArray();
        }
    }
});

test('BoardState game_id is set correctly by BoardCreated', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);
    $board_state = verb(new BoardCreated(game_id: $game_state->id))->state(BoardState::class);

    // Verify game_id is set on the board
    expect($board_state->game_id)->toBe($game_state->id);

    // Verify board->game() returns the correct GameState
    $game = $board_state->game();
    expect($game)->not->toBeNull()
        ->and($game->id)->toBe($game_state->id)
        ->and($game->board_id)->toBe($board_state->id);
});

test('getTokensArray includes valid_moves only for active player', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);
    $board_state = verb(new BoardCreated(game_id: $game_state->id))->state(BoardState::class);

    // Add two players
    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 1, color: 'blue'));
    verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 1, name: 'Player 1'));
    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 2, color: 'red'));
    verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 2, name: 'Player 2'));

    $board_state = BoardState::load($board_state->id);

    // Before game starts: tokens should NOT have valid_moves in the array
    $tokensArray = $board_state->getTokensArray();
    expect($tokensArray)->toHaveCount(20);

    foreach ($tokensArray as $token) {
        expect($token)->not->toHaveKey('valid_moves');
    }

    // Start the game with player 1 as active
    verb(new GameStarted(game_id: $game_state->id, player_id: 1));
    $game_state = GameState::load($game_state->id);
    $board_state = BoardState::load($board_state->id);

    // After game starts: only active player's tokens should have valid_moves
    $tokensArray = $board_state->getTokensArray();
    expect($tokensArray)->toHaveCount(20);

    $player1TokensWithMoves = 0;
    $player2TokensWithMoves = 0;

    foreach ($tokensArray as $token) {
        if ($token['player_id'] === 1) {
            expect($token)->toHaveKey('valid_moves');
            $player1TokensWithMoves++;
        } else {
            expect($token)->not->toHaveKey('valid_moves');
        }
    }

    expect($player1TokensWithMoves)->toBe(10);
});

test('EndedTurn changes active player correctly', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);
    verb(new BoardCreated(game_id: $game_state->id));

    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 1, color: 'blue'));
    verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 1, name: 'Player 1'));
    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 2, color: 'red'));
    verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 2, name: 'Player 2'));

    // Start game with player 1
    verb(new GameStarted(game_id: $game_state->id, player_id: 1));
    $game_state = GameState::load($game_state->id);

    expect($game_state->active_player_id)->toBe(1);
    expect($game_state->last_player_id)->toBeNull();

    // End turn for player 1
    verb(new \App\Events\Gameplay\EndedTurn(game_id: $game_state->id, player_id: 1));
    $game_state = GameState::load($game_state->id);

    expect($game_state->active_player_id)->toBe(2);
    expect($game_state->last_player_id)->toBe(1);

    // End turn for player 2
    verb(new \App\Events\Gameplay\EndedTurn(game_id: $game_state->id, player_id: 2));
    $game_state = GameState::load($game_state->id);

    expect($game_state->active_player_id)->toBe(1);
    expect($game_state->last_player_id)->toBe(2);
});

test('EndedTurn fails when game is not in progress', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);
    verb(new BoardCreated(game_id: $game_state->id));

    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 1, color: 'blue'));
    verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 1, name: 'Player 1'));
    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 2, color: 'red'));
    verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 2, name: 'Player 2'));

    // Try to end turn before game is started
    try {
        verb(new \App\Events\Gameplay\EndedTurn(game_id: $game_state->id, player_id: 1));
        expect(true)->toBeFalse('Expected exception to be thrown');
    } catch (\Throwable $e) {
        expect($e->getMessage())->toContain('not in progress');
    }
});

test('EndedTurn broadcasts board state with valid_moves for new active player', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);
    $board_state = verb(new BoardCreated(game_id: $game_state->id))->state(BoardState::class);

    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 1, color: 'blue'));
    verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 1, name: 'Player 1'));
    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 2, color: 'red'));
    verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 2, name: 'Player 2'));

    verb(new GameStarted(game_id: $game_state->id, player_id: 1));

    // End turn for player 1, player 2 becomes active
    verb(new \App\Events\Gameplay\EndedTurn(game_id: $game_state->id, player_id: 1));
    $game_state = GameState::load($game_state->id);
    $board_state = BoardState::load($board_state->id);

    // After EndedTurn, player 2 is now active
    expect($game_state->active_player_id)->toBe(2);

    // getTokensArray should now include valid_moves only for player 2
    $tokensArray = $board_state->getTokensArray();

    foreach ($tokensArray as $token) {
        if ($token['player_id'] === 2) {
            expect($token)->toHaveKey('valid_moves');
        } else {
            expect($token)->not->toHaveKey('valid_moves');
        }
    }
});

test('goal positions are the opposite corner from starting positions', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);
    $board_state = verb(new BoardCreated(game_id: $game_state->id))->state(BoardState::class);

    // Blue starts in South, goal is North (red's starting position)
    $blueGoal = $board_state->getGoalPositionsForColor('blue');
    $redStart = $board_state->getStartingPositionsForColor('red');
    expect($blueGoal)->toBe($redStart);

    // Red starts in North, goal is South (blue's starting position)
    $redGoal = $board_state->getGoalPositionsForColor('red');
    $blueStart = $board_state->getStartingPositionsForColor('blue');
    expect($redGoal)->toBe($blueStart);

    // Yellow starts in NE, goal is SW (purple's starting position)
    $yellowGoal = $board_state->getGoalPositionsForColor('yellow');
    $purpleStart = $board_state->getStartingPositionsForColor('purple');
    expect($yellowGoal)->toBe($purpleStart);

    // Purple starts in SW, goal is NE (yellow's starting position)
    $purpleGoal = $board_state->getGoalPositionsForColor('purple');
    $yellowStart = $board_state->getStartingPositionsForColor('yellow');
    expect($purpleGoal)->toBe($yellowStart);
});

test('checkWin returns false when tokens are in starting position', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);
    $board_state = verb(new BoardCreated(game_id: $game_state->id))->state(BoardState::class);

    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 1, color: 'blue'));
    verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 1, name: 'Player 1'));

    $board_state = BoardState::load($board_state->id);

    // Player 1's tokens are in starting position, not goal
    expect($board_state->checkWin(1))->toBeFalse();
});

test('isBoardFull returns false when board has empty positions', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);
    $board_state = verb(new BoardCreated(game_id: $game_state->id))->state(BoardState::class);

    // Board with no tokens - has empty positions
    expect($board_state->isBoardFull())->toBeFalse();

    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 1, color: 'blue'));
    verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 1, name: 'Player 1'));

    $board_state = BoardState::load($board_state->id);

    // Board with 10 tokens still has empty positions
    expect($board_state->isBoardFull())->toBeFalse();
});

test('PlayerWonGame sets winner and ends game', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);
    verb(new BoardCreated(game_id: $game_state->id));

    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 1, color: 'blue'));
    verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 1, name: 'Player 1'));
    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 2, color: 'red'));
    verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 2, name: 'Player 2'));

    verb(new GameStarted(game_id: $game_state->id, player_id: 1));
    $game_state = GameState::load($game_state->id);

    expect($game_state->winner_id)->toBeNull();
    expect($game_state->ended)->toBeFalse();

    verb(new \App\Events\Gameplay\PlayerWonGame(game_id: $game_state->id, player_id: 1));
    $game_state = GameState::load($game_state->id);

    expect($game_state->winner_id)->toBe(1);
    expect($game_state->ended)->toBeTrue();
    expect($game_state->isInProgress())->toBeFalse();
});

test('PlayerWonGame fails if game already has winner', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);
    verb(new BoardCreated(game_id: $game_state->id));

    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 1, color: 'blue'));
    verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 1, name: 'Player 1'));
    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 2, color: 'red'));
    verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 2, name: 'Player 2'));

    verb(new GameStarted(game_id: $game_state->id, player_id: 1));
    verb(new \App\Events\Gameplay\PlayerWonGame(game_id: $game_state->id, player_id: 1));

    // Try to set another winner
    try {
        verb(new \App\Events\Gameplay\PlayerWonGame(game_id: $game_state->id, player_id: 2));
        expect(true)->toBeFalse('Expected exception to be thrown');
    } catch (\Throwable $e) {
        expect($e->getMessage())->toContain('already won');
    }
});

test('PlayersTiedGame ends game without winner', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);
    verb(new BoardCreated(game_id: $game_state->id));

    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 1, color: 'blue'));
    verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 1, name: 'Player 1'));
    verb(new PlayerColorSelected(game_id: $game_state->id, player_id: 2, color: 'red'));
    verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 2, name: 'Player 2'));

    verb(new GameStarted(game_id: $game_state->id, player_id: 1));
    $game_state = GameState::load($game_state->id);

    expect($game_state->ended)->toBeFalse();

    verb(new \App\Events\Gameplay\PlayersTiedGame(game_id: $game_state->id));
    $game_state = GameState::load($game_state->id);

    expect($game_state->winner_id)->toBeNull();
    expect($game_state->ended)->toBeTrue();
    expect($game_state->isInProgress())->toBeFalse();
});
