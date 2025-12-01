<?php

namespace App\States;

use Thunk\Verbs\State;

class BoardState extends State
{
    public array $cells = [];

    public ?int $player_id = null;

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

    public function player(): ?PlayerState
    {
        return $this->player_id ? PlayerState::load($this->player_id) : null;
    }
}
