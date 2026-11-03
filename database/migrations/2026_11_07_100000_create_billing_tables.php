<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Compteurs des documents numérotés sans trou (F-AAAA-NNNN, NC-AAAA-NNNN), un par type et par année
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->unique(['type', 'year']);
        });

        // Facture : jamais modifiée après émission (règle 5) ; toutes les données sont figées
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->dateTime('issued_at');
            $table->date('due_on')->nullable();
            // Copies figées : client, commerce, numéros de taxes, taux
            $table->json('customer_snapshot');
            $table->json('shop_snapshot');
            $table->unsignedInteger('tps_rate');
            $table->unsignedInteger('tvq_rate');
            $table->string('fulfilment', 12);
            $table->dateTime('slot_start')->nullable();
            $table->dateTime('slot_end')->nullable();
            $table->string('payment_method', 12);
            $table->unsignedBigInteger('subtotal_cents');
            $table->unsignedBigInteger('taxable_cents');
            $table->unsignedBigInteger('delivery_fee_cents')->default(0);
            $table->boolean('delivery_taxable')->default(false);
            $table->unsignedBigInteger('tax_tps_cents');
            $table->unsignedBigInteger('tax_tvq_cents');
            $table->unsignedBigInteger('total_cents');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();

            $table->index('issued_at');
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name', 120);
            $table->string('format_label', 120);
            $table->string('channel', 10);
            $table->boolean('is_frozen')->default(false);
            $table->decimal('qty', 12, 3);
            $table->string('qty_unit', 20)->nullable();
            $table->unsignedBigInteger('unit_price_cents');
            $table->unsignedBigInteger('line_total_cents');
            $table->boolean('is_taxable')->default(false);
            $table->string('lot_numbers', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
        });

        // Note de crédit : seule façon de corriger une facture émise
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->dateTime('issued_at');
            $table->string('reason', 255);
            $table->unsignedBigInteger('delivery_cents')->default(0); // part des frais de livraison créditée
            $table->unsignedBigInteger('subtotal_cents');
            $table->unsignedBigInteger('taxable_cents');
            $table->unsignedBigInteger('tax_tps_cents');
            $table->unsignedBigInteger('tax_tvq_cents');
            $table->unsignedBigInteger('total_cents');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('credit_note_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_line_id')->constrained()->restrictOnDelete();
            $table->string('label', 255);
            $table->unsignedBigInteger('amount_cents');
            $table->boolean('is_taxable')->default(false);
        });

        // Paiements : rattachés à la commande (un virement Interac peut précéder la facture)
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 12); // interac | comptant | debit | carte
            $table->unsignedBigInteger('amount_cents');
            $table->dateTime('paid_at');
            $table->string('reference', 120)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['method', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('credit_note_lines');
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('document_sequences');
    }
};
