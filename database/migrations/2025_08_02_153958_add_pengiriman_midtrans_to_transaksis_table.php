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
        Schema::table('transaksis', function (Blueprint $table) {

            // Deskripsi atau catatan tambahan
            $table->text('catatan')->nullable()->after('total_harga');


            // Alamat pengiriman
            $table->string('nama_penerima')->nullable()->after('catatan');
            $table->string('telepon_penerima')->nullable()->after('nama_penerima');
            $table->string('alamat_lengkap')->nullable()->after('telepon_penerima');
            $table->string('kelurahan')->nullable()->after('alamat_lengkap');
            $table->string('kecamatan')->nullable()->after('kelurahan');
            $table->string('kota')->nullable()->after('kecamatan');
            $table->string('kode_pos')->nullable()->after('kota');

            // Pengiriman dan pembayaran
            $table->string('jasa_pengiriman')->nullable()->after('kode_pos');
            $table->enum('metode_pembayaran', ['cod', 'transfer'])->nullable()->after('jasa_pengiriman');

            // Midtrans
            $table->string('snap_token')->nullable()->after('metode_pembayaran');
            $table->enum('midtrans_status', ['pending', 'settlement', 'deny', 'expire', 'cancel'])->nullable()->after('snap_token');
            $table->text('midtrans_response')->nullable()->after('midtrans_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaksis', function (Blueprint $table) {
            $table->dropColumn([
                'catatan','nama_penerima', 'telepon_penerima', 'alamat_lengkap',
                'kelurahan', 'kecamatan', 'kota', 'kode_pos',
                'jasa_pengiriman', 'metode_pembayaran',
                'snap_token', 'midtrans_status', 'midtrans_response'
            ]);
        });
    }
};
