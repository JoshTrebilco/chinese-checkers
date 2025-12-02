<?php

namespace App\States;

use Thunk\Verbs\State;

class BoardState extends State
{
    public array $cells = [];

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
     * @param string $color The player color (blue, red, yellow, green, teal, purple)
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
                    case 'blue': // North
                        $matches = $r <= -5 && $r >= -8 && $q >= 1 && $q <= 4 && $sum >= 0 && $sum <= 4;
                        break;
                    case 'red': // South
                        $matches = $r >= 5 && $r <= 8 && $q >= -4 && $q <= -1 && $sum >= -4 && $sum <= 0;
                        break;
                    case 'yellow': // NE
                        $matches = $q >= 5 && $q <= 8 && $r >= -4 && $r <= 0 && $sum >= -4 && $sum <= 0;
                        break;
                    case 'green': // SW
                        $matches = $q <= -5 && $q >= -8 && $r >= 0 && $r <= 4 && $sum <= 4 && $sum >= 0;
                        break;
                    case 'teal': // SE
                        $matches = $sum <= -5 && $sum >= -8 && $r <= 4 && $r >= -4 && $q >= -4 && $q <= 4;
                        break;
                    case 'purple': // NW
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
            'blue' => ['fill' => '#3b82f6', 'stroke' => '#60a5fa', 'border' => '#93c5fd'],
            'red' => ['fill' => '#ef4444', 'stroke' => '#f87171', 'border' => '#fca5a5'],
            'yellow' => ['fill' => '#eab308', 'stroke' => '#fbbf24', 'border' => '#fde68a'],
            'green' => ['fill' => '#22c55e', 'stroke' => '#4ade80', 'border' => '#86efac'],
            'teal' => ['fill' => '#14b8a6', 'stroke' => '#2dd4bf', 'border' => '#5eead4'],
            'purple' => ['fill' => '#a855f7', 'stroke' => '#c084fc', 'border' => '#c4b5fd'],
        ];
        
        return $colors[$color][$type] ?? $colors['blue'][$type];
    }
}
