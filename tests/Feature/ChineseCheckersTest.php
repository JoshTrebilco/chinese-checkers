<?php

use App\Events\Gameplay\TokenMoved;
use App\Events\Setup\BoardCreated;
use App\Events\Setup\GameCreated;
use App\Events\Setup\PlayerJoinedGame;
use App\Events\Setup\TokensPlaced;
use App\States\BoardState;
use App\States\GameState;
use App\States\PlayerState;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    Verbs::fake();
    Verbs::commitImmediately();
});

test('board is generated with all valid hexagonal coordinates', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);

    // Create a board for the game
    $board_state = verb(new BoardCreated(game_id: $game_state->id))->state(BoardState::class);

    expect($board_state->game_id)->toBe($game_state->id)
        ->and($board_state->getTotalPositions())->toBeGreaterThan(0);

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

    $player1 = verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 1, name: 'Player 1'))->state(PlayerState::class);
    expect($player1->color)->toBe('blue');

    $player2 = verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 2, name: 'Player 2'))->state(PlayerState::class);
    expect($player2->color)->toBe('red');

    $player3 = verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 3, name: 'Player 3'))->state(PlayerState::class);
    expect($player3->color)->toBe('yellow');
});

test('starting positions are calculated correctly for each color', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);
    $board_state = verb(new BoardCreated(game_id: $game_state->id))->state(BoardState::class);

    $colors = ['blue', 'red', 'yellow', 'green', 'teal', 'purple'];

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

    // Test blue (North) - r <= -5
    $bluePositions = $board_state->getStartingPositionsForColor('blue');
    foreach ($bluePositions as $pos) {
        expect($pos['r'])->toBeLessThanOrEqual(-5);
        expect($pos['r'])->toBeGreaterThanOrEqual(-8);
        expect($pos['q'])->toBeGreaterThanOrEqual(1);
        expect($pos['q'])->toBeLessThanOrEqual(4);
    }

    // Test red (South) - r >= 5
    $redPositions = $board_state->getStartingPositionsForColor('red');
    foreach ($redPositions as $pos) {
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

    // Test green (SW) - q <= -5
    $greenPositions = $board_state->getStartingPositionsForColor('green');
    foreach ($greenPositions as $pos) {
        expect($pos['q'])->toBeLessThanOrEqual(-5);
        expect($pos['q'])->toBeGreaterThanOrEqual(-8);
        expect($pos['r'])->toBeGreaterThanOrEqual(0);
        expect($pos['r'])->toBeLessThanOrEqual(4);
    }

    // Test teal (SE) - sum <= -5
    $tealPositions = $board_state->getStartingPositionsForColor('teal');
    foreach ($tealPositions as $pos) {
        $sum = -$pos['q'] - $pos['r'];
        expect($sum)->toBeLessThanOrEqual(-5);
        expect($sum)->toBeGreaterThanOrEqual(-8);
    }

    // Test purple (NW) - sum >= 5
    $purplePositions = $board_state->getStartingPositionsForColor('purple');
    foreach ($purplePositions as $pos) {
        $sum = -$pos['q'] - $pos['r'];
        expect($sum)->toBeGreaterThanOrEqual(5);
        expect($sum)->toBeLessThanOrEqual(8);
    }
});

test('tokens are placed correctly in starting positions', function () {
    $game_state = verb(new GameCreated)->state(GameState::class);
    $board_state = verb(new BoardCreated(game_id: $game_state->id))->state(BoardState::class);

    $player = verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 1, name: 'Player 1'))->state(PlayerState::class);
    expect($player->color)->toBe('blue');

    verb(new TokensPlaced(board_id: $board_state->id, player_id: 1));
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
    $player1 = verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 1, name: 'Player 1'))->state(PlayerState::class);
    $player2 = verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 2, name: 'Player 2'))->state(PlayerState::class);

    // Place tokens
    verb(new TokensPlaced(board_id: $board_state->id, player_id: 1));
    verb(new TokensPlaced(board_id: $board_state->id, player_id: 2));
    $board_state = BoardState::load($board_state->id);

    // Verify player 1 (blue) tokens are in North region
    $bluePositions = $board_state->getStartingPositionsForColor('blue');
    foreach ($bluePositions as $pos) {
        $cell = $board_state->getCell($pos['q'], $pos['r']);
        expect($cell['piece'])->toBe(1);
    }

    // Verify player 2 (red) tokens are in South region
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

    $player = verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 1, name: 'Player 1'))->state(PlayerState::class);
    verb(new TokensPlaced(board_id: $board_state->id, player_id: 1));
    $board_state = BoardState::load($board_state->id);

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

    $player1 = verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 1, name: 'Player 1'))->state(PlayerState::class);
    $player2 = verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 2, name: 'Player 2'))->state(PlayerState::class);

    verb(new TokensPlaced(board_id: $board_state->id, player_id: 1));
    verb(new TokensPlaced(board_id: $board_state->id, player_id: 2));
    $board_state = BoardState::load($board_state->id);

    // Get a position with player 1's token
    $bluePositions = $board_state->getStartingPositionsForColor('blue');
    $fromPos = $bluePositions[0];

    // Get a position with player 2's token
    $redPositions = $board_state->getStartingPositionsForColor('red');
    $toPos = $redPositions[0];

    // Try to move - should fail
    try {
        verb(new TokenMoved(
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

    $player = verb(new PlayerJoinedGame(game_id: $game_state->id, player_id: 1, name: 'Player 1'))->state(PlayerState::class);
    verb(new TokensPlaced(board_id: $board_state->id, player_id: 1));
    $board_state = BoardState::load($board_state->id);

    // Get a starting position
    $bluePositions = $board_state->getStartingPositionsForColor('blue');
    $fromPos = $bluePositions[0];

    // Try to move to a non-adjacent position (center)
    try {
        verb(new TokenMoved(
            board_id: $board_state->id,
            player_id: 1,
            from_q: $fromPos['q'],
            from_r: $fromPos['r'],
            to_q: 0,
            to_r: 0
        ));
        expect(true)->toBeFalse('Expected exception to be thrown');
    } catch (\Throwable $e) {
        expect($e->getMessage())->toContain('not adjacent');
    }
});
