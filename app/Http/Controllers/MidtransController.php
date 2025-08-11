<?php

namespace App\Http\Controllers;

use App\Models\Transaksi;
use Illuminate\Http\Request;
use Midtrans\Config;
use Midtrans\Notification;
use Illuminate\Support\Facades\Log;

class MidtransController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('Menerima notifikasi dari Midtrans');

        Config::$isProduction = config('services.midtrans.is_production');
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isSanitized = true;
        Config::$is3ds = true;

        try {
            $notif = new Notification();
        } catch (\Exception $e) {
            Log::error('Gagal memproses notifikasi Midtrans: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal memproses notifikasi'], 500);
        }

        $transactionStatus = $notif->transaction_status;
        $paymentType = $notif->payment_type;
        $orderId = $notif->order_id;
        $fraudStatus = $notif->fraud_status;

        Log::info("Notifikasi: Order ID = {$orderId}, Status = {$transactionStatus}, Tipe Pembayaran = {$paymentType}");

        // Cek apakah orderId adalah id transaksi induk atau kode transaksi
        $parentTransaksi = Transaksi::where('id', $orderId)->first();
        if (!$parentTransaksi) {
            $parentTransaksi = Transaksi::where('kode_transaksi', $orderId)->first();
        }

        if (!$parentTransaksi) {
            Log::error('Transaksi induk tidak ditemukan untuk order_id: ' . $orderId);
            return response()->json(['message' => 'Transaksi tidak ditemukan'], 404);
        }

        // Ambil semua transaksi yang terkait, termasuk induk
        $transaksiGrup = Transaksi::where('id', $parentTransaksi->id)
                                  ->orWhere('parent_transaction_id', $parentTransaksi->id)
                                  ->get();

        Log::info('Jumlah transaksi dalam grup yang akan diupdate: ' . $transaksiGrup->count());

        $newStatus = '';
        $newMidtransStatus = '';
        $midtransResponse = json_encode($notif->getResponse());

        // Logika pembaruan status
        if ($transactionStatus == 'capture') {
            if ($fraudStatus == 'challenge') {
                $newStatus = 'pending';
                $newMidtransStatus = 'challenge';
            } else {
                $newStatus = 'sudah bayar';
                $newMidtransStatus = 'settlement';
            }
        } else if ($transactionStatus == 'settlement') {
            $newStatus = 'sudah bayar';
            $newMidtransStatus = 'settlement';
        } else if ($transactionStatus == 'pending') {
            $newStatus = 'belum bayar';
            $newMidtransStatus = 'pending';
        } else if ($transactionStatus == 'deny') {
            $newStatus = 'gagal';
            $newMidtransStatus = 'deny';
        } else if ($transactionStatus == 'expire') {
            $newStatus = 'expired';
            $newMidtransStatus = 'expire';
        } else if ($transactionStatus == 'cancel') {
            $newStatus = 'dibatalkan';
            $newMidtransStatus = 'cancel';
        }

        // Perbarui semua transaksi dalam grup
        if ($newStatus) {
            foreach ($transaksiGrup as $transaksi) {
                $transaksi->update([
                    'status' => $newStatus,
                    'midtrans_status' => $newMidtransStatus,
                    'midtrans_response' => $midtransResponse,
                ]);
            }
            Log::info('Status transaksi grup berhasil diperbarui menjadi: ' . $newStatus);
        } else {
             Log::warning('Status notifikasi tidak dikenali: ' . $transactionStatus);
        }

        return response()->json(['message' => 'OK']);
    }
}
