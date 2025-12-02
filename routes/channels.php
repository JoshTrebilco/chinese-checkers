<?php

use App\States\GameState;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Allow public access to game channels (for now, can be restricted later)
Broadcast::channel('{host}.game.{game_id}', function ($user, $host, $game_id) {
    return true; // Allow all authenticated users to listen to game events
});

