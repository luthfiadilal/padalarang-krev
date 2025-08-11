import { useState } from 'react';
import PaymentCardGroup from './PaymentCardGroup';

export default function PaymentTabs({
    belumBayar,
    menungguDiterima,
    telahDiterima,
    dibatalkan,
}) {
    const [activeTab, setActiveTab] = useState('belumBayar');

    const tabStyle =
        'px-4 py-2 text-sm font-semibold rounded-t-md transition-all duration-200';
    const activeStyle = 'bg-primary text-white shadow-md';
    const inactiveStyle =
        'bg-gray-100 text-textgray hover:bg-primary hover:text-white';

    return (
        <div className="w-full">
            {/* Tab Header */}
            <div className="flex gap-2 overflow-x-auto border-b border-gray-200 pb-2">
                <button
                    className={`${tabStyle} ${
                        activeTab === 'belumBayar' ? activeStyle : inactiveStyle
                    }`}
                    onClick={() => setActiveTab('belumBayar')}
                >
                    Belum Bayar
                </button>
                <button
                    className={`${tabStyle} ${
                        activeTab === 'menungguDiterima'
                            ? activeStyle
                            : inactiveStyle
                    }`}
                    onClick={() => setActiveTab('menungguDiterima')}
                >
                    Menunggu Diterima
                </button>
                <button
                    className={`${tabStyle} ${
                        activeTab === 'telahDiterima'
                            ? activeStyle
                            : inactiveStyle
                    }`}
                    onClick={() => setActiveTab('telahDiterima')}
                >
                    Telah Diterima
                </button>
                <button
                    className={`${tabStyle} ${
                        activeTab === 'dibatalkan' ? activeStyle : inactiveStyle
                    }`}
                    onClick={() => setActiveTab('dibatalkan')}
                >
                    Dibatalkan
                </button>
            </div>

            {/* Tab Content */}
            <div className="mt-4">
                {activeTab === 'belumBayar' &&
                    (belumBayar.length === 0 ? (
                        <p className="text-sm italic text-textgray">
                            Tidak ada transaksi yang belum dibayar.
                        </p>
                    ) : (
                        belumBayar.map((trx) => (
                            <PaymentCardGroup
                                key={trx.id}
                                transaksi={trx}
                                status="belumBayar"
                            />
                        ))
                    ))}

                {activeTab === 'menungguDiterima' &&
                    (menungguDiterima.length === 0 ? (
                        <p className="text-sm italic text-textgray">
                            Tidak ada transaksi yang sedang menunggu.
                        </p>
                    ) : (
                        menungguDiterima.map((trx) => (
                            <PaymentCardGroup
                                key={trx.id}
                                transaksi={trx}
                                status="menungguDiterima"
                            />
                        ))
                    ))}

                {activeTab === 'telahDiterima' &&
                    (telahDiterima.length === 0 ? (
                        <p className="text-sm italic text-textgray">
                            Tidak ada transaksi yang telah diterima.
                        </p>
                    ) : (
                        telahDiterima.map((trx) => (
                            <PaymentCardGroup
                                key={trx.id}
                                transaksi={trx}
                                status="telahDiterima"
                            />
                        ))
                    ))}

                {activeTab === 'dibatalkan' &&
                    (dibatalkan.length === 0 ? (
                        <p className="text-sm italic text-textgray">
                            Tidak ada transaksi yang dibatalkan.
                        </p>
                    ) : (
                        dibatalkan.map((trx) => (
                            <PaymentCardGroup
                                key={trx.id}
                                transaksi={trx}
                                status="dibatalkan"
                            />
                        ))
                    ))}
            </div>
        </div>
    );
}
