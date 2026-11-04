<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

/** Change le rôle d'un compte existant (ex. faire du premier gérant le super administrateur). */
class SetUserRole extends Command
{
    protected $signature = 'users:role {email : Courriel du compte} {role : admin, gerant, employe ou comptable}';

    protected $description = 'Changer le rôle d’un compte de l’équipe';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        $role = UserRole::tryFrom($this->argument('role'));

        if (! $user || ! $role) {
            $this->error(! $user ? 'Aucun compte avec ce courriel.' : 'Rôle inconnu : admin, gerant, employe ou comptable.');

            return self::FAILURE;
        }

        $user->update(['role' => $role]);
        $this->info("{$user->email} est maintenant : {$role->label()}.");

        return self::SUCCESS;
    }
}
