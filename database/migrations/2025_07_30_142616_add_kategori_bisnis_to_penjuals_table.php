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
            $table->string('kategori_bisnis')->nullable()->after('jasa_pengiriman');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('penjuals', function (Blueprint $table) {
            $table->dropColumn('kategori_bisnis');
        });
    }
};
