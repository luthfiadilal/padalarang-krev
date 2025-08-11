import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function CheckoutForm({ carts }) {
    // Menghitung total belanja dari item-item di keranjang
    const totalBelanja = carts.reduce((sum, cart) => sum + cart.harga_total, 0);

    // State untuk mengontrol modal peringatan
    const [showWarningModal, setShowWarningModal] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        cart_ids: carts.map((c) => c.id),
        catatan: '',
        nama_penerima: '',
        telepon_penerima: '',
        alamat_lengkap: '',
        kelurahan: '',
        kecamatan: '',
        kota: '',
        kode_pos: '',
        jasa_pengiriman: '',
        metode_pembayaran: '',
        harga_ongkir: 12000,
    });

    const handleSubmit = (e) => {
        e.preventDefault();

        // Validasi tambahan di sisi client sebelum mengirim form
        if (data.jasa_pengiriman === 'ojek' && totalBelanja < 50000) {
            setShowWarningModal(true);
            return;
        }

        post(route('checkout'), {
            onSuccess: (response) => {
                const transaksiId = response.props.transaksi_id; // Ambil dari shared props
                if (transaksiId) {
                    router.visit(route('transaksi.show', transaksiId));
                }
            },
        });
    };

    // Fungsi untuk menutup modal peringatan
    const closeWarningModal = () => {
        setShowWarningModal(false);
    };

    // Fungsi pembantu untuk memformat mata uang
    const formatCurrency = (amount) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(amount);
    };

    return (
        <div className="mx-auto max-w-3xl px-4 py-6">
            {/* Modal Peringatan Kustom */}
            {showWarningModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-800 bg-opacity-50">
                    <div className="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
                        <div className="flex items-center justify-between">
                            <h3 className="font-bold text-lg text-red-600">
                                Peringatan!
                            </h3>
                            <button
                                onClick={closeWarningModal}
                                className="text-gray-400 hover:text-gray-600"
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
                        <p className="mt-4 text-sm text-gray-700">
                            Total belanja minimal Rp50.000 untuk menggunakan
                            jasa ojek.
                        </p>
                        <div className="mt-6 text-right">
                            <button
                                onClick={closeWarningModal}
                                className="rounded bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700"
                            >
                                OK
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Tombol Kembali */}
            <div className="mb-6">
                <Link
                    href={route('cart.index')}
                    className="inline-block rounded bg-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-300"
                >
                    ← Kembali ke Keranjang
                </Link>
            </div>

            <h1 className="font-bold mb-4 text-2xl">Checkout</h1>

            <div className="mb-6">
                <h2 className="font-semibold mb-2 text-lg">
                    Produk yang Dibeli
                </h2>
                <ul className="space-y-2">
                    {carts.map((cart) => (
                        <li
                            key={cart.id}
                            className="flex items-center justify-between rounded border p-3"
                        >
                            <div>
                                <p className="font-medium">
                                    {cart.produk.nama}
                                </p>
                                <p className="text-sm text-gray-600">
                                    Qty: {cart.quantity} x{' '}
                                    {formatCurrency(cart.harga_satuan)}
                                </p>
                            </div>
                            <p className="font-bold">
                                {formatCurrency(cart.harga_total)}
                            </p>
                        </li>
                    ))}
                </ul>
            </div>

            {/* Menampilkan total belanja */}
            <div className="mb-6 rounded-lg bg-blue-50 p-4 shadow-sm">
                <p className="font-semibold text-gray-800">
                    Total Belanja: {formatCurrency(totalBelanja)}
                </p>
                {/* Peringatan jika total belanja di bawah 50.000 */}
                {totalBelanja < 50000 && (
                    <p className="mt-2 text-sm text-red-600">
                        Total belanja di bawah Rp50.000. Opsi pengiriman 'Ojek'
                        tidak bisa digunakan.
                    </p>
                )}
            </div>

            <form onSubmit={handleSubmit} className="space-y-4">
                {/* Nama Penerima */}
                <div>
                    <label className="font-medium mb-2 block">
                        Nama Penerima
                    </label>
                    <input
                        type="text"
                        className="input"
                        value={data.nama_penerima}
                        onChange={(e) =>
                            setData('nama_penerima', e.target.value)
                        }
                    />
                    {errors.nama_penerima && (
                        <p className="text-sm text-red-500">
                            {errors.nama_penerima}
                        </p>
                    )}
                </div>

                {/* Telepon Penerima */}
                <div>
                    <label className="font-medium mb-2 block">
                        Telepon Penerima
                    </label>
                    <input
                        type="text"
                        className="input"
                        value={data.telepon_penerima}
                        onChange={(e) =>
                            setData('telepon_penerima', e.target.value)
                        }
                    />
                    {errors.telepon_penerima && (
                        <p className="text-sm text-red-500">
                            {errors.telepon_penerima}
                        </p>
                    )}
                </div>

                {/* Alamat Lengkap */}
                <div>
                    <label className="font-medium mb-2 block">
                        Alamat Lengkap
                    </label>
                    <textarea
                        className="input"
                        value={data.alamat_lengkap}
                        onChange={(e) =>
                            setData('alamat_lengkap', e.target.value)
                        }
                    />
                    {errors.alamat_lengkap && (
                        <p className="text-sm text-red-500">
                            {errors.alamat_lengkap}
                        </p>
                    )}
                </div>

                {/* Grid: Kelurahan, Kecamatan, Kota, Kode Pos */}
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="font-medium mb-2 block">
                            Kelurahan
                        </label>
                        <input
                            className="input"
                            value={data.kelurahan}
                            onChange={(e) =>
                                setData('kelurahan', e.target.value)
                            }
                        />
                        {errors.kelurahan && (
                            <p className="text-sm text-red-500">
                                {errors.kelurahan}
                            </p>
                        )}
                    </div>

                    <div>
                        <label className="font-medium mb-2 block">
                            Kecamatan
                        </label>
                        <input
                            className="input"
                            value={data.kecamatan}
                            onChange={(e) =>
                                setData('kecamatan', e.target.value)
                            }
                        />
                        {errors.kecamatan && (
                            <p className="text-sm text-red-500">
                                {errors.kecamatan}
                            </p>
                        )}
                    </div>

                    <div>
                        <label className="font-medium mb-2 block">Kota</label>
                        <input
                            className="input"
                            value={data.kota}
                            onChange={(e) => setData('kota', e.target.value)}
                        />
                        {errors.kota && (
                            <p className="text-sm text-red-500">
                                {errors.kota}
                            </p>
                        )}
                    </div>

                    <div>
                        <label className="font-medium mb-2 block">
                            Kode Pos
                        </label>
                        <input
                            className="input"
                            value={data.kode_pos}
                            onChange={(e) =>
                                setData('kode_pos', e.target.value)
                            }
                        />
                        {errors.kode_pos && (
                            <p className="text-sm text-red-500">
                                {errors.kode_pos}
                            </p>
                        )}
                    </div>
                </div>

                {/* Jasa Pengiriman */}
                <div>
                    <label className="font-medium mb-2 block">
                        Jasa Pengiriman
                    </label>
                    <select
                        className="input"
                        value={data.jasa_pengiriman}
                        onChange={(e) => {
                            const value = e.target.value;
                            if (value === 'ojek' && totalBelanja < 50000) {
                                setShowWarningModal(true);
                                setData('jasa_pengiriman', ''); // Reset pilihan
                            } else {
                                setData('jasa_pengiriman', value);
                            }
                        }}
                    >
                        <option value="">-- Pilih --</option>
                        {/* Opsi Ojek dinonaktifkan jika total belanja < 50000 */}
                        <option value="ojek" disabled={totalBelanja < 50000}>
                            Ojek
                        </option>
                        <option value="ambil_di_tempat">Ambil di Tempat</option>
                    </select>
                </div>

                {/* Metode Pembayaran */}
                <div>
                    <label className="font-medium mb-2 block">
                        Metode Pembayaran
                    </label>
                    <select
                        className="input"
                        value={data.metode_pembayaran}
                        onChange={(e) =>
                            setData('metode_pembayaran', e.target.value)
                        }
                    >
                        <option value="">-- Pilih --</option>
                        <option value="cod">COD (Bayar di Tempat)</option>
                        <option value="transfer">Transfer</option>
                    </select>
                    {errors.metode_pembayaran && (
                        <p className="text-sm text-red-500">
                            {errors.metode_pembayaran}
                        </p>
                    )}
                </div>

                {/* Catatan */}
                <div>
                    <label className="font-medium mb-2 block">
                        Catatan (Opsional)
                    </label>
                    <textarea
                        className="input"
                        value={data.catatan}
                        onChange={(e) => setData('catatan', e.target.value)}
                    />
                </div>

                {/* Submit Button */}
                <button
                    type="submit"
                    disabled={processing}
                    className="rounded bg-green-600 px-4 py-2 text-white hover:bg-green-700"
                >
                    {processing ? 'Memproses...' : 'Lanjut Checkout'}
                </button>
            </form>
        </div>
    );
}
