import { Link, router, useForm } from '@inertiajs/react';

export default function CheckoutForm({ carts }) {
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

    const totalHarga = carts.reduce(
        (sum, item) => sum + Number(item.harga_total),
        0,
    );
    const totalBayar = totalHarga + Number(data.harga_ongkir || 0);
    const handleSubmit = (e) => {
        e.preventDefault();

        post(route('checkout'), {
            onSuccess: (response) => {
                const transaksiId = response.props.transaksi_id; // Ambil dari shared props
                if (transaksiId) {
                    router.visit(route('transaksi.show', transaksiId));
                }
            },
        });
    };

    return (
        <div className="mx-auto max-w-3xl px-4 py-6">
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
                                    Qty: {cart.quantity} x Rp
                                    {cart.harga_satuan.toLocaleString('id-ID')}
                                </p>
                                <p className="text-right text-gray-700">
                                    Ongkir: Rp
                                    {Number(data.harga_ongkir).toLocaleString(
                                        'id-ID',
                                    )}
                                </p>
                            </div>
                            <p className="font-bold">
                                Rp{cart.harga_total.toLocaleString('id-ID')}
                            </p>
                        </li>
                    ))}
                </ul>

                <p className="font-bold mt-1 text-right text-lg">
                    Total Bayar: Rp{totalBayar.toLocaleString('id-ID')}
                </p>
            </div>

            <form onSubmit={handleSubmit} className="space-y-4">
                {/* Semua field form seperti sebelumnya */}
                {/* ... form inputs ... */}

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
                            setData('jasa_pengiriman', value);

                            // Set harga_ongkir otomatis berdasarkan jasa
                        }}
                    >
                        <option value="">-- Pilih --</option>
                        <option value="ojek">Ojek</option>
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
