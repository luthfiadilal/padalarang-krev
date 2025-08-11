import CustomConfirmModal from '@/Components/CustomConfrimModal';
import { Icon } from '@iconify/react';
import { router } from '@inertiajs/react';
import { Card } from 'flowbite-react';
import { useState } from 'react';

export default function PaymentCard({ item, status, transaksi }) {
    // State untuk mengelola modal konfirmasi
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [modalData, setModalData] = useState({
        message: '',
        action: null,
    });

    const produk = item.produk;
    const kategori = produk?.kategori?.kategori || 'Tanpa Kategori';
    const tipe = produk?.tipe_produk?.tipe_produk || 'Tanpa Tipe';

    const getImageUrl = (path) => {
        const filename = path.split('/').pop();
        return `/storage/produk/foto/${filename}`;
    };

    const formatRupiah = (harga) => {
        return Number(harga).toLocaleString('id-ID');
    };

    // Fungsi untuk menampilkan modal konfirmasi
    const showCustomConfirm = (message, action) => {
        setModalData({ message, action });
        setIsModalOpen(true);
    };

    // Fungsi untuk menutup modal
    const handleCloseModal = () => {
        setIsModalOpen(false);
        setModalData({ message: '', action: null });
    };

    // Fungsi untuk memproses tindakan setelah konfirmasi
    const handleConfirmAction = () => {
        handleCloseModal();
        if (modalData.action === 'cancel') {
            router.post(
                route('transaksi.cancel', { transaksi: item.transaksi_id }),
                {},
                {
                    onSuccess: () => {
                        showCustomAlert('Transaksi berhasil dibatalkan');
                    },
                    onError: () => {
                        showCustomAlert('Gagal membatalkan transaksi');
                    },
                },
            );
        } else if (modalData.action === 'received') {
            router.post(
                route('transaksi.received', { transaksi: transaksi.id }),
                {},
                {
                    onSuccess: () => {
                        showCustomAlert(
                            'Transaksi berhasil ditandai sebagai telah diterima',
                        );
                    },
                    onError: () => {
                        showCustomAlert('Gagal mengubah status transaksi');
                    },
                },
            );
        }
    };

    // Fungsi untuk tombol "Batalkan"
    const handleToCancel = () => {
        showCustomConfirm('Yakin ingin membatalkan transaksi ini?', 'cancel');
    };

    // Fungsi untuk tombol "Telah Diterima"
    const handleToReceived = () => {
        showCustomConfirm(
            'Apakah Anda yakin pesanan sudah diterima?',
            'received',
        );
    };

    const handleBuyAgain = () => {
        router.post(route('cart.add', { produk: produk.id }));
    };

    return (
        <>
            <Card className="flex flex-col gap-4">
                <div className="flex w-full flex-col gap-4 md:flex-row">
                    <img
                        src={getImageUrl(produk?.foto)}
                        alt={produk?.nama}
                        className="h-24 w-24 rounded-lg object-cover"
                    />
                    <div className="flex w-full flex-wrap justify-between gap-3 px-3 text-textgray">
                        <div className="flex flex-col gap-2">
                            <h5 className="text-20 font-manropeSemiBold">
                                {produk?.nama}
                            </h5>
                            <p className="text-16 font-manropeMedium text-textgray">
                                {kategori} • {tipe}
                            </p>
                            <div className="mt-1 flex flex-col gap-1 text-15">
                                <p className="font-manropeMedium">
                                    Harga Satuan:
                                    <span className="font-manropeSemiBold text-red-500">
                                        {' '}
                                        Rp {formatRupiah(item.produk.harga)}
                                    </span>
                                </p>
                                <p className="font-manropeMedium">
                                    Harga Diskon:
                                    <span className="font-manropeSemiBold text-secondary">
                                        {' '}
                                        Rp {formatRupiah(item.harga_satuan)}
                                    </span>
                                </p>
                                <p>Jumlah: {item.quantity}</p>
                                <p className="">
                                    Total:
                                    <span className="font-manropeSemiBold text-primary">
                                        {' '}
                                        Rp {formatRupiah(item.harga_total)}
                                    </span>
                                </p>
                                {transaksi?.jasa_pengiriman && (
                                    <p className="font-manropeMedium">
                                        Jasa Pengiriman:{' '}
                                        <span className="font-manropeSemiBold text-blue-500">
                                            {transaksi.jasa_pengiriman ===
                                            'ambil_di_tempat'
                                                ? 'Ambil di Tempat'
                                                : 'COD'}
                                        </span>
                                    </p>
                                )}
                                {transaksi?.harga_ongkir !== undefined && (
                                    <p className="font-manropeMedium">
                                        + Ongkir:
                                        <span className="font-manropeSemiBold text-primary">
                                            {' '}
                                            Rp{' '}
                                            {formatRupiah(
                                                transaksi.harga_ongkir,
                                            )}
                                        </span>
                                    </p>
                                )}
                                <p className="font-manropeMedium">
                                    Total Bayar:
                                    <span className="font-manropeSemiBold text-primary">
                                        {' '}
                                        Rp {formatRupiah(transaksi.total_harga)}
                                    </span>
                                </p>
                                {transaksi?.metode_pembayaran && (
                                    <p className="font-manropeMedium">
                                        Metode Pembayaran:
                                        <span className="font-manropeSemiBold text-purple-500">
                                            {' '}
                                            {transaksi.metode_pembayaran.toUpperCase()}
                                        </span>
                                    </p>
                                )}
                            </div>
                        </div>
                        <div className="flex items-end gap-2">
                            <a
                                href={item.penjual?.whatsapp_link || '#'}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="hover:bg-primary-dark flex items-center gap-2 rounded-lg bg-primary px-3 py-2 text-white"
                                title={`Chat dengan ${item.penjual?.nama_toko || 'Penjual'}`}
                            >
                                <Icon
                                    icon="solar:chat-round-line-linear"
                                    className="text-xl"
                                />
                                <p className="text-14 font-manropeSemiBold">
                                    Chat
                                </p>
                            </a>
                            {status === 'belumBayar' && (
                                <button
                                    onClick={handleToCancel}
                                    className="flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-textgray shadow-[0_6px_2px_rgba(0,0,0,0.08)]"
                                    title="Batalkan Transaksi"
                                >
                                    <Icon
                                        icon="mdi:cancel"
                                        className="text-xl"
                                    />
                                    <p className="text-14 font-manropeSemiBold">
                                        Cancel
                                    </p>
                                </button>
                            )}
                            {status === 'menungguDiterima' && (
                                <button
                                    onClick={handleToReceived}
                                    className="flex items-center gap-2 rounded-lg bg-green-500 px-3 py-2 text-white"
                                    title="Tandai sebagai Diterima"
                                >
                                    <Icon
                                        icon="mdi:check"
                                        className="text-xl"
                                    />
                                    <p className="text-14 font-manropeSemiBold">
                                        Telah Diterima
                                    </p>
                                </button>
                            )}
                            {(status === 'sudahBayar' ||
                                status === 'dibatalkan' ||
                                status === 'telah diterima') && (
                                <button
                                    onClick={handleBuyAgain}
                                    className="flex items-center gap-2 rounded-lg bg-secondary px-3 py-2 text-white"
                                    title="Beli Lagi"
                                >
                                    <Icon
                                        icon="mdi:cart-plus"
                                        className="text-xl"
                                    />
                                    <p className="text-14 font-manropeSemiBold">
                                        Beli Lagi
                                    </p>
                                </button>
                            )}
                        </div>
                    </div>
                </div>
            </Card>
            <CustomConfirmModal
                show={isModalOpen}
                onClose={handleCloseModal}
                onConfirm={handleConfirmAction}
                message={modalData.message}
            />
        </>
    );
}
