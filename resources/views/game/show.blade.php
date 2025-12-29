@php
    use Illuminate\Support\Str;
    $channel = Str::after(config('app.url'), 'https://').'.'.'game.'.$game->id;
    $board = $game->board();
    $tokens = $board ? $board->getAllTokens() : [];
@endphp

<x-layout>
    <!-- Header with Back Link -->
    <div class="absolute top-0 left-0 p-4">
        <a href="{{ route('games.index') }}"
            class="inline-flex items-center space-x-2 text-amber-300 hover:translate-x-[-2px] transition-transform">
            <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
            <span class="text-base font-bold text-amber-300">Chinese Checkers</span>
        </a>
    </div>

    <!-- Game Board -->
    <div class="flex flex-col gap-3 w-full max-w-full">
        @if($board)
            <x-board :board="$board" :game="$game" :channel="$channel" :auth_player_id="$auth_player_id" :tokens="$tokens" />
        @else
            <div class="bg-slate-900/50 backdrop-blur-sm rounded-2xl p-8 border border-red-800/50 shadow-xl">
                <p class="text-amber-300">Board is being created...</p>
            </div>
        @endif
        <x-panel :game="$game" :auth_player_id="$auth_player_id" :channel="$channel" />
    </div>

    <!-- Winner/Tie Modal (controlled by JavaScript) -->
    <div id="winner-modal" class="fixed inset-0 bg-slate-950/90 backdrop-blur-sm flex flex-col items-center justify-center hidden z-50">
        <div class="bg-slate-900/80 backdrop-blur-sm rounded-2xl p-8 border border-amber-800/50 shadow-xl text-center">
            <div class="flex items-center justify-center space-x-3 mb-6">
                <div class="w-10 h-10 rounded-full flex items-center justify-center">
                    <!-- Pre-rendered winner tokens for all 6 colors -->
                    <div id="winner-token-blue" class="hidden">
                        <x-token color="blue" :size="40" />
                    </div>
                    <div id="winner-token-green" class="hidden">
                        <x-token color="green" :size="40" />
                    </div>
                    <div id="winner-token-red" class="hidden">
                        <x-token color="red" :size="40" />
                    </div>
                    <div id="winner-token-yellow" class="hidden">
                        <x-token color="yellow" :size="40" />
                    </div>
                    <div id="winner-token-orange" class="hidden">
                        <x-token color="orange" :size="40" />
                    </div>
                    <div id="winner-token-purple" class="hidden">
                        <x-token color="purple" :size="40" />
                    </div>
                </div>
                <h2 id="winner-text" class="text-2xl font-bold text-amber-300">
                    <!-- Winner/tie text will be populated by JavaScript -->
                </h2>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <button id="view-board-btn"
                    class="hover:cursor-pointer inline-flex justify-center bg-gradient-to-r from-slate-600 to-slate-700 text-amber-100 rounded-lg px-6 py-3 font-semibold transform transition hover:translate-y-[-2px] border border-slate-500/50">
                    View Board
                </button>
                <a href="{{ route('games.index') }}"
                    class="inline-flex justify-center bg-gradient-to-r from-amber-600 to-orange-600 text-white rounded-lg px-6 py-3 font-semibold transform transition hover:translate-y-[-2px]">
                    Back to Games
                </a>
            </div>
        </div>
    </div>
</x-layout>

<script>
    class Game {
        constructor() {
            this.players = {!! json_encode($game->players()->map(fn($p) => ['id' => (string)$p->id, 'name' => $p->name, 'color' => $p->color])) !!};
            this.authPlayerId = '{{ $auth_player_id ?? 'null' }}';
            this.activePlayerId = '{{ $game->activePlayer()?->id ?? 'null' }}';
            this.channel = window.Echo.channel(@json($channel));
            this.winnerOverlayDismissed = false;
        }

        async waitForBoardAnimation() {
            // Wait for board animation to complete if it's in progress
            if (window.board && window.board.movementInProgress) {
                return new Promise((resolve) => {
                    const checkAnimation = () => {
                        if (!window.board.movementInProgress) {
                            resolve();
                        } else {
                            setTimeout(checkAnimation, 50);
                        }
                    };
                    checkAnimation();
                });
            }
            return Promise.resolve();
        }

        async handleEvent(event, gameState) {
            // Handle winner event
            if (event === 'App\\Events\\Gameplay\\PlayerWonGame' && gameState?.winner_id !== undefined) {
                await this.waitForBoardAnimation();
                this.showWinner(gameState.winner_id);
                return;
            }

            // Handle tie event
            if (event === 'App\\Events\\Gameplay\\PlayersTiedGame') {
                await this.waitForBoardAnimation();
                this.showTie();
                return;
            }

            // Handle turn end - wait for animation to complete before reload
            if (event === 'App\\Events\\Gameplay\\EndedTurn') {
                await this.waitForBoardAnimation();
                window.location.reload();
            }

            // Setup events should refresh the page
            if (event && typeof event === 'string' && event.startsWith('App\\Events\\Setup')) {
                window.location.reload();
            }
        }

        showWinner(winnerId) {
            // Don't show overlay if it was previously dismissed
            if (this.winnerOverlayDismissed) return;
            
            const winner = this.players.find(p => p.id === String(winnerId));
            if (!winner) return;

            this.updateWinnerToken(winner);
            this.updateWinnerText(winner);
            this.displayWinnerModal();
        }

        showTie() {
            // Don't show overlay if it was previously dismissed
            if (this.winnerOverlayDismissed) return;

            // Hide all winner tokens for tie
            document.querySelectorAll('[id^="winner-token-"]').forEach(token => {
                token.classList.add('hidden');
            });

            const text = document.getElementById('winner-text');
            text.textContent = "It's a tie!";
            
            this.displayWinnerModal();
        }

        updateWinnerToken(winner) {
            // Hide all winner tokens first
            document.querySelectorAll('[id^="winner-token-"]').forEach(token => {
                token.classList.add('hidden');
            });
            
            // Show the correct winner token
            const winnerToken = document.getElementById(`winner-token-${winner.color}`);
            if (winnerToken) {
                winnerToken.classList.remove('hidden');
            }
        }

        updateWinnerText(winner) {
            const text = document.getElementById('winner-text');
            text.textContent = `${winner.name} won the game!`;
        }

        displayWinnerModal() {
            const modal = document.getElementById('winner-modal');
            modal.classList.remove('hidden');
        }

        hideWinnerModal() {
            const modal = document.getElementById('winner-modal');
            modal.classList.add('hidden');
            this.winnerOverlayDismissed = true;
        }

        checkInitialWinner() {
            @if($game->ended && $game->winner())
                this.showWinner('{{ $game->winner()->id }}');
            @elseif($game->ended)
                this.showTie();
            @endif
        }

        init() {
            this.checkInitialWinner();
            this.channel.listen('BroadcastEvent', (data) => {
                this.handleEvent(data.event, data.gameState);
            });
            
            // Add event listener for view board button
            document.getElementById('view-board-btn').addEventListener('click', () => {
                this.hideWinnerModal();
            });
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (window.Echo) {
            window.game = new Game();
            window.game.init();
        }
    });
</script>

