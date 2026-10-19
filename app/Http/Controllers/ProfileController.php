<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    // Mostra il form del profilo utente
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'lastActiveAdministrator' => $request->user()->isLastActiveAdministrator(),
            'retainedHistory' => $request->user()->hasRetainedHistory(),
        ]);
    }

    // Aggiorna le informazioni del profilo utente
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    // Elimina l'account utente (Richiede conferma password)
    public function destroy(Request $request): RedirectResponse
    {
        $this->authorize('system.admin');
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if ($user->isLastActiveAdministrator()) {
            return back()->with('error', 'Non puoi eliminare l’unico amministratore attivo. Crea o attiva prima un altro amministratore.');
        }

        try {
            $user->deleteUnusedAccount(fn () => Auth::logout());
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return back()->with('error', $exception->validator->errors()->first());
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
