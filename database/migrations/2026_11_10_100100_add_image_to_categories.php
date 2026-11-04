<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Image facultative de la carte de catégorie ; à défaut, l'icône et la palette
        Schema::table('categories', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('palette');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
