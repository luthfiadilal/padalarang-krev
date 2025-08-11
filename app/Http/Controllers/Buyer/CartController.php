<?php

namespace App\Http\Controllers\Buyer;

use App\Models\Cart;
use Inertia\Inertia;
use App\Models\Produk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{

     /**
     * Tampilkan semua item di keranjang pembeli.
     */
    public function index()
    {
        $pembeli = Auth::user()->pembeli;
        $user = Auth::user()->load(['pembeli' => function($query) {
            $query->withCount('carts');
        }]);

        if (!$pembeli) {
            return response()->json(['message' => 'Pembeli tidak ditemukan'], 404);
        }

        $carts = $pembeli->carts()->with(['produk', 'penjual'])->get();

        return Inertia::render('Buyer/Cart', [
            'user' => Auth::user()->load(['pembeli' => function($query) {
                $query->withCount('carts');
            }]),
            'cartCount' => $user->pembeli->carts_count ?? 0,
            'carts' => $carts
        ]);
    }


    /**
     * Tambah produk ke keranjang.
     */
    public function store(Request $request)
    {
        \Log::info('STORE CART HIT', $request->all());
        $request->validate([
            'produk_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $pembeli = Auth::user()->pembeli;

        if (!$pembeli) {
            return response()->json(['message' => 'Pembeli tidak ditemukan'], 404);
        }

        $produk = Produk::findOrFail($request->produk_id);

        $hargaSatuan = $produk->harga_diskon ?? $produk->harga;
        $quantity = $request->quantity;
        $totalHarga = Cart::calculateTotal($hargaSatuan, $quantity);

        // Cek apakah produk sudah ada di keranjang
        $existing = Cart::where('pembeli_id', $pembeli->id)
                        ->where('produk_id', $produk->id)
                        ->first();

        if ($existing) {
            // Update quantity dan total
            $existing->quantity += $quantity;
            $existing->harga_total = Cart::calculateTotal($hargaSatuan, $existing->quantity);
            $existing->save();

            return response()->json(['message' => 'Keranjang diperbarui', 'cart' => $existing]);
        }

        // Tambahkan item baru ke keranjang
        $cart = Cart::create([
            'pembeli_id'   => $pembeli->id,
            'produk_id'    => $produk->id,
            'penjual_id'   => $produk->penjual_id,
            'quantity'     => $quantity,
            'harga_satuan' => $hargaSatuan,
            'harga_total'  => $totalHarga,
        ]);

        //
        return redirect()->route('marketplace-index')->with('success', 'Produk berhasil ditambahkan ke keranjang');
    }

    public function storeDirectCheckout(Request $request)
    {
        Log::info('STORE DIRECT CHECKOUT HIT', $request->all());

        if (!Auth::check()) {
            return response()->json(['message' => 'Anda harus login untuk melanjutkan.'], 401);
        }

        try {
            $request->validate([
                'produk_id' => 'required|exists:products,id',
                'quantity' => 'required|integer|min:1',
            ]);

            // Mengambil objek user yang terautentikasi dan mengecek relasi 'pembeli'
            $user = Auth::user();
            if (!$user || !$user->pembeli) {
                return response()->json(['message' => 'Pembeli tidak ditemukan'], 404);
            }

            $pembeli = $user->pembeli;
            $produk = Produk::findOrFail($request->produk_id);

            $hargaSatuan = $produk->harga_diskon ?? $produk->harga;
            $quantity = $request->quantity;
            $totalHarga = Cart::calculateTotal($hargaSatuan, $quantity);

            $existing = Cart::where('pembeli_id', $pembeli->id)
                            ->where('produk_id', $produk->id)
                            ->first();

            if ($existing) {
                $existing->quantity += $quantity;
                $existing->harga_total = Cart::calculateTotal($hargaSatuan, $existing->quantity);
                $existing->save();

                return response()->json(['message' => 'Keranjang diperbarui.', 'cart' => $existing], 200);
            }

            $cart = Cart::create([
                'pembeli_id'   => $pembeli->id,
                'produk_id'    => $produk->id,
                'penjual_id'   => $produk->penjual_id,
                'quantity'     => $quantity,
                'harga_satuan' => $hargaSatuan,
                'harga_total'  => $totalHarga,
            ]);

            return response()->json(['message' => 'Berhasil menambahkan ke keranjang.', 'cart' => $cart], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Menangkap secara spesifik jika produk tidak ditemukan
            Log::error('Produk tidak ditemukan saat direct checkout:', ['produk_id' => $request->produk_id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Produk tidak ditemukan.'], 404);
        } catch (\Exception $e) {
            // Menangkap error lainnya
            Log::error('Error in storeDirectCheckout:', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Terjadi kesalahan server. Mohon coba lagi.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update quantity item di keranjang.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = Cart::findOrFail($id);



        $cart->quantity = $request->quantity;
        $cart->harga_total = Cart::calculateTotal($cart->harga_satuan, $cart->quantity);
        $cart->save();

        return redirect()->route('cart.index')->with('success', 'Keranjang berhasil diperbarui');
    }

    /**
     * Hapus item dari keranjang.
     */
    public function destroy($id)
    {
        $cart = Cart::where('pembeli_id', Auth::user()->pembeli->id)->findOrFail($id);

        // $this->authorize('delete', $cart); // Optional jika pakai policy

        $cart->delete();

        return redirect()->route('cart.index')->with('success', 'Produk berhasil dihapus dari keranjang');
    }

    public function count(Request $request)
    {
        $user = Auth::user();
        $count = $user->pembeli->carts()->count();

        return response()->json([
            'count' => $count
        ]);
    }
}
