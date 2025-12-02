@php
    use Illuminate\Support\Str;
    $channel = Str::after(config('app.url'), 'https://').'.'.'game.'.$game->id;
    $board = $game->board();
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
            <x-board :board="$board" :game="$game" :channel="$channel" :auth_player_id="$auth_player_id" />
        @else
            <div class="bg-slate-900/50 backdrop-blur-sm rounded-2xl p-8 border border-red-800/50 shadow-xl">
                <p class="text-amber-300">Board is being created...</p>
            </div>
        @endif
        <x-panel :game="$game" :auth_player_id="$auth_player_id" :channel="$channel" />
    </div>
</x-layout>

<script>
    class Game {
        constructor() {
            this.players = {!! json_encode($game->players()->map(fn($p) => ['id' => (string)$p->id, 'name' => $p->name, 'color' => $p->color])) !!};
            this.authPlayerId = '{{ $auth_player_id ?? 'null' }}';
            this.activePlayerId = '{{ $game->activePlayer()?->id ?? 'null' }}';
            this.channel = window.Echo.channel(@json($channel));
        }

        handleEvent(event, gameState) {
            // Setup events should refresh the page
            if (event && typeof event === 'string' && event.startsWith('App\\Events\\Setup')) {
                window.location.reload();
            } else if (event && typeof event === 'object' && event.type && event.type.startsWith('App\\Events\\Setup')) {
                window.location.reload();
            }
        }

        init() {
            this.channel.listen('BroadcastEvent', (data) => {
                this.handleEvent(data.event, data.gameState);
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

