import { Link } from '@inertiajs/react';
import PaymentCard from './PaymentCard';

export default function PaymentCardGroup({ transaksi, status }) {
    const toko = transaksi.items[0]?.penjual;
    const {
        nama_penerima,
        telepon_penerima,
        alamat_lengkap,
        kelurahan,
        kecamatan,
        kota,
        kode_pos,
    } = transaksi;

    const getImageProfile = (path) => {
        if (!path) return '/placeholder.jpg';
        if (path.startsWith('foto_profil/')) {
            return `/storage/${path}`;
        }
        return path;
    };

    return (
        <div className="mb-6 rounded-xl border bg-white p-4 shadow-sm">
            {/* Bagian Info Toko dan Kode Transaksi */}
            <div className="mb-4 flex items-center gap-3">
                <img
                    src={getImageProfile(toko?.foto_profil)}
                    alt={toko?.nama_toko}
                    className="h-12 w-12 rounded-full object-cover"
                />
                <div>
                    <h4 className="font-bold text-lg">{toko?.nama_toko}</h4>
                    <p className="text-sm text-gray-500">
                        Kode Transaksi: {transaksi.kode_transaksi}
                    </p>
                </div>
            </div>

            {/* Bagian Alamat Pengiriman */}
            <div className="mb-4 rounded-lg bg-gray-50 p-4">
                <h5 className="text-md font-bold mb-2 text-gray-700">
                    Alamat Pengiriman
                </h5>
                <p className="text-sm text-gray-600">{nama_penerima}</p>
                <p className="text-sm text-gray-600">{telepon_penerima}</p>
                <p className="text-sm text-gray-600">
                    {alamat_lengkap}, {kelurahan}, {kecamatan}, {kota},{' '}
                    {kode_pos}
                </p>
            </div>

            {/* Daftar Produk dalam Transaksi */}
            <div className="space-y-4">
                {transaksi.items.map((item) => (
                    <PaymentCard
                        key={item.id}
                        item={item}
                        status={status}
                        transaksi={transaksi}
                    />
                ))}
            </div>

            {/* Tombol Lanjut Pembayaran (hanya muncul jika status 'belumBayar') */}
            {status === 'belumBayar' && (
                <div className="mt-4 text-right">
                    <Link
                        href={route('transaksi.show', transaksi.id)}
                        className="inline-block rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                    >
                        Lanjut Pembayaran
                    </Link>
                </div>
            )}
        </div>
    );
}
