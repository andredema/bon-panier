<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Comptes de l'équipe. Le gérant gère les employés et les comptables ;
 * seul le super administrateur gère les gérants et les super administrateurs.
 */
class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.users.form', [
            'user' => new User(['role' => UserRole::Employee, 'is_active' => true]),
            'roles' => $request->user()->role->assignable(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            ...$this->rules($request->user()),
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)],
        ]);

        User::create($data + ['is_active' => true]);

        return redirect()->route('admin.users.index')->with('status', __('app.users.created'));
    }

    public function edit(Request $request, User $user): View
    {
        $this->authorizeTarget($request->user(), $user);

        return view('admin.users.form', ['user' => $user, 'roles' => $this->rolesFor($request->user(), $user)]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $this->authorizeTarget($actor, $user);
        $self = $user->is($actor);

        $data = $request->validate([
            ...$this->rules($actor, $self ? $user->role : null),
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['nullable', 'confirmed', Password::min(10)],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        // Personne ne change son propre rôle ni ne se désactive : il reste toujours un responsable actif.
        if ($self && ($data['role'] !== $user->role->value || ! $data['is_active'])) {
            return back()->withInput()->withErrors(['role' => __('app.users.self_demote')]);
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('admin.users.index')->with('status', __('app.users.updated'));
    }

    private function authorizeTarget(User $actor, User $target): void
    {
        abort_unless($target->is($actor) || $actor->canManage($target), 403, __('app.users.not_manageable'));
    }

    /** Rôles proposés : ceux que l'on peut attribuer ; pour son propre compte, son rôle actuel. */
    private function rolesFor(User $actor, User $target): array
    {
        return $target->is($actor) ? [$actor->role] : $actor->role->assignable();
    }

    /** @return array<string, mixed> */
    private function rules(User $actor, ?UserRole $own = null): array
    {
        $allowed = $own ? [$own] : $actor->role->assignable();

        return [
            'name' => ['required', 'string', 'max:120'],
            'role' => ['required', Rule::in(array_map(fn (UserRole $r) => $r->value, $allowed))],
        ];
    }
}
