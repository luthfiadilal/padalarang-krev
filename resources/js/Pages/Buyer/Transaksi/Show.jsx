import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Show({ transaksi, snap_token }) {
    const [snapReady, setSnapReady] = useState(false);

    useEffect(() => {
        if (
            snap_token &&
            transaksi.status === 'belum bayar' &&
            transaksi.metode_pembayaran !== 'cod'
        ) {
            const script = document.createElement('script');
            script.src = 'https://app.sandbox.midtrans.com/snap/snap.js';
            script.setAttribute(
                'data-client-key',
                import.meta.env.VITE_MIDTRANS_CLIENT_KEY,
            );
            script.async = true;

            script.onload = () => {
                setSnapReady(true); // setel true jika script berhasil dimuat
            };

            document.body.appendChild(script);
        }
    }, [snap_token]);

    const handleBayar = () => {
        if (!window.snap || !snap_token) {
            alert('Pembayaran tidak tersedia.');
            return;
        }

        window.snap.pay(snap_token, {
            onSuccess: (result) => {
                console.log('Sukses', result);
                router.visit(`/transaksi/${transaksi.id}`);
            },
            onPending: (result) => {
                console.log('Pending', result);
                router.visit(`/transaksi/${transaksi.id}`);
            },
            onError: (result) => {
                console.error('Error', result);
                alert('Terjadi kesalahan saat pembayaran.');
            },
            onClose: () => {
                console.log('Popup ditutup');
            },
        });
    };

    return (
        <div className="mx-auto max-w-4xl space-y-6 p-4">
            <h1 className="font-bold text-xl">Detail Transaksi</h1>

            {/* Produk */}
            <div className="space-y-4">
                <h2 className="font-semibold text-lg">Produk</h2>
                {transaksi.items.map((item) => (
                    <div
                        key={item.id}
                        className="flex gap-4 rounded border p-3"
                    >
                        <img
                            src={
                                item.produk.gambar_utama_url ?? '/no-image.png'
                            }
                            alt={item.produk.nama}
                            className="h-24 w-24 rounded object-cover"
                        />
                        <div>
                            <h3 className="text-md font-bold">
                                {item.produk.nama}
                            </h3>
                            <p className="text-sm text-gray-600">
                                Harga: Rp{item.harga.toLocaleString()} x{' '}
                                {item.jumlah}
                            </p>
                            <p className="text-sm text-gray-600">
                                Subtotal: Rp
                                {(item.harga * item.jumlah).toLocaleString()}
                            </p>
                            <p className="text-sm text-gray-500">
                                Penjual: {item.penjual.name}
                            </p>
                        </div>
                    </div>
                ))}
            </div>

            {/* Informasi Pengiriman */}
            <div className="space-y-2">
                <h2 className="font-semibold text-lg">Informasi Pengiriman</h2>
                <p>Nama: {transaksi.nama_penerima}</p>
                <p>Telepon: {transaksi.telepon_penerima}</p>
                <p>
                    Alamat: {transaksi.alamat_lengkap}, {transaksi.kelurahan},{' '}
                    {transaksi.kecamatan}, {transaksi.kota},{' '}
                    {transaksi.kode_pos}
                </p>
            </div>

            {/* Informasi Pembayaran */}
            <div className="space-y-2">
                <h2 className="font-semibold text-lg">Informasi Pembayaran</h2>
                <p>Metode: {transaksi.metode_pembayaran}</p>
                <p>Status: {transaksi.status}</p>

                <p>
                    + Ongkir: Rp
                    {Number(transaksi.harga_ongkir).toLocaleString('id-ID')}
                </p>
                <p>
                    Total Bayar:{' '}
                    <strong>
                        Rp
                        {Number(transaksi.total_harga).toLocaleString('id-ID')}
                    </strong>
                </p>
            </div>

            {/* Tombol Aksi */}
            <div className="pt-4">
                <button
                    onClick={() => router.visit(route('history-buyer.index'))}
                    className="hover:bg-primary-emphasis mr-4 rounded bg-primary px-4 py-2 text-white"
                >
                    ⬅ Kembali ke Riwayat
                </button>
                {transaksi.metode_pembayaran === 'cod' ? (
                    <button
                        onClick={() => router.visit('/keranjang')}
                        className="rounded bg-gray-600 px-4 py-2 text-white hover:bg-gray-700"
                    >
                        Kembali
                    </button>
                ) : transaksi.status === 'belum bayar' ? (
                    <button
                        onClick={handleBayar}
                        disabled={!snapReady}
                        className={`rounded px-4 py-2 text-white ${
                            snapReady
                                ? 'hover:bg-secondary-emphasis bg-secondary'
                                : 'cursor-not-allowed bg-gray-400'
                        }`}
                    >
                        Lanjutkan Pembayaran
                    </button>
                ) : null}
            </div>
        </div>
    );
}
