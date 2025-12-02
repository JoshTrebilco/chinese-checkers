<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function index(Request $request)
    {
        // If user is already authenticated, redirect to game
        if (Auth::check()) {
            if ($request->has('game_id')) {
                return redirect()->route('games.show', ['game_id' => $request->input('game_id')]);
            }
            return redirect()->route('games.index');
        }

        return view('login', [
            'game_id' => $request->input('game_id'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // Find or create user
        $user = User::firstOrCreate(
            ['name' => $request->input('name')],
            [
                'email' => $request->input('name') . '@game.local', // Dummy email for now
                'password' => Hash::make('password'), // Dummy password
            ]
        );

        // Log the user in
        Auth::login($user);

        if ($request->has('game_id')) {
            return redirect()->route('games.show', ['game_id' => $request->input('game_id')]);
        }

        return redirect()->route('games.index');
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('games.index');
    }
}

