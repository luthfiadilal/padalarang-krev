import { Icon } from '@iconify/react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { Badge, Button } from 'flowbite-react';
import { toast } from 'react-toastify';

const CardProduk = ({ product, onClick }) => {
    const getImageUrl = (path) => {
        if (!path) return '/placeholder.jpg';
        const filename = path.split('/').pop();
        return `/storage/produk/foto/${filename}`;
    };

    const handleClick = (e) => {
        e.stopPropagation();
        onClick?.();
    };

    // Fungsi untuk tombol "Tambah ke Keranjang"
    const handleTambahKeranjang = async () => {
        try {
            // Memanggil endpoint lama yang melakukan redirect
            const response = await axios.post('/cart', {
                produk_id: product.id,
                quantity: 1,
            });
            // Karena ini akan redirect, respons ini mungkin tidak akan terjangkau
            toast.success(
                response.data.message || 'Berhasil menambahkan ke keranjang',
                {
                    position: 'top-right',
                    autoClose: 3000,
                    hideProgressBar: false,
                    closeOnClick: true,
                    pauseOnHover: true,
                    draggable: true,
                },
            );
        } catch (error) {
            toast.error(
                error.response?.data?.message ||
                    'Terjadi kesalahan saat menambahkan ke keranjang',
            );
        }
    };

    // FUNGSI BARU UNTUK CHECKOUT LANGSUNG
    const handleDirectCheckout = (e) => {
        e.stopPropagation();

        console.log('--- Memulai alur checkout langsung ---');
        console.log('ID Produk yang dipilih:', product.id);

        // Memanggil endpoint baru yang selalu mengembalikan JSON
        axios
            .post('/cart-direct-checkout', {
                produk_id: product.id,
                quantity: 1,
            })
            .then((response) => {
                console.log(
                    'Respons dari API /cart-direct-checkout:',
                    response.data,
                );

                const cartId = response.data.cart?.id;

                if (!cartId) {
                    console.error(
                        'Error: API response tidak memiliki cart.id. Respons:',
                        response.data,
                    );
                    toast.error(
                        'Gagal mendapatkan ID keranjang. Format respons API tidak valid.',
                    );
                    return;
                }

                console.log(
                    'Produk berhasil ditambahkan atau diperbarui. Cart ID:',
                    cartId,
                );

                const checkoutUrl = route('buyer.checkout.form');
                console.log('Melanjutkan ke URL tujuan:', checkoutUrl);

                router.get(
                    checkoutUrl,
                    {
                        'cart_ids[]': [cartId],
                    },
                    {
                        onSuccess: (redirectResponse) => {
                            console.log(
                                'Sukses: Redirect ke halaman form berhasil.',
                                redirectResponse,
                            );
                        },
                        onError: (errors) => {
                            console.error('Error saat redirect:', errors);
                            toast.error(
                                'Terjadi kesalahan saat checkout. Cek konsol untuk detail.',
                            );
                        },
                    },
                );
            })
            .catch((error) => {
                console.error(
                    'Terjadi error saat menambahkan ke keranjang:',
                    error,
                );
                const errorMessage =
                    error.response?.data?.message ||
                    'Terjadi kesalahan saat menambahkan produk ke keranjang.';
                toast.error(errorMessage);
            });
    };

    const handleChat = () => {
        const productName = product.nama;
        const productPrice = new Intl.NumberFormat('id-ID').format(
            product.harga,
        );

        const message = `Halo, saya tertarik dengan produk:

Nama Produk: ${productName}
Harga: Rp ${productPrice}

Apakah produk ini masih tersedia?`;

        if (product.penjual?.whatsapp_link) {
            window.open(
                `${product.penjual.whatsapp_link}?text=${encodeURIComponent(message)}`,
            );
        } else if (product.penjual?.no_hp) {
            const phone = product.penjual.no_hp.startsWith('0')
                ? '62' + product.penjual.no_hp.substring(1)
                : product.penjual.no_hp;
            window.open(
                `https://wa.me/${phone}?text=${encodeURIComponent(message)}`,
            );
        } else {
            toast.error('Kontak penjual tidak tersedia');
        }
    };

    return (
        <div className="flex h-[350px] w-full flex-col overflow-hidden rounded-lg bg-white shadow-sm transition-all duration-300 hover:shadow">
            {/* Gambar Produk */}
            <div className="relative h-[50%]" onClick={handleClick}>
                <img
                    src={getImageUrl(product.foto)}
                    alt={product.nama}
                    className="h-full w-full object-cover"
                    onError={(e) => {
                        e.target.src = '/placeholder.jpg';
                    }}
                />
                {product.harga_diskon && (
                    <Badge
                        color="red"
                        className="absolute right-3 top-2 rounded-full text-textgray"
                    >
                        {Math.round(
                            (1 - product.harga_diskon / product.harga) * 100,
                        )}
                        %
                    </Badge>
                )}
            </div>

            {/* Info Produk */}
            <div className="flex-grow overflow-y-auto p-3">
                <h3 className="font-medium mb-1 line-clamp-1 text-gray-900">
                    {product.nama}
                </h3>
                <div className="mb-2">
                    {product.harga_diskon ? (
                        <div className="flex items-center gap-2">
                            <span className="font-bold text-red-600">
                                Rp
                                {new Intl.NumberFormat('id-ID').format(
                                    product.harga_diskon,
                                )}
                            </span>
                            <span className="text-xs text-gray-400 line-through">
                                Rp
                                {new Intl.NumberFormat('id-ID').format(
                                    product.harga,
                                )}
                            </span>
                        </div>
                    ) : (
                        <span className="font-bold text-gray-900">
                            Rp
                            {new Intl.NumberFormat('id-ID').format(
                                product.harga,
                            )}
                        </span>
                    )}
                </div>
                <div className="flex flex-wrap gap-x-2 gap-y-1 text-xs font-manropeRegular text-gray-600">
                    {product.ukuran && <span>{product.ukuran}</span>}
                    <span>
                        Stok: {product.stok ? product.stok : 'tersedia'}
                    </span>
                </div>
                <p className="mt-2 line-clamp-2 font-manropeMedium">
                    {product.penjual?.nama_toko}
                </p>
            </div>

            {/* Tombol Aksi */}
            <div className="grid grid-cols-4 gap-2 p-3 pt-0">
                <Button
                    size="sm"
                    color="gray"
                    onClick={handleTambahKeranjang}
                    className="col-span-1 flex items-center justify-center gap-1 p-2 text-textgray"
                >
                    <Icon icon="solar:cart-large-4-linear" width={18} />
                </Button>
                <Button
                    size="sm"
                    color="gray"
                    className="col-span-1 flex items-center justify-center gap-1 p-2"
                    onClick={handleChat}
                >
                    <Icon icon="solar:chat-round-line-linear" width={18} />
                </Button>
                <Button
                    size="sm"
                    className="col-span-2 flex items-center justify-center bg-secondary hover:bg-secondaryemphasis"
                    onClick={handleDirectCheckout} // Memanggil event handler baru
                >
                    Beli
                </Button>
            </div>
        </div>
    );
};

export default CardProduk;
