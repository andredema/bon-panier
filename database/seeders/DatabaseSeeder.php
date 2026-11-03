<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Données de démonstration seulement : jamais en production.
     * En production, créer les comptes avec « php artisan users:create ».
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Les données de démonstration ne se chargent pas en production.');
        }

        // Comptes fictifs ; mot de passe de démonstration : « password »
        User::factory()->manager()->create([
            'name' => 'Gérant Démo',
            'email' => 'gerant@example.com',
        ]);

        User::factory()->create([
            'name' => 'Employé Démo',
            'email' => 'employe@example.com',
        ]);
    }
}
