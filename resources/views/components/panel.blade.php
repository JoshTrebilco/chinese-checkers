@props(['game', 'auth_player_id', 'channel'])
<div class="space-y-6">
    @if(! $game->hasPlayer($auth_player_id) && ! $game->isInProgress())
        <div class="bg-slate-900/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-800/50 shadow-xl">
            <div class="flex items-center space-x-3 mb-4">
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 bg-purple-900/50 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 5a2 2 0 1 0 0-4 2 2 0 0 0 0 4zm0 0v3m-4 1h8m-8 0c0 2 1.5 3 4 3s4-1 4-3m-8 0l-1 8h10l-1-8" />
                        </svg>
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-blue-300">
                    Choose Your Token
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

    @if ($game->hasPlayer($auth_player_id) && !$game->isInProgress())
        <!-- Share Game Section -->
        <div class="bg-slate-900/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-800/50 shadow-xl">
            <div class="flex items-center space-x-3 mb-4">
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 bg-purple-900/50 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                        </svg>
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-blue-300">
                    Invite Players
                </h3>
            </div>

            <div id="copy-section" class="space-y-3">
                <p class="text-blue-200/80 text-sm">Share this link with your friends to invite them to join your game:</p>

                <div class="flex gap-2">
                    <input
                        type="text"
                        readonly
                        value="{{ url('/games/' . $game->id) }}"
                        class="w-full px-4 py-2 rounded-lg border-2 border-purple-500/20 bg-slate-900/30 text-blue-200 text-sm focus:outline-none"
                    />
                    <button
                        id="copy-button"
                        class="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-purple-600 to-blue-500 text-white rounded-lg font-semibold transform transition hover:translate-y-[-2px]"
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

    @if ($game->created && $game->hasAllPlayersJoined() && ! $game->isInProgress())
        <form action="{{ route('players.startGame', ['game_id' => $game->id]) }}" method="post">
            @csrf
            <button type="submit"
                class="w-full bg-gradient-to-r from-purple-600 to-blue-500 text-white rounded-lg px-4 py-3 font-semibold transform transition hover:translate-y-[-2px]">
                Start Game
            </button>
        </form>
    @endif

    @if(! $game->hasPlayer($auth_player_id) && $game->isInProgress())
        <div class="bg-slate-900/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-800/50 shadow-xl lg:max-w-60">
            <div>
                <h3 class="text-lg font-semibold text-blue-300">
                    Game in Progress
                </h3>
                <p class="text-blue-200/80 text-sm mt-4">
                    You're spectating this game.
                </p>
                <p class="text-blue-200/80 text-sm mt-2">
                    Wait for it to finish before joining a new one!
                </p>
            </div>
        </div>
    @endif

    <div class="grid gap-6 grid-cols-2 lg:grid-cols-1">
        <!-- Players List -->
        <div class="bg-slate-900/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-800/50 shadow-xl">
            <h3 class="text-lg font-semibold text-blue-300 mb-4">Players</h3>
            <ul class="space-y-3" id="players-list">
                @foreach ($game->players() as $player)
                    <li class="flex items-center space-x-3" data-player-id="{{ $player->id }}">
                        <x-token :color="$player->color" :size="25" />

                        <div class="flex items-center space-x-2 flex-grow">
                            <span class="text-blue-200">{{ $player->name }}</span>

                            <div class="flex items-center gap-2 ml-auto">
                                @if ($player->id == $auth_player_id)
                                    <span class="inline-flex items-center rounded-md bg-purple-400/10 px-2 py-1 text-xs font-medium text-purple-400 ring-1 ring-inset ring-purple-400/30">
                                        You
                                    </span>
                                @endif

                                @if ($player->id == $game->activePlayer()?->id)
                                    <svg class="w-4 h-4 text-blue-400 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                @endif
                            </div>
                        </div>
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
            // Update active player indicator
            const playersList = document.getElementById('players-list');
            if (!playersList) return;

            const items = playersList.querySelectorAll('li');
            items.forEach(item => {
                const playerId = item.getAttribute('data-player-id');
                const spinner = item.querySelector('svg.animate-spin');
                
                if (String(playerId) === String(this.activePlayerId)) {
                    if (!spinner) {
                        // Add spinner if not present
                        const indicator = document.createElement('svg');
                        indicator.className = 'w-4 h-4 text-blue-400 animate-spin';
                        indicator.setAttribute('fill', 'none');
                        indicator.setAttribute('viewBox', '0 0 24 24');
                        indicator.innerHTML = '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>';
                        const actionsDiv = item.querySelector('.ml-auto');
                        if (actionsDiv) {
                            actionsDiv.appendChild(indicator);
                        }
                    }
                } else {
                    if (spinner) {
                        spinner.remove();
                    }
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
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (window.Echo) {
            window.panel = new Panel();
            window.panel.init();
        }
    });
</script>

