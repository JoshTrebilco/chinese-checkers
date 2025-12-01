<?php

use App\Events\Setup\BoardCreated;
use App\Events\Setup\GameCreated;
use App\States\BoardState;
use App\States\GameState;
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
