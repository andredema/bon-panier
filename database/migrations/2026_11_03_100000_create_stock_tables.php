<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('contact', 120)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            // Délai habituel entre la commande et la livraison (sert à prévoir les arrivages)
            $table->unsignedSmallInteger('usual_delay_days')->nullable();
            $table->string('notes', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Bon de réception : en-tête d'une entrée en stock
        Schema::create('stock_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->date('received_on');
            $table->string('delivery_note_no', 60)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Choix faits à la validation (le service des précommandes arrive à l'étape 5)
            $table->boolean('notify_preorders')->default(true);
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index('received_on');
        });

        // Un lot par produit reçu : traçabilité (n° de lot, date limite, fournisseur, coût)
        Schema::create('lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_receipt_id')->constrained()->restrictOnDelete();
            // Format reçu : un format de vente du produit, ou nul (unité de stock, autre conditionnement)
            $table->foreignId('sale_format_id')->nullable()->constrained()->nullOnDelete();
            $table->string('received_label', 120);
            $table->decimal('received_qty', 12, 3);
            // Unités de stock par format reçu (carton de 50 boules = 50)
            $table->decimal('content_qty', 12, 3);
            $table->decimal('converted_qty', 12, 3);
            $table->decimal('remaining_qty', 12, 3);
            $table->string('lot_number', 60);
            $table->date('best_before')->nullable();
            // Coût saisi par format reçu, et coût total du lot (cents)
            $table->unsignedBigInteger('unit_cost_cents')->nullable();
            $table->unsignedBigInteger('cost_cents')->nullable();
            $table->timestamps();

            // Ordre de sortie FEFO : date limite, puis ancienneté
            $table->index(['product_id', 'best_before', 'id']);
        });

        Schema::create('expected_arrivals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->date('expected_on');
            // Quantité attendue, dans l'unité de stock du produit (facultative)
            $table->decimal('qty', 12, 3)->nullable();
            $table->string('notes', 255)->nullable();
            $table->string('status', 12)->default('open'); // open | received | cancelled
            $table->foreignId('stock_receipt_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'expected_on']);
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expected_arrivals');
        Schema::dropIfExists('lots');
        Schema::dropIfExists('stock_receipts');
        Schema::dropIfExists('suppliers');
    }
};
