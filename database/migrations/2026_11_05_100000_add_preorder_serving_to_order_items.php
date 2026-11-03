<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Entrée en stock qui a servi la précommande (nulle si servie à la main, sans arrivage)
            $table->foreignId('served_receipt_id')->nullable()->after('preorder_served_at')->constrained('stock_receipts')->nullOnDelete();
            // Courriel « précommande arrivée » envoyé
            $table->timestamp('preorder_notified_at')->nullable()->after('served_receipt_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('served_receipt_id');
            $table->dropColumn('preorder_notified_at');
        });
    }
};
