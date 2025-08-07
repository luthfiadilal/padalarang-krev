<?php

namespace App\Models;

use App\Models\Penjual;
use Illuminate\Database\Eloquent\Model;

class BuktiPembayaran extends Model
{
    protected $fillable = [
        'penjual_id',
        'bukti_pembayaran',
    ];

    public function penjual()
    {
        return $this->belongsTo(Penjual::class);
    }
}
