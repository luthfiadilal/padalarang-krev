<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaksi;
use Illuminate\Support\Facades\Log;
use Midtrans\Notification;

class MidtransController extends Controller
{
    public function handleNotification(Request $request)
    {
        Log::info('NOTIFIKASI MASUK', ['payload' => $request->all()]);
        // Set konfigurasi Midtrans
        \Midtrans\Config::$serverKey = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = config('midtrans.is_production');
        \Midtrans\Config::$isSanitized = true;
        \Midtrans\Config::$is3ds = true;

        // Ambil notifikasi
        $notification = new Notification();

        $transactionStatus = $notification->transaction_status;
        $paymentType = $notification->payment_type;
        $orderId = $notification->order_id;
        $fraudStatus = $notification->fraud_status;

        Log::info('Midtrans Notification Received', [
            'order_id' => $orderId,
            'transaction_status' => $transactionStatus,
            'payment_type' => $paymentType,
            'fraud_status' => $fraudStatus,
        ]);

        // Ambil transaksi dari DB
        $transaksi = Transaksi::where('kode_transaksi', $orderId)->first();

        if (!$transaksi) {
            Log::warning('Transaksi tidak ditemukan untuk order_id: ' . $orderId);
            return response()->json(['message' => 'Transaksi tidak ditemukan'], 404);
        }

        // Handle status
        switch ($transactionStatus) {
            case 'capture':
                // Langsung set status ke 'sudah bayar', tidak perlu cek credit_card
                $transaksi->status = 'sudah bayar';
                break;
            case 'settlement':
                $transaksi->status = 'sudah bayar';
                break;
            case 'pending':
                $transaksi->status = 'belum bayar';
                break;
            case 'deny':
            case 'cancel':
                $transaksi->status = 'dibatalkan';
                break;
            case 'expire':
                $transaksi->status = 'kadaluarsa';
                break;
            default:
                Log::warning("Status transaksi tidak dikenali: $transactionStatus");
                break;
        }


        $transaksi->save();

        return response()->json(['message' => 'Notifikasi diproses'], 200);
    }
}
