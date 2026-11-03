<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('label', 60);
            $table->string('short_label', 20);
            // count (sac, unité, carton…), mass (kg, g) ou volume (L)
            $table->string('kind', 10);
            // Conversion entre unités d'une même nature : microgrammes de base par unité
            // (kg = 1 000 000, g = 1 000 ; L = 1 000 000). Nul pour les unités de compte.
            $table->unsignedBigInteger('base_factor')->nullable();
            $table->boolean('allows_decimals')->default(false);
            $table->decimal('default_step', 12, 3)->default(1);
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            // Unités livrées avec l'application : renommables, désactivables, jamais supprimées
            $table->boolean('is_system')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Unités de base du cahier des charges (configuration, pas des données de démonstration)
        $now = now();
        DB::table('units')->insert([
            ['code' => 'sac', 'label' => 'Sac', 'short_label' => 'sac', 'kind' => 'count', 'base_factor' => null, 'allows_decimals' => false, 'default_step' => 1, 'description' => 'Contenu en kg ou g (sac de 1 kg, 5 kg, 25 kg)', 'sort_order' => 1],
            ['code' => 'unite', 'label' => 'Unité', 'short_label' => 'unité', 'kind' => 'count', 'base_factor' => null, 'allows_decimals' => false, 'default_step' => 1, 'description' => 'À la pièce : boule, bouteille, carton, caisse', 'sort_order' => 2],
            ['code' => 'l', 'label' => 'Litre (L)', 'short_label' => 'L', 'kind' => 'volume', 'base_factor' => 1_000_000, 'allows_decimals' => true, 'default_step' => 1, 'description' => 'Huiles, boissons ; décimales permises', 'sort_order' => 3],
            ['code' => 'kg', 'label' => 'Kilogramme (kg)', 'short_label' => 'kg', 'kind' => 'mass', 'base_factor' => 1_000_000, 'allows_decimals' => true, 'default_step' => 0.5, 'description' => 'Vente au poids ; pas de 0,5 kg', 'sort_order' => 4],
            ['code' => 'g', 'label' => 'Gramme (g)', 'short_label' => 'g', 'kind' => 'mass', 'base_factor' => 1_000, 'allows_decimals' => false, 'default_step' => 100, 'description' => 'Sachets et vrac ; pas de 100 g', 'sort_order' => 5],
        ]);
        DB::table('units')->update(['is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
