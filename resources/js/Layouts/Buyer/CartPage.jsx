import { router } from '@inertiajs/react';
import React from 'react';
import CartSellerGroup from './CartSellerGroup';

export default function CartPage({ carts, onUpdateQty, onRemoveItem }) {
    const [selected, setSelected] = React.useState([]);

    const toggleSelect = (id) => {
        setSelected((prev) =>
            prev.includes(id)
                ? prev.filter((sid) => sid !== id)
                : [...prev, id],
        );
    };

    const allSelected = carts.length > 0 && selected.length === carts.length;

    // Helper untuk mengelompokkan carts berdasarkan penjual_id
    const groupByPenjual = (items) => {
        return items.reduce((acc, item) => {
            const key = item.penjual_id;
            if (!acc[key]) acc[key] = [];
            acc[key].push(item);
            return acc;
        }, {});
    };

    const groupedCarts = groupByPenjual(carts);

    return (
        <div className="mx-auto max-w-7xl p-4">
            <h2 className="font-bold mb-4 text-xl">Keranjang Belanja</h2>
            <div className="rounded bg-white">
                <div className="flex items-center border-b p-4">
                    <input
                        type="checkbox"
                        checked={allSelected}
                        onChange={() =>
                            allSelected
                                ? setSelected([])
                                : setSelected(carts.map((c) => c.id))
                        }
                        className="mr-2 rounded-[4px] checked:bg-primary focus:outline-none focus:ring-0 focus:ring-offset-0"
                    />
                    <span className="font-semibold">Produk</span>
                </div>

                {Object.entries(groupedCarts).map(([penjualId, cartsGroup]) => (
                    <CartSellerGroup
                        key={penjualId}
                        carts={cartsGroup}
                        selected={selected}
                        toggleSelect={toggleSelect}
                        onUpdateQty={onUpdateQty}
                        onRemoveItem={onRemoveItem}
                        onCheckoutGroup={(ids) => {
                            // Bisa arahkan ke halaman checkout atau simpan ke state global
                            // Arahkan ke halaman form checkout dengan parameter cart_ids
                            if (ids.length === 0) {
                                alert('Pilih produk terlebih dahulu!');
                                return;
                            }

                            router.get(route('buyer.checkout.form'), {
                                cart_ids: ids,
                            });
                        }}
                    />
                ))}
            </div>
        </div>
    );
}
