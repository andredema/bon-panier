<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 90)->unique();
            // Icône parmi celles des maquettes (feuille, sac, poisson, bouteille, piment, plantain)
            $table->string('icon', 20)->default('sac');
            // Palette prédéfinie (fond + encre) : contraste 4,5:1 garanti
            $table->string('palette', 20)->default('osier');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
