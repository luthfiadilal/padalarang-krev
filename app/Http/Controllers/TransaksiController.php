<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use App\Models\TransaksiItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Midtrans\Snap;

class TransaksiController extends Controller
{

    public function show($id)
    {
        $transaksi = Transaksi::with(['items.produk', 'items.penjual'])->findOrFail($id);

        // Pastikan hanya pembeli yang punya transaksi ini yang bisa melihat
        if ($transaksi->pembeli_id !== Auth::user()->pembeli->id) {
            abort(403, 'Akses tidak diizinkan.');
        }

        $items = $transaksi->items->map(function ($item) {
            $harga = $item->produk->harga ?? 0;
            $qty = $item->qty ?? 0;

            return [
                'id' => $item->id,
                'produk' => $item->produk,
                'penjual' => $item->penjual,
                'qty' => $qty,
                'harga' => $harga,
                'harga_total' => $harga * $qty,
            ];
        });

        return inertia('Buyer/Transaksi/Show', [
            'transaksi' => [
                    ...$transaksi->toArray(),   // konversi ke array agar bisa diubah
                    'items' => $items,          // ganti items dengan hasil map (ada harga_total)
                ],
            'snap_token' => $transaksi->snap_token,
        ]);
    }

    public function checkout(Request $request)
    {
        $request->validate([
            'cart_ids' => 'required|array',
            'cart_ids.*' => 'exists:carts,id',
            'catatan' => 'nullable|string',
            'nama_penerima' => 'required|string',
            'telepon_penerima' => 'required|string',
            'alamat_lengkap' => 'required|string',
            'kelurahan' => 'required|string',
            'kecamatan' => 'required|string',
            'kota' => 'required|string',
            'kode_pos' => 'required|string',
            'jasa_pengiriman' => 'required|string',
            'harga_ongkir' => 'required|numeric|min:0',
            'metode_pembayaran' => 'required|in:cod,transfer',
        ]);

        $cartIds = $request->input('cart_ids');
        $carts = Cart::whereIn('id', $cartIds)
            ->where('pembeli_id', Auth::user()->pembeli->id)
            ->get();

        if ($carts->isEmpty()) {
            return back()->with('error', 'Tidak ada item yang valid untuk diproses.');
        }

        DB::beginTransaction();

        try {
            $groupedCarts = $carts->groupBy('penjual_id');
            $transaksiIds = [];

            foreach ($groupedCarts as $penjualId => $group) {
                $totalHarga = $group->sum('harga_total') + $request->harga_ongkir;

                $status = $request->metode_pembayaran === 'cod' ? 'sudah bayar' : 'belum bayar';

                $transaksi = Transaksi::create([
                    'pembeli_id' => Auth::user()->pembeli->id,
                    'kode_transaksi' => Transaksi::generateKode(),
                    'status' => $status,
                    'total_harga' => $totalHarga,
                    'catatan' => $request->catatan,
                    'nama_penerima' => $request->nama_penerima,
                    'telepon_penerima' => $request->telepon_penerima,
                    'alamat_lengkap' => $request->alamat_lengkap,
                    'kelurahan' => $request->kelurahan,
                    'kecamatan' => $request->kecamatan,
                    'kota' => $request->kota,
                    'kode_pos' => $request->kode_pos,
                    'jasa_pengiriman' => $request->jasa_pengiriman,
                    'metode_pembayaran' => $request->metode_pembayaran,
                    'harga_ongkir' => $request->harga_ongkir,
                ]);

                foreach ($group as $cart) {
                    TransaksiItem::create([
                        'transaksi_id' => $transaksi->id,
                        'produk_id' => $cart->produk_id,
                        'penjual_id' => $cart->penjual_id,
                        'quantity' => $cart->quantity,
                        'harga_satuan' => $cart->harga_satuan,
                        'harga_total' => $cart->harga_total,
                    ]);
                }

                // Hanya buat transaksi Midtrans jika bukan COD
                if ($request->metode_pembayaran !== 'cod' && $transaksi->isMidtrans()) {
                    $this->createMidtransTransaction($transaksi);
                }

                $transaksiIds[] = $transaksi->id;
            }

            Cart::whereIn('id', $cartIds)->delete();
            DB::commit();

            if (count($transaksiIds) === 1) {
                return redirect()->route('transaksi.show', $transaksiIds[0])
                    ->with('success', 'Checkout berhasil! Silakan lanjut ke pembayaran.');
            }

            return redirect()->route('transaksi.index')
                ->with('success', 'Checkout berhasil untuk beberapa penjual! Silakan lanjut ke pembayaran.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan saat memproses transaksi. ' . $e->getMessage());
        }
    }



    public function createMidtransTransaction(Transaksi $transaksi)
    {
        // Ambil user + pembeli
        $pembeli = $transaksi->pembeli;
        $user = $pembeli->user;

        // Ambil item-item transaksi
        $items = $transaksi->items->map(function ($item) {
            return [
                'id' => $item->produk_id,
                'price' => (int) $item->harga_satuan,
                'quantity' => (int) $item->quantity,
                'name' => $item->produk->nama,
            ];
        })->toArray();

        if ($transaksi->harga_ongkir > 0) {
            $items[] = [
                'id' => 'ONGKIR',
                'price' => (int) $transaksi->harga_ongkir,
                'quantity' => 1,
                'name' => 'Ongkos Kirim',
            ];
        }

        // Total harga
        $grossAmount = $transaksi->total_harga;

        // Payload Midtrans
        $payload = [
            'transaction_details' => [
                'order_id' => $transaksi->kode_transaksi,
                'gross_amount' => $grossAmount,
            ],
            'item_details' => $items,
            'customer_details' => [
                'first_name' => $user->name,
                'email' => $user->email,
                'phone' => $pembeli->no_hp,
                'shipping_address' => [
                    'first_name' => $user->name,
                    'phone' => $pembeli->no_hp,
                    'address' => $pembeli->alamat,
                    'city' => $pembeli->kota ?? 'Kota Tidak Diketahui',
                    'postal_code' => $pembeli->kode_pos ?? '00000',
                    'country_code' => 'IDN',
                ],
            ],
            'expiry' => [
                'start_time' => now()->format('Y-m-d H:i:s O'),
                'unit' => 'hours', // gunakan 'hours' bukan 'minutes'
                'duration' => 24,  // artinya 24 jam
            ],
        ];
        \Log::info('MIDTRANS PAYLOAD', ['payload' => $payload]);


        if (empty($items)) {
            throw new \Exception('Item transaksi kosong');
        }

        // Buat Snap Token dari Midtrans
        $snapToken = Snap::getSnapToken($payload);

        // Simpan snap token ke transaksi
        $transaksi->update(['snap_token' => $snapToken]);

        \Log::info('MIDTRANS SNAP TOKEN', ['token' => $snapToken]);


        return $snapToken;
    }

    public function checkoutForm(Request $request)
    {
        $request->validate([
            'cart_ids' => 'present|array',
            'cart_ids.*' => 'exists:carts,id',
        ]);

        $cartIds = $request->cart_ids;

        $carts = Cart::with(['produk', 'penjual'])
            ->whereIn('id', $cartIds)
            ->where('pembeli_id', Auth::user()->pembeli->id)
            ->get();

        return inertia('Buyer/Checkout/Form', [
            'carts' => $carts,
        ]);
    }


    public function updateStatus(Request $request, $transaksiId)
    {
        $request->validate([
            'status' => 'required|in:belum bayar,sudah bayar,dibatalkan',
        ]);

        $penjualId = Auth::user()->penjual->id ?? null;

        if (!$penjualId) {
            abort(403, 'Hanya penjual yang dapat mengubah status transaksi.');
        }

        $transaksi = Transaksi::with('items')->findOrFail($transaksiId);

        // Pastikan penjual memiliki produk dalam transaksi ini
        $penjualPunyaItem = $transaksi->items->contains(fn ($item) => $item->penjual_id === $penjualId);

        if (!$penjualPunyaItem) {
            abort(403, 'Anda tidak memiliki produk dalam transaksi ini.');
        }

        // Update status transaksi
        $transaksi->status = $request->status;
        $transaksi->save();

        return back()->with('success', 'Status transaksi berhasil diperbarui.');
    }

    public function toCancel($transaksiId)
    {
        $transaksi = Transaksi::findOrFail($transaksiId);
        $pembeliId = Auth::user()->pembeli->id ?? null;

        // Pastikan hanya pembeli yang punya transaksi ini yang bisa membatalkan
        if ($transaksi->pembeli_id !== $pembeliId) {
            abort(403, 'Anda tidak memiliki akses untuk membatalkan transaksi ini.');
        }

        // Pastikan transaksi belum dibayar
        if ($transaksi->status !== 'belum bayar') {
            return back()->with('error', 'Transaksi hanya bisa dibatalkan jika statusnya "belum bayar".');
        }


        DB::beginTransaction();

        try {
            // Update status transaksi
            $transaksi->update([
                'status' => 'dibatalkan'
            ]);

            // Optional: Kembalikan stok produk jika diperlukan
            foreach ($transaksi->items as $item) {
                $produk = $item->produk;
                $produk->increment('stok', $item->quantity);
            }

            DB::commit();

            return redirect()->back()->with('success', 'Transaksi berhasil dibatalkan.');
            \Log::debug("Mencoba membatalkan transaksi ID: {$transaksiId}");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal membatalkan transaksi: ' . $e->getMessage());
        }
    }

    public function history()
    {
        $pembeliId = Auth::user()->pembeli->id;
        $user = Auth::user()->load(['pembeli' => function ($query) {
            $query->withCount('carts');
        }]);

        // Ambil semua transaksi user dengan data lengkap
        $transaksis = Transaksi::with(['items.produk.kategori', 'items.produk.tipeProduk', 'items.penjual'])
            ->where('pembeli_id', $pembeliId)
            ->get();

        // Kelompokkan per status untuk tab
        $belumBayar = $transaksis->where('status', 'belum bayar')->values();
        $sudahBayar = $transaksis->where('status', 'sudah bayar')->values();
        $dibatalkan = $transaksis->where('status', 'dibatalkan')->values();

        return inertia('Buyer/PaymentHistory/PaymentHistoryPage', [
            'belumBayar' => $belumBayar,
            'sudahBayar' => $sudahBayar,
            'dibatalkan' => $dibatalkan,
            'user' => $user,
        ]);
    }

    public function historyWithPenjual()
    {
        $penjualId = Auth::user()->penjual->id;
        $user = Auth::user()->load(['penjual']);

        // Ambil semua transaksi yang memiliki item dari penjual yang sedang login
        $transaksis = Transaksi::with(['items.produk.kategori', 'items.produk.tipeProduk', 'items.penjual', 'pembeli.user'])
            ->whereHas('items', function ($query) use ($penjualId) {
                $query->where('penjual_id', $penjualId);
            })
            ->get();

        // Filter items dalam setiap transaksi untuk hanya menampilkan item dari penjual yang login
        $transaksis = $transaksis->map(function ($transaksi) use ($penjualId) {
            $transaksi->items = $transaksi->items->where('penjual_id', $penjualId);
            return $transaksi;
        });

        // Kelompokkan per status untuk tab
        $belumBayar = $transaksis->where('status', 'belum bayar')->values();
        $sudahBayar = $transaksis->where('status', 'sudah bayar')->values();
        $dibatalkan = $transaksis->where('status', 'dibatalkan')->values();

        return inertia('Seller/PaymentHistory/PaymentHistoryPage', [
            'belumBayar' => $belumBayar,
            'sudahBayar' => $sudahBayar,
            'dibatalkan' => $dibatalkan,
            'user' => $user,
        ]);
    }



}
