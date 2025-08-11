import { router } from '@inertiajs/react';

// Komponen ini menampilkan detail dari satu item produk dalam sebuah transaksi
export default function PaymentCardSeller({ item, transaksi }) {
    const produk = item.produk;
    const kategori = produk?.kategori?.kategori || 'Tanpa Kategori';
    const tipe = produk?.tipe_produk?.tipe_produk || 'Tanpa Tipe';

    // Fungsi helper untuk mendapatkan URL gambar produk
    const getImageUrl = (path) => {
        const filename = path.split('/').pop();
        return `/storage/produk/foto/${filename}`;
    };

    // Fungsi untuk mendapatkan badge status yang sesuai
    const getStatusBadge = (currentStatus) => {
        const statusConfig = {
            'belum bayar': {
                color: 'bg-yellow-100 text-yellow-800',
                text: 'Belum Bayar',
            },
            'menunggu diterima': {
                color: 'bg-blue-100 text-blue-800',
                text: 'Menunggu Diterima',
            },
            'telah diterima': {
                color: 'bg-green-100 text-green-800',
                text: 'Telah Diterima',
            },
            cancel: { color: 'bg-red-100 text-red-800', text: 'Dibatalkan' },
        };

        const config =
            statusConfig[currentStatus] || statusConfig['belum bayar'];

        return (
            <span
                className={`font-medium rounded-full px-2 py-1 text-xs ${config.color}`}
            >
                {config.text}
            </span>
        );
    };

    // Fungsi umum untuk update status dengan konfirmasi
    // Catatan: Dalam produksi, Anda harus menggunakan modal kustom sebagai pengganti `confirm` atau `alert`
    const handleUpdateStatus = (newStatus, confirmMessage) => {
        if (confirm(confirmMessage)) {
            router.post(
                route('transaksi.update-status', { transaksi: transaksi.id }),
                { status: newStatus },
                {
                    onSuccess: () => {
                        // Anda bisa menampilkan notifikasi sukses di sini
                    },
                    onError: (errors) => {
                        console.error('Error:', errors);
                        // Anda bisa menampilkan notifikasi error di sini
                    },
                },
            );
        }
    };

    return (
        <div className="flex gap-4 rounded-lg border border-gray-200 bg-gray-50 p-4 shadow-sm">
            {/* Gambar Produk */}
            <img
                src={getImageUrl(produk?.foto)}
                alt={produk?.nama}
                className="h-24 w-24 flex-shrink-0 rounded-lg object-cover"
            />

            {/* Detail Produk */}
            <div className="flex w-full flex-col justify-between md:flex-row md:items-center">
                <div className="flex flex-col gap-1 text-gray-700">
                    <div className="flex items-center gap-3">
                        <h5 className="font-semibold text-xl text-gray-800">
                            {produk?.nama}
                        </h5>
                        {/* Menampilkan badge status */}
                        {getStatusBadge(transaksi?.status)}
                    </div>
                    <p className="text-sm text-gray-500">
                        {kategori} • {tipe}
                    </p>
                    <div className="mt-2 text-sm">
                        <p className="font-medium">
                            Jumlah:{' '}
                            <span className="font-semibold">
                                {item.quantity}
                            </span>
                        </p>
                        <p className="font-medium">
                            Harga Total:
                            <span className="font-bold ml-1 text-primary">
                                Rp{' '}
                                {parseInt(item.harga_total).toLocaleString(
                                    'id-ID',
                                )}
                            </span>
                        </p>
                    </div>
                </div>

                {/* Tombol Aksi */}
                <div className="mt-4 flex flex-col items-end gap-2 md:mt-0">
                    {transaksi.status === 'belum bayar' && (
                        <>
                            <button
                                onClick={() =>
                                    handleUpdateStatus(
                                        'cancel',
                                        'Yakin ingin membatalkan transaksi ini?',
                                    )
                                }
                                className="font-semibold w-full rounded-lg bg-white px-4 py-2 text-sm text-red-500 shadow-sm ring-1 ring-inset ring-red-300 transition-all duration-200 hover:bg-red-50"
                            >
                                Batalkan
                            </button>
                            <button
                                onClick={() =>
                                    handleUpdateStatus(
                                        'menunggu diterima',
                                        'Yakin ingin menerima pesanan ini?',
                                    )
                                }
                                className="font-semibold w-full rounded-lg bg-blue-600 px-4 py-2 text-sm text-white shadow-sm transition-all duration-200 hover:bg-blue-700"
                            >
                                Terima Pesanan
                            </button>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}
