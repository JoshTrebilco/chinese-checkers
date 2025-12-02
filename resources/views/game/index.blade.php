<x-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <!-- Hero Section -->
        <div class="text-center mb-16">
            <h1 class="text-6xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-red-700 to-amber-600 mb-4">
                Chinese Checkers
            </h1>
            <p class="text-xl text-amber-200">
                Journey across the board and reach the opposite realm! 🏮
            </p>
        </div>

        <!-- Game Options Cards -->
        <div class="grid md:grid-cols-2 gap-8 max-w-4xl mx-auto">
            <!-- Join Game Card -->
            <div class="bg-slate-900 rounded-2xl shadow-xl transform transition duration-500 hover:scale-105 border border-red-800/50">
                <div class="p-8">
                    <div class="flex items-center justify-center w-16 h-16 bg-red-900 rounded-full mb-4">
                        <svg class="w-8 h-8 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-amber-300 mb-4">Enter the Arena</h2>
                    <p class="text-amber-200 mb-6">Have an invitation? Enter the code to join your fellow players!</p>

                    <form id="joinGameForm" class="space-y-4">
                        <input
                            type="text"
                            id="gameId"
                            name="game_id"
                            class="w-full px-4 py-3 rounded-lg border-2 border-red-800 bg-slate-800 text-amber-100 placeholder-amber-500 focus:border-red-600 focus:ring-0 focus:outline-none"
                            placeholder="Enter game code..."
                            required
                        />
                        <button
                            type="submit"
                            id="joinButton"
                            class="w-full bg-gradient-to-r from-red-600 to-amber-500 text-white rounded-lg px-4 py-3 font-semibold transform transition hover:translate-y-[-2px] disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Join Game
                        </button>
                    </form>

                    <script>
                        document.getElementById('joinGameForm').addEventListener('submit', function(e) {
                            e.preventDefault();
                            
                            const gameId = document.getElementById('gameId').value.trim();
                            const button = document.getElementById('joinButton');
                            
                            if (gameId === '') {
                                alert('Please enter a game code');
                                return;
                            }
                            
                            // Disable button to prevent double submission
                            button.disabled = true;
                            button.textContent = 'Joining...';
                            
                            // Redirect to the game
                            window.location.href = `./games/${gameId}`;
                        });

                        // Enable/disable button based on input
                        document.getElementById('gameId').addEventListener('input', function() {
                            const button = document.getElementById('joinButton');
                            const gameId = this.value.trim();
                            
                            button.disabled = gameId === '';
                        });
                    </script>
                </div>
            </div>

            <!-- Create Game Card -->
            <div class="bg-slate-900 rounded-2xl shadow-xl transform transition duration-500 hover:scale-105 border border-red-800/50">
                <div class="p-8 h-full flex flex-col">
                    <div class="flex items-center justify-center w-16 h-16 bg-amber-900 rounded-full mb-4">
                        <svg class="w-8 h-8 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-amber-300 mb-4">Begin New Journey</h2>
                    <p class="text-amber-200 mb-6">Become the master! Create a new match and invite others to join.</p>

                    <form action="{{ route('games.store') }}" method="post" class="mt-auto">
                        @csrf
                        <button type="submit"
                            class="w-full bg-gradient-to-r from-red-600 to-amber-500 text-white rounded-lg px-4 py-3 font-semibold transform transition hover:translate-y-[-2px]">
                            Create New Game
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Auth Section -->
        <div class="mt-12 text-center">
            @auth
                <div class="inline-flex items-center space-x-2 bg-slate-900 rounded-full px-6 py-3 shadow-lg">
                    <span class="text-amber-200">Master {{ auth()->user()->name }}</span>
                    <form action="{{ route('logout.destroy') }}" method="post" class="inline">
                        @csrf
                        <button type="submit"
                            class="text-red-500 hover:text-red-600 font-semibold">
                            Logout
                        </button>
                    </form>
                </div>
            @else
                <a href="{{ route('login.index') }}"
                    class="inline-flex items-center space-x-2 bg-slate-900 rounded-full px-6 py-3 shadow-lg text-amber-200 hover:shadow-xl transition duration-300">
                    <span>Enter to Preserve Your Journey</span>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            @endauth
        </div>

        <!-- Fun Footer -->
        <div class="mt-16 text-center text-amber-400">
            <p class="text-sm">🏮 Master the path of strategy! 🏮</p>
        </div>
    </div>
</x-layout>

