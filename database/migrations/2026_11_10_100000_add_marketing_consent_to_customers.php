<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Infolettre : consentement exprès (case non cochée par défaut) et preuve du désabonnement
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('marketing_consent_at')->nullable()->after('consent_at');
            $table->timestamp('marketing_unsubscribed_at')->nullable()->after('marketing_consent_at');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['marketing_consent_at', 'marketing_unsubscribed_at']);
        });
    }
};
