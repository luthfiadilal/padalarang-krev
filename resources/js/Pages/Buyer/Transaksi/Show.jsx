import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Show({ transaksi, client_key, is_production }) {
    const [isMidtransScriptLoaded, setIsMidtransScriptLoaded] = useState(false);
    const [errorMessage, setErrorMessage] = useState('');
    const [showModal, setShowModal] = useState(false);
    const [modalMessage, setModalMessage] = useState('');
    const [modalTitle, setModalTitle] = useState('');

    // Fungsi untuk menampilkan modal kustom
    const showMessageModal = (title, message) => {
        setModalTitle(title);
        setModalMessage(message);
        setShowModal(true);
    };

    // Memuat script Midtrans secara dinamis
    useEffect(() => {
        // HANYA muat script jika snap_token sudah ada dan script belum dimuat
        if (transaksi.snap_token && !isMidtransScriptLoaded) {
            console.log('Semua kondisi terpenuhi, memuat script Midtrans...');

            const script = document.createElement('script');
            const snapUrl = is_production
                ? 'https://app.midtrans.com/snap/snap.js'
                : 'https://app.sandbox.midtrans.com/snap/snap.js';

            script.src = snapUrl;
            script.setAttribute('data-client-key', client_key);
            script.async = true;

            script.onload = () => {
                console.log('Snap.js berhasil dimuat.');
                setIsMidtransScriptLoaded(true);
            };

            script.onerror = () => {
                console.error('Gagal memuat script Snap.js');
                setErrorMessage(
                    'Gagal memuat script pembayaran. Silakan refresh halaman.',
                );
            };

            document.body.appendChild(script);

            // Bersihkan script saat komponen di-unmount
            return () => {
                if (document.body.contains(script)) {
                    document.body.removeChild(script);
                }
            };
        }
    }, [
        transaksi.snap_token,
        isMidtransScriptLoaded,
        client_key,
        is_production,
    ]);

    const handleBayar = () => {
        if (errorMessage) {
            showMessageModal('Terjadi Kesalahan', errorMessage);
            return;
        }

        if (!window.snap || !transaksi.snap_token) {
            console.error(
                'Pembayaran tidak tersedia. Snap.js belum dimuat atau snap_token tidak ada.',
            );
            showMessageModal(
                'Pembayaran Tidak Siap',
                'Pembayaran belum siap. Silakan tunggu sebentar atau refresh halaman.',
            );
            return;
        }

        window.snap.pay(transaksi.snap_token, {
            onSuccess: (result) => {
                console.log('Pembayaran Berhasil', result);
                router.visit(route('transaksi.show', transaksi.id), {
                    preserveScroll: true,
                    onSuccess: () => {
                        showMessageModal(
                            'Pembayaran Berhasil',
                            'Pembayaran berhasil! Transaksi Anda sedang diproses.',
                        );
                    },
                });
            },
            onPending: (result) => {
                console.log('Pembayaran Menunggu', result);
                router.visit(route('transaksi.show', transaksi.id), {
                    preserveScroll: true,
                    onSuccess: () => {
                        showMessageModal(
                            'Pembayaran Menunggu',
                            'Pembayaran menunggu konfirmasi. Silakan selesaikan pembayaran.',
                        );
                    },
                });
            },
            onError: (result) => {
                console.error('Pembayaran Gagal', result);
                showMessageModal(
                    'Pembayaran Gagal',
                    'Terjadi kesalahan saat pembayaran. Silakan coba lagi.',
                );
            },
            onClose: () => {
                console.log('Popup ditutup oleh user');
                showMessageModal(
                    'Pembayaran Dibatalkan',
                    'Anda menutup pop-up pembayaran.',
                );
            },
        });
    };

    // Tentukan apakah tombol "Lanjutkan Pembayaran" harus aktif
    const isPayButtonReady = isMidtransScriptLoaded && transaksi.snap_token;

    return (
        <div className="font-inter mx-auto max-w-4xl space-y-6 p-4">
            <h1 className="font-bold text-xl">Detail Transaksi</h1>

            {errorMessage && (
                <div className="rounded-md bg-red-100 p-4 text-red-700">
                    {errorMessage}
                </div>
            )}

            {showModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-50">
                    <div className="mx-4 w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="font-bold text-lg text-gray-900">
                                {modalTitle}
                            </h3>
                            <button
                                onClick={() => setShowModal(false)}
                                className="text-gray-400 transition hover:text-gray-600"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    className="h-6 w-6"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={2}
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                        <p className="text-sm text-gray-700">{modalMessage}</p>
                        <div className="mt-6 flex justify-end">
                            <button
                                onClick={() => setShowModal(false)}
                                className="font-semibold hover:bg-secondary-emphasis rounded-md bg-secondary px-4 py-2 text-sm text-white transition"
                            >
                                Tutup
                            </button>
                        </div>
                    </div>
                </div>
            )}

            <div className="space-y-4 rounded-lg border p-4 shadow-sm">
                <h2 className="font-semibold text-lg">Produk</h2>
                {transaksi.items.map((item) => (
                    <div
                        key={item.id}
                        className="flex gap-4 rounded-md border p-3"
                    >
                        <img
                            src={
                                item.produk.foto ??
                                'https://placehold.co/96x96/E5E7EB/9CA3AF?text=No+Image'
                            }
                            alt={item.produk.nama}
                            className="h-24 w-24 rounded object-cover"
                        />
                        <div>
                            <h3 className="text-md font-bold">
                                {item.produk.nama}
                            </h3>
                            <p className="text-sm text-gray-600">
                                Harga: Rp
                                {Number(item.harga).toLocaleString(
                                    'id-ID',
                                )} x {item.qty}
                            </p>
                            <p className="text-sm text-gray-600">
                                Subtotal: Rp
                                {Number(item.harga * item.qty).toLocaleString(
                                    'id-ID',
                                )}
                            </p>
                            <p className="text-sm text-gray-500">
                                Penjual: {item.penjual.nama_toko}
                            </p>
                        </div>
                    </div>
                ))}
            </div>

            <div className="space-y-2 rounded-lg border p-4 shadow-sm">
                <h2 className="font-semibold text-lg">Informasi Pengiriman</h2>
                <p className="text-sm text-gray-700">
                    Nama: {transaksi.nama_penerima}
                </p>
                <p className="text-sm text-gray-700">
                    Telepon: {transaksi.telepon_penerima}
                </p>
                <p className="text-sm text-gray-700">
                    Alamat: {transaksi.alamat_lengkap}, {transaksi.kelurahan},{' '}
                    {transaksi.kecamatan}, {transaksi.kota},{' '}
                    {transaksi.kode_pos}
                </p>
            </div>

            <div className="space-y-2 rounded-lg border p-4 shadow-sm">
                <h2 className="font-semibold text-lg">Informasi Pembayaran</h2>
                <p className="text-sm text-gray-700">
                    Metode: {transaksi.metode_pembayaran}
                </p>
                <p className="text-sm text-gray-700">
                    Status: {transaksi.status}
                </p>
                <p className="text-sm text-gray-700">
                    + Ongkir: Rp
                    {Number(transaksi.harga_ongkir).toLocaleString('id-ID')}
                </p>
                <p className="text-md font-bold text-gray-900">
                    Total Bayar:{' '}
                    <strong>
                        Rp
                        {Number(transaksi.total_harga).toLocaleString('id-ID')}
                    </strong>
                </p>
            </div>

            <div className="flex items-center justify-between pt-4">
                <button
                    onClick={() => router.visit(route('history-buyer.index'))}
                    className="rounded-md bg-gray-600 px-4 py-2 text-white transition hover:bg-gray-700"
                >
                    ⬅ Kembali
                </button>
                {transaksi.metode_pembayaran === 'cod' ? (
                    <span className="rounded-md bg-blue-500 px-4 py-2 text-white">
                        Pembayaran COD
                    </span>
                ) : transaksi.status === 'belum bayar' ? (
                    <button
                        onClick={handleBayar}
                        disabled={!isPayButtonReady}
                        className={`rounded-md px-4 py-2 text-white transition ${
                            isPayButtonReady
                                ? 'hover:bg-secondary-emphasis bg-secondary'
                                : 'cursor-not-allowed bg-gray-400'
                        }`}
                    >
                        {isPayButtonReady
                            ? 'Lanjutkan Pembayaran'
                            : 'Memuat...'}
                    </button>
                ) : (
                    <span className="rounded-md bg-green-500 px-4 py-2 text-white">
                        Pembayaran Selesai
                    </span>
                )}
            </div>
        </div>
    );
}
