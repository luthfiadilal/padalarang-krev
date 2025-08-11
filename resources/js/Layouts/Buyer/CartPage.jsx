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

    const groupedCarts = groupByPenjual(carts || []);

    const handleCombinedCheckout = () => {
        if (selected.length === 0) {
            alert('Tidak ada produk yang dipilih.');
            return;
        }

        router.get(route('buyer.checkout.form'), { 'cart_ids[]': selected });
    };

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
                    <span className="font-semibold">Pilih Semua</span>
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
                            if (ids.length === 0) {
                                // Ganti alert dengan modal UI custom
                                // alert('Pilih produk terlebih dahulu!');
                                return;
                            }
                            router.get(route('buyer.checkout.form'), {
                                cart_ids: ids,
                            });
                        }}
                    />
                ))}
            </div>

            {/* Tombol checkout gabungan di luar loop */}
            <div className="fixed bottom-0 left-0 right-0 mx-auto max-w-7xl bg-white p-4 shadow-lg">
                <div className="mx-auto flex max-w-7xl items-center justify-between">
                    <div className="flex-1">
                        <span className="font-semibold text-lg">
                            Total Dipilih: {selected.length} item
                        </span>
                    </div>
                    <button
                        onClick={handleCombinedCheckout}
                        disabled={selected.length === 0}
                        className={`font-semibold w-1/3 rounded-md px-4 py-2 text-white transition duration-200 ease-in-out ${selected.length > 0 ? 'bg-indigo-600 hover:bg-indigo-700' : 'cursor-not-allowed bg-gray-400'}`}
                    >
                        Checkout Semua ({selected.length})
                    </button>
                </div>
            </div>
        </div>
    );
}
