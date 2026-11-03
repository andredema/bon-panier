<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/** Crée un compte de l'équipe en ligne de commande (premier gérant en production). */
class CreateUser extends Command
{
    protected $signature = 'users:create
        {--name= : Nom affiché}
        {--email= : Courriel de connexion}
        {--role= : gerant ou employe}';

    protected $description = 'Créer un compte de l’équipe (gérant ou employé)';

    public function handle(): int
    {
        $data = [
            'name' => $this->option('name') ?? text('Nom', required: true),
            'email' => $this->option('email') ?? text('Courriel', required: true),
            'role' => $this->option('role') ?? select('Rôle', [
                UserRole::Manager->value => 'Gérant',
                UserRole::Employee->value => 'Employé',
            ]),
            'password' => password('Mot de passe (10 caractères minimum)', required: true),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => ['required', Password::min(10)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create($data + ['is_active' => true]);
        $this->info("Compte créé : {$user->email} ({$user->role->label()}).");

        return self::SUCCESS;
    }
}
