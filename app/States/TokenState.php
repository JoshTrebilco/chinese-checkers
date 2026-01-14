<?php

namespace App\States;

use Thunk\Verbs\State;

class TokenState extends State
{
    public int $board_id;

    public int $player_id;

    public int $q;

    public int $r;

    public int $from_q;

    public int $from_r;

    public array $jump_path = [];

    /**
     * Get all valid moves for this token (single-step + multi-jump)
     * Calculated on-demand, not stored in state.
     */
    public function getValidMoves(BoardState $board): array
    {
        $validMoves = [];

        // Single-step moves: check all 6 adjacent positions for empty spaces
        // These have an empty path (direct movement, no jumps)
        $adjacentPositions = $board->getAdjacentPositions($this->q, $this->r);
        foreach ($adjacentPositions as $pos) {
            if ($board->pieceAt($pos['q'], $pos['r']) === null) {
                $validMoves[] = [
                    'q' => $pos['q'],
                    'r' => $pos['r'],
                    'path' => [],
                ];
            }
        }

        // Multi-jump moves: find all possible jump sequences (includes paths)
        $jumpMoves = $this->calculateJumpMoves($board, $this->q, $this->r);
        $validMoves = array_merge($validMoves, $jumpMoves);

        // Remove duplicates and return
        return $this->uniquePositions($validMoves);
    }

    /**
     * Recursively calculate all possible jump moves from a starting position
     *
     * @param  array  $visited  Visited positions to avoid infinite loops
     * @param  array  $currentPath  The path taken to reach this position
     * @return array Array of ['q' => int, 'r' => int, 'path' => array] positions with paths
     */
    private function calculateJumpMoves(BoardState $board, int $startQ, int $startR, array $visited = [], array $currentPath = []): array
    {
        $validEndPositions = [];
        $key = "{$startQ},{$startR}";

        // Avoid revisiting same position
        if (isset($visited[$key])) {
            return [];
        }
        $visited[$key] = true;

        // Hexagonal grid has 6 directions
        $directions = [
            ['q' => 1, 'r' => 0],   // East
            ['q' => 1, 'r' => -1],  // Northeast
            ['q' => 0, 'r' => -1],  // Northwest
            ['q' => -1, 'r' => 0],  // West
            ['q' => -1, 'r' => 1],  // Southwest
            ['q' => 0, 'r' => 1],   // Southeast
        ];

        // Check all 6 directions
        foreach ($directions as $dir) {
            $jumpOverQ = $startQ + $dir['q'];
            $jumpOverR = $startR + $dir['r'];
            $landQ = $jumpOverQ + $dir['q'];
            $landR = $jumpOverR + $dir['r'];

            // Check if we can jump (has token to jump over, empty landing spot, on board)
            if ($board->pieceAt($jumpOverQ, $jumpOverR) !== null &&
                $board->pieceAt($landQ, $landR) === null &&
                $board->isOnBoard($landQ, $landR)) {

                // Build the path to this landing position
                $pathToLanding = array_merge($currentPath, [['q' => $landQ, 'r' => $landR]]);

                $validEndPositions[] = [
                    'q' => $landQ,
                    'r' => $landR,
                    'path' => $pathToLanding,
                ];

                // Recursively find more jumps from landing position
                $moreJumps = $this->calculateJumpMoves($board, $landQ, $landR, $visited, $pathToLanding);
                $validEndPositions = array_merge($validEndPositions, $moreJumps);
            }
        }

        return $validEndPositions;
    }

    /**
     * Remove duplicate positions from array (keeps the first path found for each position)
     */
    private function uniquePositions(array $positions): array
    {
        $seen = [];
        $unique = [];

        foreach ($positions as $pos) {
            $key = "{$pos['q']},{$pos['r']}";
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = [
                    'q' => $pos['q'],
                    'r' => $pos['r'],
                    'path' => $pos['path'] ?? [],
                ];
            }
        }

        return $unique;
    }
}
