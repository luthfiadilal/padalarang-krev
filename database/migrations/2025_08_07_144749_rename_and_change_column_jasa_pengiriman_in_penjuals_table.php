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
            $table->integer('is_active')->default(0)->after('foto_profil');
            $table->dropColumn('jasa_pengiriman');


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('penjuals', function (Blueprint $table) {
            $table->json('jasa_pengiriman')->nullable()->after('foto_profil');
            $table->dropColumn('is_active');
        });
    }
};
