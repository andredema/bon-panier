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

/** Comptes de l'équipe ; réservé au gérant. */
class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', ['user' => new User(['role' => UserRole::Employee, 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            ...$this->rules(),
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)],
        ]);

        User::create($data + ['is_active' => true]);

        return redirect()->route('admin.users.index')->with('status', __('app.users.created'));
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', ['user' => $user]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            ...$this->rules(),
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['nullable', 'confirmed', Password::min(10)],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        // Le gérant ne peut pas se retirer lui-même l'accès : il faut toujours un gérant actif.
        if ($user->is($request->user()) && ($data['role'] !== UserRole::Manager->value || ! $data['is_active'])) {
            return back()->withInput()->withErrors(['role' => __('app.users.self_demote')]);
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('admin.users.index')->with('status', __('app.users.updated'));
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'role' => ['required', Rule::enum(UserRole::class)],
        ];
    }
}
