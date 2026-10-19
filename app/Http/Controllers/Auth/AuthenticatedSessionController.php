<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    // Mostra la vista di login
    public function create(): View
    {
        return view('auth.login');
    }

    // Gestisce la richiesta di autenticazione
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();
        
        $user = auth()->user();
        if ($user && $user->status !== 'active') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => 'Il tuo account è disattivato o sospeso.',
            ]);
        }

        try {
            if ($user) {
                app(\App\Services\AuditLogService::class)->log('login', $user, null, null, null, $user->id);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to log login: " . $e->getMessage());
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    // Distrugge la sessione di autenticazione (logout)
    public function destroy(Request $request): RedirectResponse
    {
        try {
            $user = auth()->user();
            if ($user) {
                app(\App\Services\AuditLogService::class)->log('logout', $user, null, null, null, $user->id);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to log logout: " . $e->getMessage());
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
