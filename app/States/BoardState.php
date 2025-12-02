<?php

namespace App\States;

use Thunk\Verbs\State;

class BoardState extends State
{
    public array $cells = [];
    
    public array $token_ids = [];

    /**
     * Initialize the board with all valid hexagonal coordinates
     */
    public function setup()
    {
        $this->cells = [];

        // Generate all valid board positions
        for ($q = -8; $q <= 8; $q++) {
            for ($r = -8; $r <= 8; $r++) {
                if ($this->isOnBoard($q, $r)) {
                    $key = "{$q},{$r}";
                    $this->cells[$key] = [
                        'q' => $q,
                        'r' => $r,
                        'piece' => null, // null means no piece, otherwise player_id
                    ];
                }
            }
        }
    }

    public function pieceAt(int $q, int $r): ?int
    {
        return $this->cells["{$q},{$r}"]['piece'] ?? null;
    }

    public function pieceColorAt(int $q, int $r): ?string
    {
        return $this->pieceAt($q, $r) ? PlayerState::load($this->pieceAt($q, $r))->color : null;
    }

    /**
     * Determine if a coordinate (q, r) is part of the Chinese Checkers star board
     * Ported from JavaScript in welcome.blade.php
     *
     * @param int $q The q coordinate (column-like)
     * @param int $r The r coordinate (row-like)
     * @return bool True if the coordinate is on the board
     */
    public function isOnBoard(int $q, int $r): bool
    {
        $sum = -$q - $r;

        // --- Center hexagon (radius 4)
        if (abs($q) <= 4 && abs($r) <= 4 && abs($sum) <= 4) {
            return true;
        }

        // --- Top (North) - r is primary coordinate (negative)
        if ($r <= -5 && $r >= -8 && $q >= 1 && $q <= 4 && $sum >= 0 && $sum <= 4) {
            return true;
        }

        // --- Bottom (South) - r is primary coordinate (positive)
        if ($r >= 5 && $r <= 8 && $q >= -4 && $q <= -1 && $sum >= -4 && $sum <= 0) {
            return true;
        }

        // --- Top-right (Northeast) - q is primary coordinate (positive)
        if ($q >= 5 && $q <= 8 && $r >= -4 && $r <= 0 && $sum >= -4 && $sum <= 0) {
            return true;
        }

        // --- Bottom-left (Southwest) - q is primary coordinate (negative)
        if ($q <= -5 && $q >= -8 && $r >= 0 && $r <= 4 && $sum <= 4 && $sum >= 0) {
            return true;
        }

        // --- Top-left (Northwest) - sum is primary coordinate (negative)
        if ($sum <= -5 && $sum >= -8 && $r <= 4 && $r >= -4 && $q >= -4 && $q <= 4) {
            return true;
        }

        // --- Bottom-right (Southeast) - sum is primary coordinate (positive)
        if ($sum >= 5 && $sum <= 8 && $r <= 4 && $r >= -4 && $q >= -4 && $q <= 4) {
            return true;
        }

        return false;
    }

    /**
     * Get all valid board positions
     *
     * @return array Array of cells keyed by "q,r"
     */
    public function getCells(): array
    {
        return $this->cells;
    }

    /**
     * Get a specific cell by coordinates
     *
     * @param int $q
     * @param int $r
     * @return array|null The cell data or null if not found
     */
    public function getCell(int $q, int $r): ?array
    {
        $key = "{$q},{$r}";
        return $this->cells[$key] ?? null;
    }

    /**
     * Get the total number of valid board positions
     *
     * @return int
     */
    public function getTotalPositions(): int
    {
        return count($this->cells);
    }

    /**
     * Get the 10 starting positions for a given color
     *
     * @param string $color The player color (blue, red, yellow, green, orange, purple)
     * @return array Array of ['q' => int, 'r' => int] positions
     */
    public function getStartingPositionsForColor(string $color): array
    {
        $positions = [];
        $sum = 0;

        // Generate all positions and filter by color region
        for ($q = -8; $q <= 8; $q++) {
            for ($r = -8; $r <= 8; $r++) {
                $sum = -$q - $r;
                
                $matches = false;
                switch ($color) {
                    case 'red': // North
                        $matches = $r <= -5 && $r >= -8 && $q >= 1 && $q <= 4 && $sum >= 0 && $sum <= 4;
                        break;
                    case 'blue': // South
                        $matches = $r >= 5 && $r <= 8 && $q >= -4 && $q <= -1 && $sum >= -4 && $sum <= 0;
                        break;
                    case 'yellow': // NE
                        $matches = $q >= 5 && $q <= 8 && $r >= -4 && $r <= 0 && $sum >= -4 && $sum <= 0;
                        break;
                    case 'purple': // SW
                        $matches = $q <= -5 && $q >= -8 && $r >= 0 && $r <= 4 && $sum <= 4 && $sum >= 0;
                        break;
                    case 'green': // SE
                        $matches = $sum <= -5 && $sum >= -8 && $r <= 4 && $r >= -4 && $q >= -4 && $q <= 4;
                        break;
                    case 'orange': // NW
                        $matches = $sum >= 5 && $sum <= 8 && $r <= 4 && $r >= -4 && $q >= -4 && $q <= 4;
                        break;
                }

                if ($matches && $this->isOnBoard($q, $r)) {
                    $positions[] = ['q' => $q, 'r' => $r];
                }
            }
        }

        // Ensure we have exactly 10 positions
        return array_slice($positions, 0, 10);
    }

    /**
     * Get the 6 adjacent positions for a given hex coordinate
     *
     * @param int $q
     * @param int $r
     * @return array Array of ['q' => int, 'r' => int] positions
     */
    public function getAdjacentPositions(int $q, int $r): array
    {
        // Hexagonal grid has 6 neighbors
        $directions = [
            ['q' => 1, 'r' => 0],   // East
            ['q' => 1, 'r' => -1],  // Northeast
            ['q' => 0, 'r' => -1],  // Northwest
            ['q' => -1, 'r' => 0],  // West
            ['q' => -1, 'r' => 1],  // Southwest
            ['q' => 0, 'r' => 1],   // Southeast
        ];

        $adjacent = [];
        foreach ($directions as $dir) {
            $newQ = $q + $dir['q'];
            $newR = $r + $dir['r'];
            if ($this->isOnBoard($newQ, $newR)) {
                $adjacent[] = ['q' => $newQ, 'r' => $newR];
            }
        }

        return $adjacent;
    }

    /**
     * Convert hex coordinates to pixel position
     * 
     * @param int $q
     * @param int $r
     * @return array [x, y] pixel coordinates
     */
    public function getHexCenter(int $q, int $r): array
    {
        $hexRadius = 30;
        $centerX = 400;
        $centerY = 400;
        
        $x = $hexRadius * sqrt(3) * ($q + $r / 2);
        $y = $hexRadius * (3 / 2) * $r;
        
        return [$x + $centerX, $y + $centerY];
    }

    /**
     * Get hexagon points for SVG polygon
     * 
     * @param int $q
     * @param int $r
     * @return string Points string for SVG polygon
     */
    public function getHexPoints(int $q, int $r): string
    {
        [$cx, $cy] = $this->getHexCenter($q, $r);
        $hexRadius = 30;
        
        $points = [];
        for ($i = 0; $i < 6; $i++) {
            $angle = (M_PI / 180) * (60 * $i - 30);
            $x = $cx + $hexRadius * cos($angle);
            $y = $cy + $hexRadius * sin($angle);
            $points[] = "{$x},{$y}";
        }
        
        return implode(' ', $points);
    }

    /**
     * Get color values for a given color name
     * 
     * @param string $color
     * @param string $type 'fill', 'stroke', or 'border'
     * @return string Hex color value
     */
    public function getColorValue(string $color, string $type = 'fill'): string
    {
        $colors = [
            'blue' => ['fill' => '#3b82f6', 'stroke' => '#60a5fa', 'border' => '#bae6fd'],      // Tailwind blue-500, blue-400, blue-200
            'red' => ['fill' => '#ef4444', 'stroke' => '#f87171', 'border' => '#fecaca'],        // Tailwind red-500, red-400, red-200
            'yellow' => ['fill' => '#eab308', 'stroke' => '#fde047', 'border' => '#fef08a'],     // Tailwind yellow-500, yellow-300, yellow-200
            'green' => ['fill' => '#22c55e', 'stroke' => '#4ade80', 'border' => '#bbf7d0'],      // Tailwind green-500, green-400, green-200
            'orange' => ['fill' => '#f59e42', 'stroke' => '#fdba74', 'border' => '#ffedd5'],     // Tailwind orange-500, orange-300, orange-100
            'purple' => ['fill' => '#a855f7', 'stroke' => '#c084fc', 'border' => '#ddd6fe'],     // Tailwind purple-500, purple-400, purple-200
        ];
        
        return $colors[$color][$type] ?? $colors['blue'][$type];
    }

    /**
     * Get a token by ID
     *
     * @param int $tokenId
     * @return TokenState|null
     */
    public function getToken(int $tokenId): ?TokenState
    {
        if (!in_array($tokenId, $this->token_ids)) {
            return null;
        }
        
        return TokenState::load($tokenId);
    }

    /**
     * Get all tokens for a specific player
     *
     * @param int $playerId
     * @return array Array of TokenState objects
     */
    public function getTokensForPlayer(int $playerId): array
    {
        $tokens = [];
        foreach ($this->token_ids as $tokenId) {
            $token = TokenState::load($tokenId);
            if ($token && $token->player_id === $playerId) {
                $tokens[] = $token;
            }
        }
        return $tokens;
    }

    /**
     * Get all tokens on the board
     *
     * @return array Array of TokenState objects
     */
    public function getAllTokens(): array
    {
        $tokens = [];
        foreach ($this->token_ids as $tokenId) {
            $token = TokenState::load($tokenId);
            if ($token) {
                $tokens[] = $token;
            }
        }
        return $tokens;
    }

    /**
     * Get token at a specific position
     *
     * @param int $q
     * @param int $r
     * @return TokenState|null
     */
    public function getTokenAtPosition(int $q, int $r): ?TokenState
    {
        foreach ($this->token_ids as $tokenId) {
            $token = TokenState::load($tokenId);
            if ($token && $token->q === $q && $token->r === $r) {
                return $token;
            }
        }
        return null;
    }

    /**
     * Recalculate valid moves for all tokens on the board
     *
     * @return void
     */
    public function recalculateAllTokenMoves(): void
    {
        foreach ($this->token_ids as $tokenId) {
            $token = TokenState::load($tokenId);
            if ($token) {
                $token->calculateValidMoves($this);
            }
        }
    }

    /**
     * Get all tokens as array for serialization (for frontend)
     *
     * @return array
     */
    public function getTokensArray(): array
    {
        $tokens = [];
        foreach ($this->token_ids as $tokenId) {
            $token = TokenState::load($tokenId);
            if ($token) {
                $tokens[] = [
                    'id' => $token->id,
                    'player_id' => $token->player_id,
                    'q' => $token->q,
                    'r' => $token->r,
                    'valid_moves' => $token->valid_moves,
                ];
            }
        }
        return $tokens;
    }
}
