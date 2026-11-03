<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Correction de stock sur un lot : casse, perte, péremption, inventaire (jamais effacée)
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lot_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('qty_before', 12, 3);
            $table->decimal('qty_after', 12, 3);
            $table->decimal('delta', 12, 3);              // négatif = sortie
            $table->bigInteger('value_cents')->nullable(); // effet sur la valeur du stock, au coût du lot
            $table->string('reason', 20);                  // casse | perte | peremption | inventaire | autre
            $table->string('note', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
