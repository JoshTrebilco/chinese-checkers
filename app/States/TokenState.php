<?php

namespace App\States;

use Thunk\Verbs\State;

class TokenState extends State
{
    public int $board_id;
    
    public int $player_id;
    
    public int $q;
    
    public int $r;
    
    public array $valid_moves = [];

    /**
     * Calculate all valid moves for this token (single-step + multi-jump)
     *
     * @param BoardState $board
     * @return void
     */
    public function calculateValidMoves(BoardState $board): void
    {
        $validMoves = [];
        
        // Single-step moves: check all 6 adjacent positions for empty spaces
        $adjacentPositions = $board->getAdjacentPositions($this->q, $this->r);
        foreach ($adjacentPositions as $pos) {
            if ($board->pieceAt($pos['q'], $pos['r']) === null) {
                $validMoves[] = $pos;
            }
        }
        
        // Multi-jump moves: find all possible jump sequences
        $jumpMoves = $this->calculateJumpMoves($board, $this->q, $this->r);
        $validMoves = array_merge($validMoves, $jumpMoves);
        
        // Remove duplicates and store
        $this->valid_moves = $this->uniquePositions($validMoves);
    }

    /**
     * Recursively calculate all possible jump moves from a starting position
     *
     * @param BoardState $board
     * @param int $startQ
     * @param int $startR
     * @param array $visited Visited positions to avoid infinite loops
     * @return array Array of ['q' => int, 'r' => int] positions
     */
    private function calculateJumpMoves(BoardState $board, int $startQ, int $startR, array $visited = []): array
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
                
                $validEndPositions[] = ['q' => $landQ, 'r' => $landR];
                
                // Recursively find more jumps from landing position
                $moreJumps = $this->calculateJumpMoves($board, $landQ, $landR, $visited);
                $validEndPositions = array_merge($validEndPositions, $moreJumps);
            }
        }
        
        return $validEndPositions;
    }

    /**
     * Remove duplicate positions from array
     *
     * @param array $positions
     * @return array
     */
    private function uniquePositions(array $positions): array
    {
        $seen = [];
        $unique = [];
        
        foreach ($positions as $pos) {
            $key = "{$pos['q']},{$pos['r']}";
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $pos;
            }
        }
        
        return $unique;
    }
}

