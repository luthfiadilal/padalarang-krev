<?php

namespace App\Models;

use App\Models\Cart;
use App\Models\Pembeli;
use App\Models\TransaksiItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Transaksi extends Model
{
    protected $table = 'transaksis';

    protected $fillable = [
        'pembeli_id',
        'kode_transaksi',
        'status',
        'total_harga',
        'catatan',
        'nama_penerima',
        'telepon_penerima',
        'alamat_lengkap',
        'kelurahan',
        'kecamatan',
        'kota',
        'kode_pos',
        'jasa_pengiriman',
        'metode_pembayaran',
        'snap_token',
        'midtrans_status',
        'midtrans_response',
        'harga_ongkir'
    ];

    protected $casts = [
        'total_harga' => 'decimal:2',
        'midtrans_response' => 'array', // jika JSON disimpan sebagai string
    ];

    // Relasi ke Pembeli
    public function pembeli(): BelongsTo
    {
        return $this->belongsTo(Pembeli::class, 'pembeli_id');
    }

    // Relasi ke Cart
    public function carts(): BelongsToMany
    {
        return $this->belongsToMany(Cart::class, 'cart_transaksi');
    }

    public function items()
    {
        return $this->hasMany(TransaksiItem::class);
    }

    // Generate Kode Transaksi
    public static function generateKode(): string
    {
        return 'TRX-' . strtoupper(uniqid());
    }

    // Format Total
    public function getTotalFormattedAttribute(): string
    {
        return 'Rp ' . number_format($this->total_harga, 0, ',', '.');
    }

    // Apakah pembayaran menggunakan COD?
public function isCOD(): bool
{
    return strtolower($this->metode_pembayaran) === 'cod';
}

// Apakah pembayaran menggunakan Midtrans?
public function isMidtrans(): bool
{
    return in_array(strtolower($this->metode_pembayaran), ['transfer', 'qris', 'virtual_account']);
}

// Apakah status Midtrans masih pending?
public function isPendingMidtrans(): bool
{
    return $this->isMidtrans() && strtolower($this->midtrans_status) === 'pending';
}

// Apakah pembayaran Midtrans sudah sukses?
public function isPaid(): bool
{
    return $this->isMidtrans() && strtolower($this->midtrans_status) === 'settlement';
}

// === Status Transaksi ===
    public function isBelumBayar(): bool
    {
        return $this->status === 'belum bayar';
    }

    public function isSudahBayar(): bool
    {
        return $this->status === 'sudah bayar';
    }

}
