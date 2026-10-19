<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class FirstAccessController extends Controller
{

    public function show(Request $request): View|RedirectResponse
    {
        // Reindirizza alla dashboard se la password è già stata impostata
        if ($request->user()->password_changed_at !== null) {
            return redirect()->route('dashboard');
        }

        return view('auth.first-access');
    }


    public function update(Request $request): RedirectResponse
    {
        // Reindirizza alla dashboard se la password è già stata impostata
        if ($request->user()->password_changed_at !== null) {
            return redirect()->route('dashboard');
        }

        // Valida la nuova password secondo le regole di sicurezza predefinite
        $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();

        $user->update([
            'password' => Hash::make($request->password),
            'password_changed_at' => now(),
        ]);

        // Rigenera la sessione per prevenire attacchi di session fixation
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Password aggiornata con successo! Benvenuto.');
    }
}
