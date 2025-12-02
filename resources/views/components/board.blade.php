@props(['board', 'game', 'channel', 'auth_player_id'])
@php
    $cells = $board->getCells();
    $players = $game->players()->keyBy('id');
@endphp

<div class="relative mx-auto w-full h-auto flex items-center justify-center">
    <style>
        .selectable-token {
            cursor: pointer;
        }
        .selectable-token:hover .token-border {
            stroke-width: 4 !important;
            opacity: 0.8;
        }
        .player-token.selected .token-border {
            stroke: #f59e0b !important;
            stroke-width: 5 !important;
            filter: drop-shadow(0 0 4px rgba(245, 158, 11, 0.6));
        }
        .move-highlight {
            cursor: pointer;
        }
        .move-highlight:hover {
            fill-opacity: 0.5 !important;
            stroke-width: 3 !important;
        }
        .hex-polygon {
            transition: stroke-width 0.2s ease;
        }
        .hex-cell:hover .hex-polygon {
            stroke-width: 2;
        }
    </style>
    <svg id="board-svg" 
        class="w-full h-auto max-w-[600px]"
        viewBox="0 0 800 800"
        preserveAspectRatio="xMidYMid meet"
        xmlns="http://www.w3.org/2000/svg">
        
        <!-- Hexagonal cells will be rendered here -->
        @foreach($cells as $cell)
            @php
                $q = $cell['q'];
                $r = $cell['r'];
                $sum = -$q - $r;
                
                if ($r <= -5) $fill = '#dc2626'; // North - red-600
                elseif ($r >= 5) $fill = '#2563eb'; // South - blue-600
                elseif ($q >= 5) $fill = '#eab308'; // NE - yellow-600
                elseif ($q <= -5) $fill = '#9333ea'; // SW - purple-600
                elseif ($sum <= -5) $fill = '#16a34a'; // SE - green-600
                elseif ($sum >= 5) $fill = '#f97316'; // NW - orange-600
                else $fill = '#e5e5e5'; // Center
            @endphp
            
            <g class="hex-cell" data-q="{{ $q }}" data-r="{{ $r }}" data-piece="{{ $cell['piece'] ?? 'null' }}">
                <polygon 
                    class="hex-polygon"
                    fill="{{ $fill }}"
                    stroke="#0f172a"
                    stroke-width="2"
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
                <g class="player-token {{ $cell['piece'] == $auth_player_id ? 'selectable-token' : '' }}" 
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
                        fill-opacity="0.3"
                    />
                    <!-- Token background -->
                    <circle
                        cx="{{ $cx }}"
                        cy="{{ $cy }}"
                        r="15"
                        class="token-bg"
                        fill="{{ $board->getColorValue($color, 'fill') }}"
                        fill-opacity="0.8"
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
            this.authPlayerId = '{{ $auth_player_id ?? 'null' }}';
            this.gameId = {{ $game->id }};
            this.selectedToken = null;
            this.cells = @json($cells);
            this.activePlayerId = '{{ $game->activePlayer()?->id ?? 'null' }}';
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

        getAdjacentPositions(q, r) {
            const directions = [
                { q: 1, r: 0 },   // East
                { q: 1, r: -1 },  // Northeast
                { q: 0, r: -1 },  // Northwest
                { q: -1, r: 0 },  // West
                { q: -1, r: 1 },  // Southwest
                { q: 0, r: 1 },   // Southeast
            ];

            const adjacent = [];
            directions.forEach(dir => {
                const newQ = q + dir.q;
                const newR = r + dir.r;
                const key = `${newQ},${newR}`;
                if (this.cells[key]) {
                    adjacent.push({ q: newQ, r: newR });
                }
            });

            return adjacent;
        }

        getEmptyAdjacentPositions(q, r) {
            const adjacent = this.getAdjacentPositions(q, r);
            return adjacent.filter(pos => {
                const key = `${pos.q},${pos.r}`;
                // Check cells data first
                if (this.cells && this.cells[key]) {
                    return this.cells[key].piece === null;
                }
                // Fallback: check DOM
                const hexCell = document.querySelector(`.hex-cell[data-q="${pos.q}"][data-r="${pos.r}"]`);
                if (hexCell) {
                    const piece = hexCell.getAttribute('data-piece');
                    return piece === 'null' || piece === null;
                }
                return false;
            });
        }

        clearHighlights() {
            // Remove move highlights
            document.querySelectorAll('.move-highlight').forEach(el => el.remove());
            // Remove token selection
            document.querySelectorAll('.player-token.selected').forEach(el => {
                el.classList.remove('selected');
            });
        }

        highlightAdjacentSpaces(q, r) {
            const emptyAdjacent = this.getEmptyAdjacentPositions(q, r);
            const svg = document.getElementById('board-svg');
            
            emptyAdjacent.forEach(pos => {
                const position = this.hexPositions[`${pos.q},${pos.r}`];
                if (!position) return;

                // Create highlight circle
                const highlight = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                highlight.setAttribute('cx', position.x);
                highlight.setAttribute('cy', position.y);
                highlight.setAttribute('r', 20);
                highlight.setAttribute('fill', '#f59e0b');
                highlight.setAttribute('fill-opacity', '0.3');
                highlight.setAttribute('stroke', '#d97706');
                highlight.setAttribute('stroke-width', '2');
                highlight.setAttribute('stroke-dasharray', '4,2');
                highlight.classList.add('move-highlight');
                highlight.setAttribute('data-q', pos.q);
                highlight.setAttribute('data-r', pos.r);
                highlight.style.cursor = 'pointer';
                svg.appendChild(highlight);
            });
        }

        selectToken(token) {
            // Clear previous selection
            this.clearHighlights();
            
            const playerId = token.getAttribute('data-player-id');
            const q = parseInt(token.getAttribute('data-q'));
            const r = parseInt(token.getAttribute('data-r'));

            // Only allow selecting own tokens and only if it's the active player's turn
            if (String(playerId) !== String(this.authPlayerId) || 
                String(this.authPlayerId) !== String(this.activePlayerId)) {
                return;
            }

            this.selectedToken = { playerId, q, r };
            token.classList.add('selected');
            
            // Enhance border for selected token (CSS will handle the styling)
            const border = token.querySelector('.token-border');
            if (border) {
                // The CSS class will handle the styling
            }

            // Highlight adjacent empty spaces
            this.highlightAdjacentSpaces(q, r);
        }

        async moveTokenToSpace(toQ, toR) {
            if (!this.selectedToken) return;
            if (this.movementInProgress) return;

            const { playerId, q: fromQ, r: fromR } = this.selectedToken;

            try {
                const url = '{{ $auth_player_id ? route('players.moveToken', ['game_id' => $game->id, 'player_id' => $auth_player_id]) : null }}';
                const response = await axios.post(url, {
                    _token: '{{ csrf_token() }}',
                    from_q: fromQ,
                    from_r: fromR,
                    to_q: toQ,
                    to_r: toR,
                });
                
                // Clear selection - the animation will be handled by the event
                this.clearHighlights();
                this.selectedToken = null;
            } catch (error) {
                console.error('Error moving token:', error.response.data);
            }
        }

        handleTokenClick(e) {
            if (this.movementInProgress) return;

            const token = e.target.closest('.player-token');
            if (!token) return;

            const playerId = token.getAttribute('data-player-id');
            
            // Only allow selecting own tokens
            if (String(playerId) !== String(this.authPlayerId)) {
                return;
            }

            // Only allow selecting if it's the active player's turn
            if (String(this.authPlayerId) !== String(this.activePlayerId)) {
                return;
            }

            e.stopPropagation();
            this.selectToken(token);
        }

        handleSpaceClick(e) {
            if (this.movementInProgress) return;
            if (!this.selectedToken) return;

            const highlight = e.target.closest('.move-highlight');
            if (!highlight) return;

            e.stopPropagation();
            const toQ = parseInt(highlight.getAttribute('data-q'));
            const toR = parseInt(highlight.getAttribute('data-r'));
            this.moveTokenToSpace(toQ, toR);
        }

        handleBoardClick(e) {
            // If clicking on the board but not on a token or highlight, clear selection
            if (e.target.closest('.player-token') || e.target.closest('.move-highlight')) {
                return;
            }
            
            if (e.target.closest('#board-svg')) {
                this.clearHighlights();
                this.selectedToken = null;
            }
        }

        updateCursorStyles() {
            const isMyTurn = String(this.authPlayerId) === String(this.activePlayerId);
            document.querySelectorAll('.selectable-token').forEach(token => {
                if (isMyTurn && !this.movementInProgress) {
                    token.style.cursor = 'pointer';
                } else {
                    token.style.cursor = 'default';
                }
            });
        }

        handleEvent(eventData, boardState, playerState) {
            // Check if event is TokenMoved and has move data
            if (eventData && typeof eventData === 'object' && eventData.type === 'App\\Events\\Gameplay\\TokenMoved') {
                this.movementInProgress = true;
                
                const { from_q, from_r, to_q, to_r, player_id } = eventData;
                
                // Clear selection if this move was made by the current user
                if (this.selectedToken && 
                    this.selectedToken.q === from_q && 
                    this.selectedToken.r === from_r &&
                    String(this.selectedToken.playerId) === String(player_id)) {
                    this.clearHighlights();
                    this.selectedToken = null;
                }
                
                this.moveToken(player_id, from_q, from_r, to_q, to_r)
                    .then(() => {
                        this.movementInProgress = false;
                        this.updateCursorStyles();
                    });
            } else if (eventData === 'App\\Events\\Gameplay\\TokenMoved') {
                // Fallback: if event is just a string, try to find the move from boardState
                // This shouldn't happen with our updated event, but handle it gracefully
                console.warn('TokenMoved event received without move data');
                this.movementInProgress = false;
                this.updateCursorStyles();
            }

            // Update active player if changed
            if (boardState && boardState.active_player_id !== undefined) {
                this.activePlayerId = String(boardState.active_player_id);
                // Clear selection if it's no longer the player's turn
                if (String(this.authPlayerId) !== String(this.activePlayerId)) {
                    this.clearHighlights();
                    this.selectedToken = null;
                }
                this.updateCursorStyles();
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
                } else {
                    // Handle other events that might update game state
                    this.handleEvent(eventData, data.gameState || data.boardState, data.playerState);
                }
            });

            // Add click handlers
            const svg = document.getElementById('board-svg');
            if (svg) {
                svg.addEventListener('click', (e) => {
                    this.handleTokenClick(e);
                    this.handleSpaceClick(e);
                    this.handleBoardClick(e);
                });
            }

            // Update cursor styles initially
            this.updateCursorStyles();
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (window.Echo) {
            window.board = new Board();
            window.board.init();
        }
    });
</script>

