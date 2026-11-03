<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('brand', 80)->nullable();
            $table->string('barcode', 40)->nullable()->unique();
            $table->string('photo_path')->nullable();
            // Un seul stock par produit, compté dans cette unité
            $table->foreignId('stock_unit_id')->constrained('units')->restrictOnDelete();
            $table->boolean('is_frozen')->default(false);
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->boolean('allow_preorder')->default(true);
            $table->decimal('alert_threshold', 12, 3)->default(0);
            // Rang de popularité (tri « Populaires ») ; calculé à partir des ventes plus tard
            $table->unsignedInteger('popularity')->default(0);
            $table->timestamps();
            // Un produit vendu n'est jamais effacé : il est archivé
            $table->softDeletes();

            $table->index(['is_visible', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
