<?php

namespace App\Http\Controllers;

use App\Models\Transaksi;
use Illuminate\Http\Request;
use Midtrans\Config;
use Midtrans\Notification;

class MidtransController extends Controller
{
    public function handle(Request $request)
    {
        // Set konfigurasi Midtrans
        Config::$isProduction = config('services.midtrans.is_production');
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isSanitized = true;
        Config::$is3ds = true;

        $notif = new Notification();

        $transaction = $notif->transaction_status;
        $type = $notif->payment_type;
        $orderId = $notif->order_id;
        $fraud = $notif->fraud_status;

        // Cari transaksi berdasarkan order_id
        $transaksi = Transaksi::where('kode_transaksi', $orderId)->first();

        if (!$transaksi) {
            return response()->json(['message' => 'Transaksi tidak ditemukan'], 404);
        }

        // Periksa status pembayaran
        if ($transaction == 'capture') {
            if ($type == 'credit_card') {
                if ($fraud == 'challenge') {
                    $transaksi->update([
                        'midtrans_status' => 'challenge',
                        'midtrans_response' => $notif->getResponse()
                    ]);
                } else {
                    $transaksi->update([
                        'status' => 'sudah bayar',
                        'midtrans_status' => 'settlement',
                        'midtrans_response' => $notif->getResponse()
                    ]);
                }
            }
        } else if ($transaction == 'settlement') {
            $transaksi->update([
                'status' => 'sudah bayar',
                'midtrans_status' => 'settlement',
                'midtrans_response' => $notif->getResponse()
            ]);
        } else if ($transaction == 'pending') {
            $transaksi->update([
                'midtrans_status' => 'pending',
                'midtrans_response' => $notif->getResponse()
            ]);
        } else if ($transaction == 'deny') {
            $transaksi->update([
                'midtrans_status' => 'deny',
                'midtrans_response' => $notif->getResponse()
            ]);
        } else if ($transaction == 'expire') {
            $transaksi->update([
                'midtrans_status' => 'expire',
                'midtrans_response' => $notif->getResponse()
            ]);
        } else if ($transaction == 'cancel') {
            $transaksi->update([
                'midtrans_status' => 'cancel',
                'midtrans_response' => $notif->getResponse()
            ]);
        }

        return response()->json(['message' => 'OK']);
    }
}
