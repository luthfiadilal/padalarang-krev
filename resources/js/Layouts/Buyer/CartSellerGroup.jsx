import CartItem from './CartItem';

export default function CartSellerGroup({
    carts,
    selected,
    toggleSelect,
    onUpdateQty,
    onRemoveItem,
    onCheckoutGroup, // fungsi dari parent
}) {
    const getImageProfile = (path) => {
        if (path?.startsWith('foto_profil/')) {
            return `/storage/${path}`;
        }
        return path || '/placeholder.jpg';
    };

    const totalHarga = carts
        .filter((cart) => selected.includes(cart.id))
        .reduce((sum, cart) => sum + Number(cart.harga_total || 0), 0);

    // Kumpulkan ID cart yang dipilih
    const selectedIds = carts
        .filter((cart) => selected.includes(cart.id))
        .map((cart) => cart.id);

    return (
        <div className="mt-4 rounded border p-4 shadow-sm">
            {/* Header penjual */}
            <div className="mb-4 flex items-center gap-2">
                <img
                    src={getImageProfile(carts[0]?.penjual?.foto_profil)}
                    alt="foto_profile"
                    className="h-10 w-10 rounded-full object-cover"
                />
                <h2 className="font-semibold">
                    {carts[0]?.penjual?.nama_toko ?? 'Toko Tidak Diketahui'}
                </h2>
            </div>

            {/* List produk */}
            {carts.map((cart) => (
                <CartItem
                    key={cart.id}
                    cart={cart}
                    checked={selected.includes(cart.id)}
                    toggleSelect={() => toggleSelect(cart.id)}
                    onUpdateQty={onUpdateQty}
                    onRemoveItem={onRemoveItem}
                />
            ))}

            {/* Footer grup - total dan checkout */}
            <div className="mt-4 flex items-center justify-between border-t pt-4">
                <div className="font-semibold text-lg text-gray-700">
                    Total: Rp {totalHarga.toLocaleString('id-ID')}
                </div>
                <button
                    className="rounded bg-blue-600 px-4 py-2 text-white disabled:bg-gray-400"
                    disabled={totalHarga === 0}
                    onClick={() => onCheckoutGroup?.(selectedIds)}
                >
                    Checkout
                </button>
            </div>
        </div>
    );
}
