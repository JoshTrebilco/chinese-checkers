<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class BroadcastEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $event;

    public $gameState;

    public $playerState;

    public $boardState;

    public function __construct()
    {
        $this->event = null;
        $this->gameState = null;
        $this->playerState = null;
        $this->boardState = null;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('debug-channel'),
            new Channel(Str::after(config('app.url'), 'https://').'.'.'game.'.$this->gameState->id),
        ];
    }

    public function broadcastWith()
    {
        $data = [
            'event' => $this->event,
            'gameState' => $this->gameState,
            'playerState' => $this->playerState,
            'boardState' => $this->boardState,
            'gameChannel' => Str::after(config('app.url'), 'https://').'.'.'game.'.$this->gameState->id,
        ];

        // Convert large integers to strings to prevent JavaScript precision loss
        return $this->convertLargeIntegersToStrings($data);
    }

    private function convertLargeIntegersToStrings($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->convertLargeIntegersToStrings($value);
            }
        } elseif (is_object($data)) {
            $array = (array) $data;
            foreach ($array as $key => $value) {
                $array[$key] = $this->convertLargeIntegersToStrings($value);
            }
            $data = (object) $array;
        } elseif (is_int($data)) {
            return (string) $data;
        }

        return $data;
    }

    public function getEvent()
    {
        return $this->event;
    }

    public function getGameState()
    {
        return $this->gameState;
    }

    public function getPlayerState()
    {
        return $this->playerState;
    }

    public function getBoardState()
    {
        return $this->boardState;
    }

    public function setEvent($event)
    {
        $this->event = $event;
    }

    public function setGameState($game)
    {
        $this->gameState = $game;
    }

    public function setPlayerState($player)
    {
        $this->playerState = $player;
    }

    public function setBoardState($board)
    {
        $this->boardState = $board;
    }
}
