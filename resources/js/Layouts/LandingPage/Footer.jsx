import { Icon } from '@iconify/react';
import axios from 'axios';
import { useEffect } from 'react';

export default function Footer() {
    const developer = {
        name: 'Deyya',
        phone: '+62 813-9573-5740',
        phoneClean: '6281395735740',
    };

    useEffect(() => {
        axios
            .get('/api/elemen/Logo')
            .then((response) => {
                setLogo(response.data);
            })
            .catch((error) => {
                console.error('Error loading logo:', error);
            });
    }, []);

    return (
        <footer className="bg-[#F5F5F5] px-10 py-10 md:px-24">
            <div className="grid grid-cols-2 gap-6 md:grid-cols-5">
                {/* CUSTOMER SERVICE */}
                <div className="flex flex-col gap-2">
                    <h4 className="text-16 font-manropeBold text-secondary md:text-20">
                        CUSTOMER SERVICE
                    </h4>
                    <a
                        href="#"
                        className="text-12 font-manropeMedium text-textgray hover:underline md:text-16"
                    >
                        Resolution Center
                    </a>
                    <a
                        href="#"
                        className="text-12 font-manropeMedium text-textgray hover:underline md:text-16"
                    >
                        How to shop
                    </a>
                    <a
                        href="#"
                        className="text-12 font-manropeMedium text-textgray hover:underline md:text-16"
                    >
                        How to use
                    </a>
                    <a
                        href="#"
                        className="text-12 font-manropeMedium text-textgray hover:underline md:text-16"
                    >
                        Free shipping
                    </a>
                </div>

                {/* HELP */}
                <div className="flex flex-col gap-2">
                    <h4 className="text-16 font-manropeBold text-secondary md:text-20">
                        HELP
                    </h4>
                    <a
                        href="#"
                        className="text-12 font-manropeMedium text-textgray hover:underline md:text-16"
                    >
                        Privacy Policy
                    </a>
                    <a
                        href="#"
                        className="text-12 font-manropeMedium text-textgray hover:underline md:text-16"
                    >
                        Terms & Conditions
                    </a>
                    <a
                        href="#"
                        className="text-12 font-manropeMedium text-textgray hover:underline md:text-16"
                    >
                        Contact Us
                    </a>
                </div>

                {/* DEVELOPER HUB */}
                <div className="flex flex-col gap-2">
                    <h4 className="text-16 font-manropeBold text-secondary md:text-20">
                        DEVELOPER HUB
                    </h4>
                    <div className="space-y-2 text-12 font-manropeMedium leading-relaxed text-textgray md:text-16">
                        <p>
                            Dikembangkan oleh <b>{developer.name}</b>.
                        </p>
                        <p>
                            Wujudkan impian bisnismu! <br />
                            Bergabunglah sebagai <b>seller</b> dan mulai jangkau
                            lebih banyak pelanggan.
                        </p>
                    </div>

                    <a
                        href={`https://wa.me/${developer.phoneClean}`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="flex items-center gap-2 text-12 font-manropeMedium text-green-600 hover:underline md:text-16"
                    >
                        <Icon icon="logos:whatsapp-icon" width="20" />
                        {developer.phone}
                    </a>
                </div>
            </div>
        </footer>
    );
}
