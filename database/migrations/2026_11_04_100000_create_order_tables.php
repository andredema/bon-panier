<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Compte client facultatif en v1 : le client est reconnu par son courriel
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('phone', 40);
            $table->string('email')->unique();
            $table->string('address', 255)->nullable();
            $table->string('type', 12)->default('particulier'); // particulier | gros
            $table->boolean('approved_wholesale')->default(false);
            // Loi 25 : date du dernier consentement explicite
            $table->timestamp('consent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->nullable()->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('channel', 10);       // detail | gros (gros dès qu'une ligne est en gros)
            $table->string('status', 20)->default('recue');
            $table->string('fulfilment', 12);    // ramassage | livraison
            $table->dateTime('slot_start');
            $table->dateTime('slot_end');
            $table->string('delivery_address', 255)->nullable();
            $table->string('payment_method', 12); // interac | remise
            $table->unsignedBigInteger('subtotal_cents');
            $table->unsignedBigInteger('taxable_cents');
            $table->unsignedBigInteger('delivery_fee_cents')->default(0);
            $table->unsignedBigInteger('tax_tps_cents');
            $table->unsignedBigInteger('tax_tvq_cents');
            $table->unsignedBigInteger('total_cents');
            $table->boolean('has_preorder')->default(false);
            $table->string('notes', 500)->nullable();
            $table->timestamp('consent_at');
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['status', 'slot_start']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_format_id')->constrained()->restrictOnDelete();
            // Copie figée au moment de la commande (le catalogue peut changer ensuite)
            $table->string('product_name', 120);
            $table->string('format_label', 120);
            $table->string('channel', 10);
            $table->boolean('is_by_weight')->default(false);
            $table->string('qty_unit', 20)->nullable(); // « kg » pour la vente au poids
            $table->decimal('qty', 12, 3);
            // Quantité retirée du stock, dans l'unité de stock du produit (sert aux réservations)
            $table->decimal('stock_qty', 12, 3);
            $table->unsignedBigInteger('unit_price_cents');
            $table->unsignedBigInteger('line_total_cents');
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_preorder')->default(false);
            $table->date('expected_on')->nullable();      // date d'arrivage annoncée au client
            $table->timestamp('preorder_served_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'is_preorder']);
        });

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from', 20)->nullable();
            $table->string('to', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('customers');
    }
};
