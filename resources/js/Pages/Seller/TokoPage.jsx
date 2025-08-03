import DynamicHead from '@/Components/DynamicHead';
import ScrollToTop from '@/Components/shared/ScrollToTop';
import Header from '@/Layouts/Seller/Header/Header';
import ProductCard from '@/Layouts/Seller/ProductCard';
import SidebarLayout from '@/Layouts/Seller/Sidebar/Sidebar';
import { Icon } from '@iconify/react';
import { router, usePage } from '@inertiajs/react';

export default function TokoPage() {
    const { produkPerKategori, user, errorMessage } = usePage().props;

    return (
        <>
            <DynamicHead>
                <title>Toko</title>
            </DynamicHead>
            <div className="flex min-h-screen w-full dark:bg-darkgray">
                <SidebarLayout />
                <div className="page-wrapper flex w-full">
                    <div className="page-wrapper-sub flex w-full flex-col dark:bg-darkgray">
                        <Header user={user} />

                        <div className="h-full rounded-bb bg-lightgray dark:bg-dark">
                            <div className="w-full">
                                <ScrollToTop>
                                    <div className="container py-20">
                                        {errorMessage ? (
                                            <div className="flex flex-col items-center justify-center gap-4 py-24 text-center">
                                                <Icon
                                                    icon="solar:store-bold-duotone"
                                                    width="70"
                                                    className="text-secondary"
                                                />
                                                <p className="font-semibold text-lg text-gray-700 dark:text-gray-200">
                                                    {errorMessage}
                                                </p>
                                                <button
                                                    onClick={() =>
                                                        router.visit(
                                                            route(
                                                                'profile.edit',
                                                            ),
                                                        )
                                                    }
                                                    className="hover:bg-secondary/90 rounded-md bg-secondary px-5 py-2 text-white shadow transition-all"
                                                >
                                                    Buat Toko Sekarang
                                                </button>
                                            </div>
                                        ) : (
                                            Object.entries(
                                                produkPerKategori,
                                            ).map(
                                                ([kategoriId, produkList]) => {
                                                    const terlarisId =
                                                        produkList.reduce(
                                                            (max, p) =>
                                                                p.total_terjual >
                                                                max.total_terjual
                                                                    ? p
                                                                    : max,
                                                            produkList[0],
                                                        ).produk_id;

                                                    return (
                                                        <div
                                                            key={kategoriId}
                                                            className="mb-8 mt-[-50px] space-y-4"
                                                        >
                                                            <h2 className="font-bold text-xl text-gray-700 dark:text-white">
                                                                {
                                                                    produkList[0]
                                                                        .nama_kategori
                                                                }
                                                            </h2>
                                                            <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                                                                {produkList.map(
                                                                    (
                                                                        produk,
                                                                    ) => (
                                                                        <ProductCard
                                                                            key={
                                                                                produk.produk_id
                                                                            }
                                                                            product={{
                                                                                id: produk.produk_id,
                                                                                nama: produk.nama_produk,
                                                                                foto: produk.foto,
                                                                                harga:
                                                                                    produk.harga ??
                                                                                    0,
                                                                                harga_diskon:
                                                                                    produk.harga_diskon ??
                                                                                    null,
                                                                                ukuran:
                                                                                    produk.ukuran ??
                                                                                    null,
                                                                                stok:
                                                                                    produk.stok ??
                                                                                    null,
                                                                            }}
                                                                            isTerlaris={
                                                                                produk.produk_id ===
                                                                                terlarisId
                                                                            }
                                                                            onDeleteClick={() =>
                                                                                console.log(
                                                                                    'Delete',
                                                                                    produk.produk_id,
                                                                                )
                                                                            }
                                                                            onProductClick={() =>
                                                                                console.log(
                                                                                    'Clicked',
                                                                                    produk.produk_id,
                                                                                )
                                                                            }
                                                                        />
                                                                    ),
                                                                )}
                                                            </div>
                                                        </div>
                                                    );
                                                },
                                            )
                                        )}
                                    </div>
                                </ScrollToTop>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
