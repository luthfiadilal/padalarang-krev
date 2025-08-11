<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use App\Models\TransaksiItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Midtrans\Snap;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TransaksiController extends Controller
{

    public function show($id)
    {
        $transaksi = Transaksi::with(['items.produk', 'items.penjual'])->findOrFail($id);

        if ($transaksi->pembeli_id !== Auth::user()->pembeli->id) {
            abort(403, 'Akses tidak diizinkan.');
        }

        $items = $transaksi->items->map(function ($item) {
            $harga = $item->produk->harga ?? 0;
            $qty = $item->quantity ?? 0; // Menggunakan 'quantity' sesuai model TransaksiItem

            return [
                'id' => $item->id,
                'produk' => $item->produk,
                'penjual' => $item->penjual,
                'qty' => $qty,
                'harga' => $harga,
                'harga_total' => $harga * $qty,
            ];
        });

        // Mengambil client key dari .env file melalui file konfigurasi
        $client_key = config('services.midtrans.client_key');
        // Mengambil status produksi dari .env file
        $is_production = config('services.midtrans.is_production');

        // Cek apakah snap_token sudah ada, jika belum dan metodenya bukan COD, buat snap_token baru
        if (empty($transaksi->snap_token) && $transaksi->metode_pembayaran !== 'cod' && $transaksi->status === 'pending') {
            try {
                // Pastikan metode createMidtransTransaction() tersedia di kelas ini
                $this->createMidtransTransaction($transaksi);
                // Muat ulang transaksi untuk mendapatkan snap_token yang baru
                $transaksi->refresh();
            } catch (\Exception $e) {
                \Log::error('Gagal membuat Midtrans Snap Token: ' . $e->getMessage());
                // Kirimkan pesan error ke front-end
                session()->flash('error', 'Gagal membuat token pembayaran. Silakan coba lagi.');
            }
        }

        return inertia('Buyer/Transaksi/Show', [
            'transaksi' => [
                ...$transaksi->toArray(),
                'items' => $items,
            ],
            'snap_token' => $transaksi->snap_token,
            'client_key' => $client_key,
            'is_production' => $is_production
        ]);
    }


    public function checkout(Request $request)
    {
        // Validasi input dari pengguna
        // Catatan: 'harga_ongkir' dihapus dari validasi karena akan dihitung di server
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
        $carts = Cart::whereIn('id', $cartIds)
            ->where('pembeli_id', Auth::user()->pembeli->id)
            ->with(['produk', 'penjual']) // Eager load produk dan penjual untuk performa
            ->get();

        // Jika tidak ada item keranjang yang valid, kembalikan dengan error
        if ($carts->isEmpty()) {
            return back()->with('error', 'Tidak ada item yang valid untuk diproses.');
        }

        // Mulai database transaction untuk memastikan semua operasi berhasil atau gagal bersamaan
        DB::beginTransaction();

        try {
            $groupedCarts = $carts->groupBy('penjual_id');
            $transaksiIds = [];

            // Proses setiap kelompok keranjang per penjual
            foreach ($groupedCarts as $penjualId => $group) {
                $totalBelanja = $group->sum('harga_total');
                $ongkir = 0;

                // Hitung ongkir berdasarkan jasa pengiriman
                if ($request->jasa_pengiriman !== 'ambil_di_tempat') {
                    // Ambil koordinat penjual dari data pertama di group
                    $penjual = $group->first()->penjual;
                    $penjualCoord = $this->getCoordinates($penjual->kecamatan);
                    $pembeliCoord = $this->getCoordinates($request->kecamatan);

                    // Hitung jarak dan tentukan ongkir
                    $distanceKm = $this->getDistanceKm($penjualCoord, $pembeliCoord);
                    $ongkir = $this->calculateShippingCost($distanceKm, $request->jasa_pengiriman, $totalBelanja);

                    // Jika ongkir bernilai -1, berarti ada error atau syarat tidak terpenuhi
                    if ($ongkir === -1) {
                        DB::rollBack();
                        return back()->with('error', 'Total belanja minimal Rp25.000 untuk menggunakan jasa ojek.');
                    }
                }

                // Hitung total harga transaksi
                $totalHarga = $totalBelanja + $ongkir;

                // Perbarui status sesuai metode pembayaran
                // Untuk COD, status awal lebih baik 'menunggu konfirmasi' daripada 'sudah bayar'
                $status = $request->metode_pembayaran === 'cod' ? 'menunggu konfirmasi' : 'belum bayar';

                // Buat entri Transaksi baru
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
                    'harga_ongkir' => $ongkir,
                ]);

                // Buat item transaksi dari setiap item keranjang
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

                // Panggil Midtrans jika metode pembayaran bukan COD
                if ($request->metode_pembayaran !== 'cod') {
                    $this->createMidtransTransaction($transaksi);
                }

                $transaksiIds[] = $transaksi->id;
            }

            // Hapus item keranjang yang sudah diproses
            Cart::whereIn('id', $cartIds)->delete();

            // Komit database transaction
            DB::commit();

            // Redirect ke halaman yang sesuai
            if (count($transaksiIds) === 1) {
                return redirect()->route('transaksi.show', $transaksiIds[0])
                    ->with('success', 'Checkout berhasil! Silakan lanjut ke pembayaran.');
            }

            return redirect()->route('transaksi.index')
                ->with('success', 'Checkout berhasil untuk beberapa penjual! Silakan lanjut ke pembayaran.');

        } catch (\Exception $e) {
            // Jika ada error, rollback database transaction
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan saat memproses transaksi. ' . $e->getMessage());
        }
    }

    public function createMidtransTransaction(Transaksi $transaksi)
    {
        Log::info('MIDTRANS: Memulai pembuatan transaksi Midtrans.', ['transaksi_id' => $transaksi->id]);

        $pembeli = $transaksi->pembeli;
        $user = $pembeli->user;

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

        $grossAmount = array_sum(array_map(fn($item) => $item['price'] * $item['quantity'], $items));

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
                'unit' => 'hours',
                'duration' => 24,
            ],
        ];
        Log::info('MIDTRANS: Payload siap dikirim.', ['payload' => $payload]);

        if (empty($items)) {
            Log::error('MIDTRANS: Item transaksi kosong. Tidak dapat melanjutkan.');
            throw new \Exception('Item transaksi kosong');
        }

        try {
            Log::info('MIDTRANS: Mencoba membuat Snap Token...');
            $snapToken = Snap::getSnapToken($payload);
            Log::info('MIDTRANS: Snap Token berhasil dibuat.', ['token' => $snapToken]);

            $transaksi->update(['snap_token' => $snapToken]);
            Log::info('MIDTRANS: Snap Token berhasil disimpan ke database.', ['transaksi_id' => $transaksi->id]);
        } catch (\Exception $e) {
            Log::error('MIDTRANS: Gagal membuat Snap Token.', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);
            throw new \Exception('Gagal membuat Snap Token: ' . $e->getMessage());
        }

        return $snapToken;
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


    private function calculateShippingCost($distanceKm, $jasaPengiriman, $totalBelanja)
    {
        $ongkir = 0;

        if ($jasaPengiriman === 'ojek' && $totalBelanja < 25000) {
            return -1; // Mengembalikan nilai -1 sebagai indikasi error
        }

        if ($distanceKm <= 3) {
            $ongkir = 7000;
        } elseif ($distanceKm <= 5) {
            $ongkir = 10000;
        } elseif ($distanceKm <= 8) {
            $ongkir = 13000;
        } elseif ($distanceKm <= 10) {
            $ongkir = 15000;
        } else {
            $ongkir = 20000;
        }

        return $ongkir;
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
