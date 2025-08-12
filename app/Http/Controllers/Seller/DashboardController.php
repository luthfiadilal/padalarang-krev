<?php

namespace App\Http\Controllers\Seller;

use Inertia\Inertia;
use App\Models\Produk;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use App\Models\TransaksiItem;
use Illuminate\Support\Carbon;
use App\Jobs\ExportTransaksiJob;
use Illuminate\Support\Collection;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DashboardController extends Controller
{


    public function __invoke()
    {
        $user = Auth::user();
        $penjualId = optional($user->penjual)->id;

        // Ambil total produk
        $totalProduk = Produk::where('penjual_id', $penjualId)->count();

        // Ambil produk per kategori
        $produkPerKategori = Produk::where('penjual_id', $penjualId)
            ->with('kategori')
            ->get()
            ->groupBy('kategori.kategori')
            ->map(fn($group) => $group->count());

        // Ambil semua item transaksi untuk penjual ini
        $transaksiItems = TransaksiItem::where('penjual_id', $penjualId)
            ->with(['transaksi', 'produk.kategori'])
            ->get();

        // 📊 Pengelompokan dan penghitungan status transaksi
        $transaksiGroup = $transaksiItems->groupBy(fn($item) => $item->transaksi->status);

        // Menghitung jumlah untuk setiap status baru
        $jumlahBelumDibayar = $transaksiGroup->get('belum bayar')?->count() ?? 0;
        $jumlahMenungguDiterima = $transaksiGroup->get('menunggu diterima')?->count() ?? 0;
        $jumlahTelahDiterima = $transaksiGroup->get('telah diterima')?->count() ?? 0;
        $jumlahDibatalkan = $transaksiGroup->get('dibatalkan')?->count() ?? 0;

        // 📅 Statistik penjualan per bulan, hanya untuk transaksi 'telah diterima'
        $penjualanPerBulan = $transaksiItems
            ->filter(fn($item) => $item->transaksi->status === 'telah diterima')
            ->groupBy(fn($item) => Carbon::parse($item->transaksi->created_at)->format('Y-m'))
            ->map(fn($items, $key) => [
                'bulan' => Carbon::parse($key . '-01')->isoFormat('MMMM Y'),
                'jumlah' => $items->count(),
            ])
            ->values();

        // 🧩 Statistik kategori produk yang terbeli, hanya untuk transaksi 'telah diterima'
        $kategoriTerbeli = $transaksiItems
            ->filter(fn($item) => $item->transaksi->status === 'telah diterima')
            ->groupBy(fn($item) => optional($item->produk->kategori)->kategori ?? 'Tidak Diketahui')
            ->map(fn($items) => $items->count());

        return Inertia::render('Seller/Dashboard', [
            'user' => Auth::user()->load('penjual'),
            'totalProduk' => $totalProduk,
            'produkPerKategori' => $produkPerKategori,
            'statusTransaksi' => [
                // Mengirimkan semua status transaksi yang telah diperbarui
                'belum_dibayar' => $jumlahBelumDibayar,
                'menunggu_diterima' => $jumlahMenungguDiterima,
                'telah_diterima' => $jumlahTelahDiterima,
                'dibatalkan' => $jumlahDibatalkan,
            ],
            'penjualanPerBulan' => $penjualanPerBulan,
            'kategoriTerbeli' => $kategoriTerbeli->map(function ($jumlah, $kategori) {
                return [
                    'kategori' => $kategori,
                    'jumlah' => $jumlah,
                ];
            })->values(),
        ]);
    }

    public function export(Request $request)
    {
        $bulan = $request->input('bulan');
        $tahun = $request->input('tahun');
        $status =  $request->status;

        // Nama file disiapkan di sini agar diketahui UI
        $fileName = "transaksi_{$bulan}_{$tahun}_" . time() . ".xlsx";

        ExportTransaksiJob::dispatch($bulan, $tahun, $status, $fileName);

        return response()->json([
            'success' => true,
            'message' => 'File akan segera tersedia.',
            'file' => $fileName,
        ]);
    }

}
