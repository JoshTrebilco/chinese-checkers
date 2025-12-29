@props(['game', 'auth_player_id', 'channel'])
<div class="space-y-3">
    @if(! $game->hasPlayer($auth_player_id) && ! $game->isInProgress() && !$game->ended)
        <div class="bg-slate-900/50 backdrop-blur-sm rounded-2xl p-6 border border-red-800/50 shadow-xl">
            <div class="flex items-center space-x-3 mb-4">
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 bg-red-900/50 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 5a2 2 0 1 0 0-4 2 2 0 0 0 0 4zm0 0v3m-4 1h8m-8 0c0 2 1.5 3 4 3s4-1 4-3m-8 0l-1 8h10l-1-8" />
                        </svg>
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-amber-300">
                    Select Your Piece
                </h3>
            </div>
            <div class="flex justify-center gap-4">
                @foreach($game->available_colors as $color)
                    <form action="{{ route('players.join', ['game_id' => $game->id]) }}" method="post">
                        @csrf
                        <input type="hidden" name="color" value="{{ $color }}">
                        <button type="submit" class="transform transition hover:scale-110">
                            <x-token :color="$color" :size="50" />
                        </button>
                    </form>
                @endforeach
            </div>
        </div>
    @endif

    @if ($game->created && $game->hasEnoughPlayers() && ! $game->isInProgress() && !$game->ended)
        <form action="{{ route('players.startGame', ['game_id' => $game->id]) }}" method="post">
            @csrf
            <button type="submit"
                class="w-full bg-linear-to-r from-red-600 to-amber-500 text-white rounded-lg px-4 py-3 font-semibold transform transition hover:translate-y-[-2px]">
                Begin the Match
            </button>
        </form>
    @endif

    @if ($game->hasPlayer($auth_player_id) && !$game->isInProgress() && !$game->ended)
        <!-- Share Game Section -->
        <div class="bg-slate-900/50 backdrop-blur-sm rounded-2xl px-4 py-3 shadow-xl">
            <div class="flex items-center space-x-3 mb-4">
                <div class="shrink-0">
                    <div class="w-10 h-10 bg-red-900/50 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                        </svg>
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-amber-300">
                    Invite Masters
                </h3>
            </div>

            <div id="copy-section" class="space-y-3">
                <p class="text-amber-200/80 text-sm">Share this path with others to invite them to your match:</p>

                <div class="flex gap-2">
                    <input
                        type="text"
                        readonly
                        value="{{ url('/games/' . $game->id) }}"
                        class="w-full px-4 py-2 rounded-lg border-2 border-red-500/20 bg-slate-900/30 text-amber-200 text-sm focus:outline-none"
                    />
                    <button
                        id="copy-button"
                        class="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-red-600 to-amber-500 text-white rounded-lg font-semibold transform transition hover:translate-y-[-2px]"
                    >
                        <span id="copy-text">Copy</span>
                        <svg id="copy-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                        </svg>
                        <svg id="check-icon" class="w-4 h-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

    @endif

    @if(! $game->hasPlayer($auth_player_id) && $game->isInProgress())
        <div class="bg-slate-900/50 backdrop-blur-sm rounded-2xl p-6 border border-red-800/50 shadow-xl lg:max-w-60">
            <div>
                <h3 class="text-lg font-semibold text-amber-300">
                    Match in Progress
                </h3>
                <p class="text-amber-200/80 text-sm mt-4">
                    You are observing this match.
                </p>
                <p class="text-amber-200/80 text-sm mt-2">
                    Wait for it to conclude before joining anew!
                </p>
            </div>
        </div>
    @endif
</div>

<!-- Fixed Bottom Players Bar -->
<div id="players-bar" class="fixed bottom-0 left-0 right-0 z-50 bg-slate-900/95 backdrop-blur-sm border-t border-red-800/50 shadow-2xl">
    <!-- Collapsed State - Shows Active Player's Turn -->
    <button 
        id="players-bar-toggle" 
        class="w-full px-4 py-3 flex items-center justify-between hover:bg-slate-800/50 transition-colors"
    >
        @if(! $game->isInProgress() && !$game->ended)
            <div class="flex items-center space-x-3">
                <span class="text-sm text-amber-300/80">Current Turn:</span>
                <div id="active-player-display" class="flex items-center space-x-2">
                    @php
                        $activePlayer = $game->activePlayer();
                    @endphp
                    @if($activePlayer)
                        <x-token :color="$activePlayer->color" :size="24" />
                        <span class="text-amber-200 font-medium" id="active-player-name">{{ $activePlayer->name }}</span>
                        @if ($activePlayer->id == $auth_player_id)
                            <span class="inline-flex items-center rounded-md bg-amber-400/10 px-2 py-1 text-xs font-medium text-amber-400 ring-1 ring-inset ring-amber-400/30">
                                You
                            </span>
                        @endif
                    @else
                        <span class="text-amber-200/60 font-medium">Waiting...</span>
                    @endif
                </div>
            </div>
        @endif
        @if($game->ended)
            <div class="flex items-center space-x-3">
                <span class="text-sm text-amber-300/80">Winner:</span>
                <x-token :color="$game->winner()?->color" :size="24" />
                <span class="text-amber-200 font-medium">{{ $game->winner()?->name }}</span>
                @if($game->winner()?->id == $auth_player_id)
                    <span class="inline-flex items-center rounded-md bg-amber-400/10 px-2 py-1 text-xs font-medium text-amber-400 ring-1 ring-inset ring-amber-400/30">
                        You
                    </span>
                @endif
            </div>
        @endif
        <svg 
            id="players-bar-chevron" 
            class="w-5 h-5 text-amber-300 transition-transform" 
            fill="none" 
            stroke="currentColor" 
            viewBox="0 0 24 24"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <!-- Expanded State - Shows All Players -->
    <div id="players-bar-content" class="hidden border-t border-red-800/30">
        <div class="px-4 py-3">
            <h3 class="text-sm font-semibold text-amber-300 mb-3">Masters</h3>
            <ul class="space-y-2" id="players-list">
                @foreach ($game->players() as $player)
                    <li class="flex items-center space-x-3" data-player-id="{{ $player->id }}">
                        <x-token :color="$player->color" :size="20" />
                        <span class="text-amber-200 text-sm flex-grow">{{ $player->name }}</span>
                        @if ($player->id == $auth_player_id)
                            <span class="inline-flex items-center rounded-md bg-amber-400/10 px-2 py-1 text-xs font-medium text-amber-400 ring-1 ring-inset ring-amber-400/30">
                                You
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>

<script>
    class Panel {
        constructor() {
            this.players = {!! json_encode($game->players()->map(fn($p) => ['id' => (string)$p->id, 'name' => $p->name, 'color' => $p->color])) !!};
            this.authPlayerId = '{{ $auth_player_id ?? 'null' }}';
            this.activePlayerId = '{{ $game->activePlayer()?->id ?? 'null' }}';
            this.channel = window.Echo.channel(@json($channel));
        }

        handleEvent(event, gameState) {
            // Update active player if changed
            if (gameState?.active_player_id !== undefined && String(gameState.active_player_id) !== String(this.activePlayerId)) {
                this.activePlayerId = String(gameState.active_player_id);
                this.updateUI();
            }

            // Setup events should refresh the page
            if (event && typeof event === 'string' && event.startsWith('App\\Events\\Setup')) {
                window.location.reload();
            } else if (event && typeof event === 'object' && event.type && event.type.startsWith('App\\Events\\Setup')) {
                window.location.reload();
            }
        }

        updateUI() {
            // Update active player display in collapsed state
            const activePlayerDisplay = document.getElementById('active-player-display');
            if (!activePlayerDisplay) return;

            if (this.activePlayerId && this.activePlayerId !== 'null') {
                const activePlayer = this.players.find(p => String(p.id) === String(this.activePlayerId));
                if (activePlayer) {
                    activePlayerDisplay.innerHTML = this.generateTokenHTML(activePlayer.color, 24) + 
                        `<span class="text-amber-200 font-medium" id="active-player-name">${activePlayer.name}</span>`
                        + (activePlayer.id == this.authPlayerId ? `<span class="inline-flex items-center rounded-md bg-amber-400/10 px-2 py-1 text-xs font-medium text-amber-400 ring-1 ring-inset ring-amber-400/30">
                            You
                        </span>` : '');
                }
            } else {
                activePlayerDisplay.innerHTML = '<span class="text-amber-200/60 font-medium">Waiting...</span>';
            }
        }

        generateTokenHTML(color, size) {
            const colorClasses = {
                blue: { glow: 'fill-blue-500/20', bg: 'fill-blue-500/50 stroke-blue-400', border: 'stroke-blue-300' },
                green: { glow: 'fill-green-500/20', bg: 'fill-green-500/50 stroke-green-400', border: 'stroke-green-300' },
                red: { glow: 'fill-red-500/20', bg: 'fill-red-500/50 stroke-red-400', border: 'stroke-red-300' },
                yellow: { glow: 'fill-yellow-500/20', bg: 'fill-yellow-500/50 stroke-yellow-400', border: 'stroke-yellow-300' },
                orange: { glow: 'fill-orange-500/20', bg: 'fill-orange-500/50 stroke-orange-400', border: 'stroke-orange-300' },
                purple: { glow: 'fill-purple-500/20', bg: 'fill-purple-500/50 stroke-purple-400', border: 'stroke-purple-300' }
            };
            
            const colors = colorClasses[color] || colorClasses.blue;
            const center = size / 2;
            const glowRadius = size * 0.36;
            const tokenRadius = size * 0.3;
            
            return `<svg style="width: ${size}px; height: ${size}px;" viewBox="0 0 ${size} ${size}">
                <g class="player-token">
                    <circle cx="${center}" cy="${center}" r="${glowRadius}" class="transition-opacity ${colors.glow}" />
                    <circle cx="${center}" cy="${center}" r="${tokenRadius}" class="transition-opacity ${colors.bg}" />
                    <circle cx="${center}" cy="${center}" r="${tokenRadius}" class="fill-none stroke-[3] ${colors.border}" />
                </g>
            </svg>`;
        }

        setupPlayersBarToggle() {
            const toggle = document.getElementById('players-bar-toggle');
            const content = document.getElementById('players-bar-content');
            const chevron = document.getElementById('players-bar-chevron');
            
            if (!toggle || !content || !chevron) return;

            toggle.addEventListener('click', () => {
                const isExpanded = !content.classList.contains('hidden');
                
                if (isExpanded) {
                    content.classList.add('hidden');
                    chevron.classList.remove('rotate-180');
                } else {
                    content.classList.remove('hidden');
                    chevron.classList.add('rotate-180');
                }
            });
        }

        setupCopyButton() {
            const copyButton = document.getElementById('copy-button');
            if (!copyButton) return;

            copyButton.addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText('{{ url('/games/' . $game->id) }}');
                    this.showCopySuccess();
                } catch (err) {
                    console.error('Copy failed:', err);
                }
            });
        }

        showCopySuccess() {
            const copyText = document.getElementById('copy-text');
            const copyIcon = document.getElementById('copy-icon');
            const checkIcon = document.getElementById('check-icon');
            
            if (copyText) copyText.textContent = 'Copied!';
            if (copyIcon) copyIcon.classList.add('hidden');
            if (checkIcon) checkIcon.classList.remove('hidden');
            
            setTimeout(() => {
                if (copyText) copyText.textContent = 'Copy';
                if (copyIcon) copyIcon.classList.remove('hidden');
                if (checkIcon) checkIcon.classList.add('hidden');
            }, 2000);
        }

        init() {
            this.channel.listen('BroadcastEvent', (data) => {
                this.handleEvent(data.event, data.gameState);
            });

            this.updateUI();
            this.setupCopyButton();
            this.setupPlayersBarToggle();
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (window.Echo) {
            window.panel = new Panel();
            window.panel.init();
        }
    });
</script>

