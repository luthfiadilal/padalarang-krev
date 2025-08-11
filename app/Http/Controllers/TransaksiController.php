<?php

namespace App\Http\Controllers;

use Midtrans\Snap;
use App\Models\Cart;
use Inertia\Inertia;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use App\Models\TransaksiItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class TransaksiController extends Controller
{

    public function show($id)
    {
        Log::info('Memulai method show untuk transaksi ID: ' . $id);

        $mainTransaksi = Transaksi::with(['items.produk.penjual', 'items.penjual'])->findOrFail($id);

        if ($mainTransaksi->pembeli_id !== Auth::user()->pembeli->id) {
            Log::warning('Akses tidak diizinkan. Pembeli ID: ' . Auth::user()->pembeli->id . ', Transaksi milik Pembeli ID: ' . $mainTransaksi->pembeli_id);
            abort(403, 'Akses tidak diizinkan.');
        }

        if ($mainTransaksi->parent_transaction_id) {
            Log::info('Transaksi ID: ' . $id . ' adalah transaksi anak. Mencari transaksi lain dengan parent_transaction_id: ' . $mainTransaksi->parent_transaction_id);
            $transaksiGrup = Transaksi::with(['items.produk.penjual', 'items.penjual'])
                ->where('parent_transaction_id', $mainTransaksi->parent_transaction_id)
                ->get();
            // Cari transaksi induk, tambahkan pengecekan jika tidak ditemukan
            $parentTransaksi = Transaksi::find($mainTransaksi->parent_transaction_id);

        } else {
            Log::info('Transaksi ID: ' . $id . ' adalah transaksi induk. Mencari semua transaksi anak.');
            $transaksiGrup = Transaksi::with(['items.produk.penjual', 'items.penjual'])
                ->where(function ($q) use ($mainTransaksi) {
                    $q->where('id', $mainTransaksi->id)
                        ->orWhere('parent_transaction_id', $mainTransaksi->id);
                })
                ->get();
            $parentTransaksi = $mainTransaksi;
        }

        // Tambahkan pengecekan jika $parentTransaksi tidak ditemukan
        if (!$parentTransaksi) {
            Log::error('Transaksi induk tidak ditemukan untuk transaksi ID: ' . $id);
            // Anda bisa mengarahkan pengguna ke halaman lain atau menampilkan pesan error.
            // Misalnya, abort(404, 'Transaksi tidak ditemukan.');
            // Atau beri nilai default agar tidak error
            $parentTransaksi = $mainTransaksi;
        }

        Log::info('Jumlah transaksi dalam grup: ' . $transaksiGrup->count());

        $allGroupItems = collect();
        $totalHargaGabungan = 0;
        $totalOngkirGabungan = 0;
        // Akses properti dengan aman
        $snap_token = $parentTransaksi->snap_token ?? null;


        foreach ($transaksiGrup as $transaksi) {
            foreach ($transaksi->items as $item) {
                $allGroupItems->push($item);
            }
            $totalHargaGabungan += $transaksi->total_harga;
            $totalOngkirGabungan += $transaksi->harga_ongkir; // Tambahkan ongkir dari setiap transaksi anak
        }

        Log::info('Total harga gabungan: ' . $totalHargaGabungan);
        Log::info('Total ongkir gabungan: ' . $totalOngkirGabungan);

        $items = $allGroupItems->map(function ($item) {
            $harga = $item->produk->harga_diskon ?? $item->produk->harga ?? 0;
            $qty = $item->quantity ?? 0;
            return [
                'id' => $item->id,
                'produk' => $item->produk,
                'penjual' => $item->penjual,
                'qty' => $qty,
                'harga' => $harga,
                'harga_total' => $harga * $qty,
            ];
        });

        if (empty($snap_token) && $parentTransaksi->metode_pembayaran !== 'cod' && $parentTransaksi->status === 'pending') {
            Log::info('Snap token belum ada, status pending. Mencoba membuat Snap Token.');
            try {
                // Logika pembuatan token tetap sama
                $snap_token = $this->createMidtransTransactionGabungan($transaksiGrup);
                $parentTransaksi->refresh();
                Log::info('Snap token berhasil dibuat dan disimpan: ' . $snap_token);
            } catch (Exception $e) {
                Log::error('Gagal membuat Midtrans Snap Token:', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                session()->flash('error', 'Gagal membuat token pembayaran. Silakan coba lagi.');
            }
        }

        Log::info('Method show selesai. Mengirimkan data ke view.');
        return Inertia::render('Buyer/Transaksi/Show', [
            'transaksi' => [
                'id' => $parentTransaksi->id,
                'kode_transaksi' => $parentTransaksi->parent_transaction_id
                    ? 'GABUNGAN-' . ($parentTransaksi->parent_transaction_id ?? $parentTransaksi->id)
                    : $parentTransaksi->kode_transaksi,
                'total_harga' => $totalHargaGabungan,
                'status' => $parentTransaksi->status,
                'metode_pembayaran' => $parentTransaksi->metode_pembayaran,
                'nama_penerima' => $parentTransaksi->nama_penerima,
                'telepon_penerima' => $parentTransaksi->telepon_penerima,
                'alamat_lengkap' => $parentTransaksi->alamat_lengkap,
                'kelurahan' => $parentTransaksi->kelurahan,
                'kecamatan' => $parentTransaksi->kecamatan,
                'kota' => $parentTransaksi->kota,
                'kode_pos' => $parentTransaksi->kode_pos,
                'harga_ongkir' => $totalOngkirGabungan,
                'items' => $items,
                'snap_token' => $snap_token,
            ],
            'client_key' => config('services.midtrans.client_key'),
            'is_production' => config('services.midtrans.is_production')
        ]);
    }


    public function checkout(Request $request)
    {
        Log::info('Memulai method checkout. Data request:', $request->all());
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
            'metode_pembayaran' => 'required|in:cod,transfer',
        ]);

        $cartIds = $request->input('cart_ids');
        $pembeli = Auth::user()->pembeli;

        if (!$pembeli) {
            Log::error('Data pembeli tidak ditemukan untuk user ID: ' . Auth::user()->id);
            return back()->with('error', 'Data pembeli tidak ditemukan.');
        }

        $carts = Cart::whereIn('id', $cartIds)
            ->where('pembeli_id', $pembeli->id)
            ->with(['produk', 'penjual'])
            ->get();

        if ($carts->isEmpty()) {
            Log::warning('Tidak ada item yang valid untuk diproses. Cart IDs:', $cartIds);
            return back()->with('error', 'Tidak ada item yang valid untuk diproses.');
        }

        DB::beginTransaction();
        Log::info('DB Transaction dimulai.');

        try {
            $groupedCarts = $carts->groupBy('penjual_id');
            $transaksiIds = [];
            $parentTransactionId = 'GAB-' . now()->format('YmdHis') . '-' . uniqid();
            Log::info('Membuat parent transaction ID: ' . $parentTransactionId);

            foreach ($groupedCarts as $penjualId => $group) {
                $totalBelanja = $group->sum('harga_total');
                $ongkir = 0;
                Log::info('Memproses grup dari penjual ID: ' . $penjualId . '. Total belanja: ' . $totalBelanja);

                if ($request->jasa_pengiriman !== 'ambil_di_tempat') {
                    $penjual = $group->first()->penjual;
                    $ongkir = $this->calculateShippingCostByKecamatan($penjual->kecamatan, $request->kecamatan, $totalBelanja);
                    Log::info('Ongkir untuk penjual ' . $penjualId . ': ' . $ongkir);

                    if ($ongkir === -1) {
                        DB::rollBack();
                        Log::warning('Total belanja kurang dari Rp50.000, rollback transaksi.');
                        return back()->with('error', 'Total belanja minimal Rp50.000 untuk menggunakan jasa ojek.');
                    }
                }

                $status = ($request->metode_pembayaran === 'cod') ? 'menunggu diterima' : 'belum bayar';

                $transaksi = Transaksi::create([
                    'pembeli_id' => $pembeli->id,
                    'parent_transaction_id' => $parentTransactionId,
                    'kode_transaksi' => Transaksi::generateKode(),
                    'status' => $status,
                    'total_harga' => $totalBelanja + $ongkir,
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
                    'harga_ongkir' => $ongkir,
                ]);
                Log::info('Transaksi anak baru dibuat dengan ID: ' . $transaksi->id);

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

                $transaksiIds[] = $transaksi->id;
            }

            if ($request->metode_pembayaran !== 'cod') {
                $allTransactions = Transaksi::with(['items.produk'])
                    ->whereIn('id', $transaksiIds)
                    ->get();
                Log::info('Membuat Snap Token untuk pembayaran Midtrans.');
                $this->createMidtransTransactionGabungan($allTransactions);
            }

            Cart::whereIn('id', $cartIds)->delete();
            Log::info('Keranjang berhasil dikosongkan.');

            DB::commit();
            Log::info('DB Transaction berhasil di-commit.');

            return redirect()->route('transaksi.show', $transaksiIds[0])
                ->with('success', 'Checkout berhasil! Silakan lanjut ke pembayaran.');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Terjadi kesalahan saat memproses checkout:', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return back()->with('error', 'Terjadi kesalahan saat memproses transaksi. Silakan coba lagi.');
        }
    }

    /**
     * Buat transaksi Midtrans untuk transaksi gabungan.
     */
    protected function createMidtransTransactionGabungan($transactions)
    {
        Log::info('Memulai createMidtransTransactionGabungan. Jumlah transaksi: ' . $transactions->count());

        if (!($transactions instanceof \Illuminate\Support\Collection)) {
            $transactions = collect([$transactions]);
            Log::info('Mengubah transaksi menjadi collection.');
        }

        if ($transactions->isEmpty()) {
            Log::warning('Tidak ada transaksi yang diberikan untuk Midtrans.');
            return null;
        }

        // Ambil parent_transaction_id dari transaksi pertama. Ini akan digunakan sebagai order_id.
        $parentTransactionId = $transactions->first()->parent_transaction_id;
        if (empty($parentTransactionId)) {
             $parentTransactionId = $transactions->first()->id;
        }
        Log::info('Menggunakan parent_transaction_id sebagai order_id Midtrans: ' . $parentTransactionId);

        // Siapkan item_details untuk Midtrans
        $item_details = [];
        $gross_amount = 0;

        foreach ($transactions as $transaksi) {
            foreach ($transaksi->items as $item) {
                $harga = $item->harga_satuan ?? $item->produk->harga_diskon ?? $item->produk->harga ?? 0;
                $qty = $item->quantity ?? 1;

                $item_details[] = [
                    'id' => $item->id,
                    'price' => $harga,
                    'quantity' => $qty,
                    'name' => $item->produk->nama_produk ?? 'Produk Tanpa Nama',
                ];
                $gross_amount += ($harga * $qty);
            }

            if (!empty($transaksi->harga_ongkir) && $transaksi->harga_ongkir > 0) {
                $item_details[] = [
                    'id' => 'ongkir-' . $transaksi->id,
                    'price' => $transaksi->harga_ongkir,
                    'quantity' => 1,
                    'name' => 'Ongkos Kirim - ' . ($transaksi->jasa_pengiriman ?? 'Kurir'),
                ];
                $gross_amount += $transaksi->harga_ongkir;
            }
        }

        Log::info('Data item_details yang akan dikirim ke Midtrans:', $item_details);
        Log::info('Total gross_amount yang akan dikirim ke Midtrans: ' . $gross_amount);

        $params = [
            'transaction_details' => [
                'order_id' => $parentTransactionId,
                'gross_amount' => $gross_amount,
            ],
            'item_details' => $item_details,
            'customer_details' => [
                'first_name' => auth()->user()->name ?? 'Pembeli',
                'email' => auth()->user()->email ?? null,
                'phone' => $transactions->first()->telepon_penerima ?? null,
            ],
        ];
        Log::info('Parameter Midtrans yang akan dikirim:', $params);

        try {
            $snapToken = Snap::getSnapToken($params);
            Log::info('Snap Token berhasil didapat dari Midtrans: ' . $snapToken);

            Transaksi::whereIn('id', $transactions->pluck('id'))
                ->update(['snap_token' => $snapToken]);
            Log::info('Snap Token berhasil disimpan ke ' . $transactions->count() . ' transaksi.');

            return $snapToken;
        } catch (Exception $e) {
            Log::error('Gagal saat membuat Midtrans Snap Token:', ['error' => $e->getMessage(), 'params' => $params, 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
    }


    public function checkoutForm(Request $request)
    {
        // Validate the cart IDs
        $request->validate([
            'cart_ids' => 'present|array',
            'cart_ids.*' => 'exists:carts,id',
        ]);

        // Get the cart IDs from the request
        $cartIds = $request->cart_ids;

        // Get the carts with the given IDs
        $carts = Cart::with(['produk', 'penjual'])
            ->whereIn('id', $cartIds)
            ->where('pembeli_id', Auth::user()->pembeli->id)
            ->get();

        // Return the checkout form with the carts
        return inertia('Buyer/Checkout/Form', [
            'carts' => $carts,
        ]);
    }

    protected function calculateShippingCostByKecamatan(string $kecamatanPenjual, string $kecamatanPembeli, float $totalBelanja): int
    {
        // Peta harga statis berdasarkan jarak asumsi antar kecamatan
        // Ini adalah contoh, Anda bisa menyesuaikannya sesuai kebutuhan
        $hargaKecamatan = [
            'Lembang' => ['Lembang' => 10000, 'Parongpong' => 12000, 'Cisarua' => 15000, 'Ngamprah' => 17000],
            'Parongpong' => ['Lembang' => 12000, 'Parongpong' => 10000, 'Cisarua' => 11000, 'Ngamprah' => 13000],
            'Cisarua' => ['Lembang' => 15000, 'Parongpong' => 11000, 'Cisarua' => 10000, 'Ngamprah' => 12000],
            'Ngamprah' => ['Lembang' => 17000, 'Parongpong' => 13000, 'Cisarua' => 12000, 'Ngamprah' => 10000],
            'Padalarang' => ['Lembang' => 20000, 'Ngamprah' => 15000, 'Cipatat' => 12000],
            'Cipatat' => ['Padalarang' => 12000, 'Cipatat' => 10000, 'Cikalongwetan' => 15000],

        ];

        // Normalisasi nama kecamatan
        $kecamatanPenjual = ucfirst(strtolower($kecamatanPenjual));
        $kecamatanPembeli = ucfirst(strtolower($kecamatanPembeli));

        $ongkir = 0;

        // Aturan khusus untuk ojek
        if ($totalBelanja < 50000) {
            return -1; // Mengembalikan -1 sebagai indikasi error untuk front-end
        }

        // Cek apakah kecamatan penjual dan pembeli ada di peta harga
        if (isset($hargaKecamatan[$kecamatanPenjual][$kecamatanPembeli])) {
            $ongkir = $hargaKecamatan[$kecamatanPenjual][$kecamatanPembeli];
        } else {
            // Jika tidak ada di peta, gunakan harga default (misal, untuk kecamatan yang jauh)
            $ongkir = 25000;
        }

        return $ongkir;
    }

    // private function calculateShippingCost($distanceKm, $jasaPengiriman, $totalBelanja)
    // {
    //     $ongkir = 0;

    //     if ($jasaPengiriman === 'ojek' && $totalBelanja < 25000) {
    //         return -1; // Mengembalikan nilai -1 sebagai indikasi error
    //     }

    //     if ($distanceKm <= 3) {
    //         $ongkir = 7000;
    //     } elseif ($distanceKm <= 5) {
    //         $ongkir = 10000;
    //     } elseif ($distanceKm <= 8) {
    //         $ongkir = 13000;
    //     } elseif ($distanceKm <= 10) {
    //         $ongkir = 15000;
    //     } else if ($distanceKm <=20) {
    //         $ongkir = 20000;
    //     } else if ($distanceKm <= 40) {
    //         $ongkir = 38000;
    //     } else if ($distanceKm <= 70) {
    //         $ongkir = 50000;
    //     } else if ($distanceKm <= 100) {
    //         $ongkir = 70000;
    //     } else {
    //         $ongkir = 100000;
    //     }

    //     return $ongkir;
    // }

    public function updateStatus(Request $request, $transaksiId)
    {
        // Validasi status baru harus sesuai dengan nilai enum di database.
        $request->validate([
            'status' => 'required|in:belum bayar,menunggu diterima,telah diterima,cancel',
        ]);

        $penjualId = Auth::user()->penjual->id ?? null;

        if (!$penjualId) {
            abort(403, 'Hanya penjual yang dapat mengubah status transaksi.');
        }

        // Penjual tidak diperbolehkan untuk mengubah status menjadi 'telah diterima'.
        // Status ini seharusnya hanya bisa diubah oleh pembeli.
        if ($request->status === 'telah diterima') {
            abort(403, 'Anda tidak memiliki izin untuk menandai pesanan sebagai telah diterima.');
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


    public function toReceived($transaksiId)
    {
        $transaksi = Transaksi::findOrFail($transaksiId);
        $pembeliId = Auth::user()->pembeli->id ?? null;

        // Pastikan hanya pembeli yang punya transaksi ini yang bisa menandai diterima.
        if ($transaksi->pembeli_id !== $pembeliId) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah status transaksi ini.');
        }

        // Pastikan status saat ini adalah 'menunggu diterima' sebelum dapat diubah menjadi 'telah diterima'.
        if ($transaksi->status !== 'menunggu diterima') {
            return back()->with('error', 'Transaksi hanya bisa ditandai telah diterima jika statusnya "menunggu diterima".');
        }

        DB::beginTransaction();

        try {
            // Update status transaksi
            $transaksi->update([
                'status' => 'telah diterima'
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Transaksi berhasil ditandai sebagai telah diterima.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal mengubah status transaksi: ' . $e->getMessage());
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
        $menungguDiterima = $transaksis->where('status', 'menunggu diterima')->values();
        $telahDiterima = $transaksis->where('status', 'telah diterima')->values();
        $dibatalkan = $transaksis->where('status', 'cancel')->values();

        return inertia('Buyer/PaymentHistory/PaymentHistoryPage', [
            'belumBayar' => $belumBayar,
            'menungguDiterima' => $menungguDiterima,
            'telahDiterima' => $telahDiterima,
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
        $menungguDiterima = $transaksis->where('status', 'menunggu diterima')->values();
        $telahDiterima = $transaksis->where('status', 'telah diterima')->values();
        $dibatalkan = $transaksis->where('status', 'cancel')->values();

        return inertia('Seller/PaymentHistory/PaymentHistoryPage', [
            'belumBayar' => $belumBayar,
            'menungguDiterima' => $menungguDiterima,
            'telahDiterima' => $telahDiterima,
            'dibatalkan' => $dibatalkan,
            'user' => $user,
        ]);
    }

    function getCoordinates($kecamatan)
    {
        // $apiKey = env('ORS_API_KEY');
        $response = Http::get('https://api.openrouteservice.org/geocode/search', [
            'api_key' => "eyJvcmciOiI1YjNjZTM1OTc4NTExMTAwMDFjZjYyNDgiLCJpZCI6ImY3YzMzYjZiOTJiNDRhMjA4YzIyNTIyODM0OWNkMGRlIiwiaCI6Im11cm11cjY0In0=",
            'text' => $kecamatan,
            'size' => 1
        ]);

        Log::info('Geocode API response:', $response->json()); // log semua respons mentah

        if ($response->successful()) {
            $data = $response->json();
            if (!empty($data['features'][0]['geometry']['coordinates'])) {
                $coords = $data['features'][0]['geometry']['coordinates'];
                Log::info('Coordinates found:', $coords);
                return $coords; // [lon, lat]
            }
        }

        Log::warning("Coordinates not found for: {$kecamatan}");
        return null;
    }

    function getDistanceKm($coord1, $coord2)
    {
        // $apiKey = env('ORS_API_KEY');

        $body = [
            'locations' => [
                $coord1,
                $coord2,
            ],
            'metrics' => ['distance'],
        ];

        $response = Http::withHeaders([
            'Authorization' => "eyJvcmciOiI1YjNjZTM1OTc4NTExMTAwMDFjZjYyNDgiLCJpZCI6ImY3YzMzYjZiOTJiNDRhMjA4YzIyNTIyODM0OWNkMGRlIiwiaCI6Im11cm11cjY0In0=",
            'Content-Type'  => 'application/json',
        ])->post('https://api.openrouteservice.org/v2/matrix/driving-car', $body);

        Log::info('Distance API response:', $response->json()); // log hasil mentah

        if ($response->successful()) {
            $data = $response->json();
            Log::info('Matrix distances array:', $data['distances'] ?? []);
            $distanceMeters = $data['distances'][0][1] ?? null;

            if ($distanceMeters) {
                $distanceKm = $distanceMeters / 1000;
                Log::info("Distance in KM: {$distanceKm}");
                return $distanceKm;
            } else {
                Log::warning('Distance calculation returned null', [
                    'coord1' => $coord1,
                    'coord2' => $coord2,
                    'matrix_data' => $data
                ]);
            }
        }


        Log::warning('Failed to get distance.');
        return null;
    }

}
