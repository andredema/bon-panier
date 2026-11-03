<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_formats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 10); // detail | gros
            // « Vendu en » : sac, unité, L, kg, g… (sert aussi au filtre de la page de commande)
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('label', 120);
            // Vente au poids : la quantité commandée est dans l'unité de vente (ex. 1,5 kg)
            // et le prix s'entend par unité de vente.
            $table->boolean('is_by_weight')->default(false);
            // Ce que retire du stock une quantité de 1, dans l'unité de stock du produit
            $table->decimal('content_qty', 12, 3);
            // Poids ou volume net d'une quantité de 1, pour le prix comparatif au kg ou au L
            // quand l'unité de stock est une unité de compte (ex. boule de 500 g).
            $table->decimal('net_qty', 12, 3)->nullable();
            $table->foreignId('net_unit_id')->nullable()->constrained('units')->restrictOnDelete();
            $table->unsignedBigInteger('price_cents');
            $table->decimal('min_qty', 12, 3)->default(1);
            $table->decimal('step', 12, 3)->default(1);
            $table->boolean('is_visible')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            // Les lignes de commande gardent la référence du format : on archive, on n'efface pas
            $table->softDeletes();

            $table->index(['product_id', 'channel', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_formats');
    }
};
