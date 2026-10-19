<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Illuminate\Support\Facades\Gate;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(function ($request, $next) {
                Gate::authorize('system.admin');
                return $next($request);
            }),
        ];
    }

    public function index(): View
    {
        $users = User::orderBy('name')->get();
        return view('users.index', compact('users'));
    }

    public function create(): View
    {
        return view('users.create', ['roles' => UserRole::cases()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'max:255', 'unique:users,email'],
            'role'                  => ['required', Rule::enum(UserRole::class)],
            'primary_specialization'=> ['nullable', 'string', 'max:255'],
            'phone'                 => ['nullable', 'string', 'max:50'],
            'password'              => ['required', 'confirmed', Password::min(8)],
        ]);

        User::create([
            'name'                   => $data['name'],
            'email'                  => $data['email'],
            'role'                   => $data['role'],
            'primary_specialization' => $data['primary_specialization'] ?? null,
            'phone'                  => $data['phone'] ?? null,
            'password'               => Hash::make($data['password']),
            'status'                 => 'active',
            'password_changed_at'    => null,
        ]);

        return redirect()->route('users.index')
            ->with('success', 'Utente creato correttamente.');
    }

    public function edit(User $user): View
    {
        return view('users.edit', [
            'user'  => $user,
            'roles' => UserRole::cases(),
            'lastActiveAdministrator' => $user->isLastActiveAdministrator(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name'                   => ['required', 'string', 'max:255'],
            'email'                  => ['required', 'email', Rule::unique('users')->ignore($user)],
            'role'                   => ['required', Rule::enum(UserRole::class)],
            'primary_specialization' => ['nullable', 'string', 'max:255'],
            'phone'                  => ['nullable', 'string', 'max:50'],
            'status'                 => ['required', 'in:active,inactive'],
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($user, $data) {
            /** Lock administrators together so simultaneous demotions cannot remove the last one. */
            User::where('role', UserRole::Admin)->orWhere('id', $user->id)->orderBy('id')->lockForUpdate()->get();
            $user->refresh();
            if ($user->isLastActiveAdministrator() && ($data['role'] !== UserRole::Admin->value || $data['status'] !== 'active')) {
                throw \Illuminate\Validation\ValidationException::withMessages(['role' => 'Deve rimanere almeno un amministratore attivo.']);
            }
            $user->update($data);
        });

        return redirect()->route('users.index')
            ->with('success', 'Utente aggiornato correttamente.');
    }

    // Genera una password temporanea visualizzata una sola volta
    public function resetPassword(User $user): RedirectResponse
    {
        $temporary = Str::password(12);

        $user->update([
            'password'            => Hash::make($temporary),
            'password_changed_at' => null,
        ]);

        // Passa la password temporanea alla vista tramite sessione flash
        return redirect()->route('users.index')
            ->with('temp_password', [
                'user'     => $user->name,
                'password' => $temporary,
            ]);
    }

    // Cambia lo stato attivo/inattivo dell'account
    public function toggleStatus(User $user): RedirectResponse
    {
        // Impedisce l'autodisattivazione del proprio account
        abort_if($user->id === auth()->id(), 403, 'Non puoi disattivare il tuo account.');

        \Illuminate\Support\Facades\DB::transaction(function () use ($user) {
            User::where('role', UserRole::Admin)->orWhere('id', $user->id)->orderBy('id')->lockForUpdate()->get();
            $user->refresh();
            if ($user->isLastActiveAdministrator()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['user' => 'Deve rimanere almeno un amministratore attivo.']);
            }
            $user->update(['status' => $user->status === 'active' ? 'inactive' : 'active']);
        });

        $label = $user->status === 'active' ? 'riattivato' : 'disattivato';

        return back()->with('success', "Utente {$label} correttamente.");
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->id === auth()->id(), 403, 'Non puoi eliminare il tuo account.');

        if ($user->isLastActiveAdministrator()) {
            return back()->with('error', 'Deve rimanere almeno un amministratore attivo.');
        }

        try {
            $user->deleteUnusedAccount();
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return back()->with('error', $exception->validator->errors()->first());
        }

        return redirect()->route('users.index')
            ->with('success', 'Utente eliminato correttamente.');
    }
}
