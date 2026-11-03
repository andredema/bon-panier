<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Règle 6 : chaque ligne vendue garde le lot sorti (FEFO, à la remise)
        Schema::create('order_item_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lot_id')->constrained()->restrictOnDelete();
            $table->decimal('qty', 12, 3); // en unité de stock
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            // en_ligne (page de commande) | comptoir (saisie par l'équipe, au comptoir ou au téléphone)
            $table->string('source', 12)->default('en_ligne')->after('channel');
            $table->foreignId('created_by')->nullable()->after('source')->constrained('users')->nullOnDelete();
            $table->timestamp('handed_over_at')->nullable()->after('cancel_reason');
        });

        // Un client du comptoir peut ne pas donner de courriel (plusieurs NULL restent permis par l'index unique)
        Schema::table('customers', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['source', 'handed_over_at']);
        });
        Schema::dropIfExists('order_item_lots');
    }
};
