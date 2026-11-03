<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Loi 25 : un client effacé garde sa fiche (les factures y sont liées) mais plus aucune coordonnée
        Schema::table('customers', function (Blueprint $table) {
            $table->string('phone', 40)->nullable()->change();
            $table->timestamp('erased_at')->nullable()->after('consent_at');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('erased_at');
        });
    }
};
