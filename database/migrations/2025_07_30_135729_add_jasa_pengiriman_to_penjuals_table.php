<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('penjuals', function (Blueprint $table) {
            $table->json('jasa_pengiriman')->nullable()->after('foto_profil');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('penjuals', function (Blueprint $table) {
            $table->dropColumn('jasa_pengiriman');
        });
    }
};
