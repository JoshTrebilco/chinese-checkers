@props(['board', 'game', 'channel'])
@php
    $cells = $board->getCells();
    $players = $game->players()->keyBy('id');
@endphp

<div class="relative w-full h-auto flex items-center justify-center">
    <svg id="board-svg" 
        class="w-full h-auto max-w-[800px] max-h-[800px]"
        viewBox="0 0 800 800"
        preserveAspectRatio="xMidYMid meet"
        xmlns="http://www.w3.org/2000/svg">
        
        <!-- Hexagonal cells will be rendered here -->
        @foreach($cells as $cell)
            @php
                $q = $cell['q'];
                $r = $cell['r'];
                $sum = -$q - $r;
                
                // Determine color based on region
                $fill = '#1e293b'; // Default dark
                if ($r <= -5) $fill = '#3b82f6'; // North - blue
                elseif ($r >= 5) $fill = '#ef4444'; // South - red
                elseif ($q >= 5) $fill = '#eab308'; // NE - yellow
                elseif ($q <= -5) $fill = '#22c55e'; // SW - green
                elseif ($sum <= -5) $fill = '#14b8a6'; // SE - teal
                elseif ($sum >= 5) $fill = '#a855f7'; // NW - purple
                else $fill = '#475569'; // Center
            @endphp
            
            <g class="hex-cell" data-q="{{ $q }}" data-r="{{ $r }}">
                <polygon 
                    class="hex-polygon"
                    fill="{{ $fill }}"
                    fill-opacity="0.3"
                    stroke="#64748b"
                    stroke-width="1"
                    points="{{ $board->getHexPoints($q, $r) }}"
                />
            </g>
        @endforeach

        <!-- Tokens will be rendered here -->
        @foreach($cells as $cell)
            @if($cell['piece'] !== null)
                @php
                    $player = $players->get($cell['piece']);
                    if (!$player) continue;
                    $color = $player->color ?? 'blue';
                @endphp
                <g class="player-token" 
                   data-player-id="{{ $cell['piece'] }}" 
                   data-q="{{ $cell['q'] }}" 
                   data-r="{{ $cell['r'] }}"
                   data-color="{{ $color }}">
                    @php
                        [$cx, $cy] = $board->getHexCenter($cell['q'], $cell['r']);
                    @endphp
                    <!-- Token glow effect -->
                    <circle
                        cx="{{ $cx }}"
                        cy="{{ $cy }}"
                        r="18"
                        class="token-glow"
                        fill="{{ $board->getColorValue($color, 'fill') }}"
                        fill-opacity="0.2"
                    />
                    <!-- Token background -->
                    <circle
                        cx="{{ $cx }}"
                        cy="{{ $cy }}"
                        r="15"
                        class="token-bg"
                        fill="{{ $board->getColorValue($color, 'fill') }}"
                        fill-opacity="0.5"
                        stroke="{{ $board->getColorValue($color, 'stroke') }}"
                        stroke-width="2"
                    />
                    <!-- Token border -->
                    <circle
                        cx="{{ $cx }}"
                        cy="{{ $cy }}"
                        r="15"
                        class="token-border"
                        fill="none"
                        stroke="{{ $board->getColorValue($color, 'border') }}"
                        stroke-width="3"
                    />
                </g>
            @endif
        @endforeach
    </svg>
</div>

<script>
    class Board {
        constructor() {
            this.hexRadius = 30;
            this.centerX = 400;
            this.centerY = 400;
            this.movementInProgress = false;
            this.channel = window.Echo.channel(@json($channel));
            this.hexPositions = this.calculateHexPositions();
        }

        calculateHexPositions() {
            const positions = {};
            const cells = @json($cells);
            
            Object.values(cells).forEach(cell => {
                const { x, y } = this.axialToPixel(cell.q, cell.r);
                positions[`${cell.q},${cell.r}`] = { x: x + this.centerX, y: y + this.centerY };
            });
            
            return positions;
        }

        axialToPixel(q, r) {
            const x = this.hexRadius * Math.sqrt(3) * (q + r / 2);
            const y = this.hexRadius * (3 / 2) * r;
            return { x, y };
        }

        getToken(playerId, q, r) {
            // Try to find token by player ID and coordinates
            const token = document.querySelector(`[data-player-id="${playerId}"][data-q="${q}"][data-r="${r}"]`);
            if (!token) {
                // Fallback: find any token for this player
                const fallback = document.querySelector(`[data-player-id="${playerId}"]`);
                if (fallback) {
                    return fallback;
                }
                console.warn(`Token for player ${playerId} at (${q}, ${r}) not found`);
            }
            return token;
        }

        updateTokenPosition(token, q, r, duration = '0.3s') {
            if (!token) return;
            
            const position = this.hexPositions[`${q},${r}`];
            if (!position) {
                console.warn(`Position (${q}, ${r}) not found`);
                return;
            }

            // Update data attributes
            token.setAttribute('data-q', q);
            token.setAttribute('data-r', r);

            // Update all circles in the token group
            token.querySelectorAll('circle').forEach(circle => {
                circle.style.transition = `cx ${duration} ease-in-out, cy ${duration} ease-in-out`;
                circle.setAttribute('cx', position.x);
                circle.setAttribute('cy', position.y);
            });
        }

        async moveToken(playerId, fromQ, fromR, toQ, toR) {
            const token = this.getToken(playerId, fromQ, fromR);
            if (!token) return;

            if (fromQ === toQ && fromR === toR) return;

            this.movementInProgress = true;
            
            // Animate directly to destination
            this.updateTokenPosition(token, toQ, toR, '0.5s');
            
            await this.delay(500);
            this.movementInProgress = false;
        }

        delay(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }

        async waitForMovement() {
            return new Promise((resolve) => {
                const checkMovement = () => {
                    if (!this.movementInProgress) {
                        resolve();
                    } else {
                        setTimeout(checkMovement, 50);
                    }
                };
                checkMovement();
            });
        }

        handleEvent(eventData, boardState, playerState) {
            // Check if event is TokenMoved and has move data
            if (eventData && typeof eventData === 'object' && eventData.type === 'App\\Events\\Gameplay\\TokenMoved') {
                this.movementInProgress = true;
                
                const { from_q, from_r, to_q, to_r, player_id } = eventData;
                
                this.moveToken(player_id, from_q, from_r, to_q, to_r)
                    .then(() => {
                        this.movementInProgress = false;
                    });
            } else if (eventData === 'App\\Events\\Gameplay\\TokenMoved') {
                // Fallback: if event is just a string, try to find the move from boardState
                // This shouldn't happen with our updated event, but handle it gracefully
                console.warn('TokenMoved event received without move data');
                this.movementInProgress = false;
            }
        }

        init() {
            this.channel.listen('BroadcastEvent', (data) => {
                // The event can be either a string (class name) or an object with type and move data
                const eventData = data.event;
                
                // Handle TokenMoved events with move data
                if (eventData && typeof eventData === 'object' && eventData.type === 'App\\Events\\Gameplay\\TokenMoved') {
                    this.handleEvent(eventData, data.boardState, data.playerState);
                } else if (eventData === 'App\\Events\\Gameplay\\TokenMoved') {
                    // Fallback for string-based event
                    this.handleEvent(eventData, data.boardState, data.playerState);
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (window.Echo) {
            window.board = new Board();
            window.board.init();
        }
    });
</script>

