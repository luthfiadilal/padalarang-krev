import { Icon } from '@iconify/react';
import PaymentCardSeller from './PaymentCardSeller';

// Komponen ini mengelompokkan item-item dari satu transaksi
export default function PaymentCardGroupSeller({ transaksi }) {
    // Mengambil data penjual dari item pertama.
    // Ini mengasumsikan semua item dalam satu transaksi berasal dari satu penjual.
    const toko = transaksi.items[0]?.penjual;
    const pembeli = transaksi.pembeli;

    // Fungsi helper untuk mendapatkan URL gambar profil
    const getImageProfile = (path) => {
        if (!path) return 'https://placehold.co/48x48/e5e7eb/7f8c8d?text=User';

        if (path.startsWith('foto_profil/')) {
            return `/storage/${path}`;
        }
        return path;
    };

    return (
        <div className="mb-6 rounded-xl border bg-white p-6 shadow-md transition-all duration-300 hover:shadow-lg">
            {/* Header: Informasi Toko */}
            <div className="mb-6 flex items-center gap-4 border-b border-gray-200 pb-4">
                <img
                    src={
                        toko?.foto_profil
                            ? getImageProfile(toko.foto_profil)
                            : 'https://placehold.co/48x48/e5e7eb/7f8c8d?text=User'
                    }
                    alt={toko?.nama_toko}
                    className="h-12 w-12 rounded-full object-cover shadow-sm"
                />
                <div className="flex-1">
                    <h4 className="font-bold text-xl text-gray-800">
                        {toko?.nama_toko}
                    </h4>
                    <p className="text-sm text-gray-500">
                        Kode Transaksi:{' '}
                        <span className="font-semibold text-gray-700">
                            {transaksi.kode_transaksi}
                        </span>
                    </p>
                </div>
            </div>

            {/* Informasi Pembeli & Pengiriman */}
            <div className="mb-6 rounded-lg bg-gray-50 p-4">
                <h5 className="font-semibold mb-2 flex items-center gap-2 text-base text-gray-800">
                    <Icon
                        icon="lucide:user-2"
                        className="text-xl text-primary"
                    />
                    Informasi Pembeli
                </h5>
                <div className="flex flex-col gap-1 text-sm text-gray-600">
                    <p>
                        <span className="font-semibold">Nama Pembeli:</span>{' '}
                        {pembeli?.user?.name || 'Tidak diketahui'}
                    </p>
                    <p>
                        <span className="font-semibold">Nama Penerima:</span>{' '}
                        {transaksi.nama_penerima || 'Tidak diketahui'}
                    </p>
                    <p>
                        <span className="font-semibold">Telepon:</span>{' '}
                        {transaksi.telepon_penerima || '-'}
                    </p>
                    <div className="flex items-start gap-2">
                        <Icon
                            icon="lucide:map-pin"
                            className="mt-0.5 text-xl text-primary"
                        />
                        <p className="flex-1">
                            <span className="font-semibold">Alamat:</span>{' '}
                            {transaksi.alamat_lengkap || 'Tidak diketahui'}
                        </p>
                    </div>
                </div>
            </div>

            {/* Daftar Produk dalam Transaksi */}
            <div className="space-y-4">
                {transaksi.items.map((item) => (
                    <PaymentCardSeller
                        key={item.id}
                        item={item}
                        transaksi={transaksi}
                    />
                ))}
            </div>
        </div>
    );
}
