import Navbar from '@/Layouts/Buyer/Navbar';
import PaymentTabs from '@/Layouts/Buyer/PaymentTabs';
import { Head } from '@inertiajs/react';

export default function PaymentHistoryPage({
    belumBayar,
    menungguDiterima, // Properti baru
    telahDiterima, // Properti baru
    dibatalkan, // Diperbarui dari 'dibatalkan'
    user,
}) {
    return (
        <>
            <Head title="Riwayat Pembayaran" />
            <Navbar user={user} />
            <div className="mx-auto max-w-4xl p-4">
                <h1 className="font-bold mb-4 text-2xl">Riwayat Pembayaran</h1>
                <PaymentTabs
                    belumBayar={belumBayar}
                    menungguDiterima={menungguDiterima} // Mengirim properti baru
                    telahDiterima={telahDiterima} // Mengirim properti baru
                    dibatalkan={dibatalkan}
                />
            </div>
        </>
    );
}
