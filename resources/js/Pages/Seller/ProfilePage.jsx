import ScrollToTop from '@/Components/shared/ScrollToTop';
import Header from '../../Layouts/Seller/Header/Header';
import SidebarLayout from '../../Layouts/Seller/Sidebar/Sidebar';
import SellerProfile from '../Profile/SellerProfile';

export default function ProfilePage({ roleData, totalProduk }) {
    return (
        <>
            <div className="flex min-h-screen w-full dark:bg-darkgray">
                <SidebarLayout />
                <div className="page-wrapper flex w-full">
                    {/* Header/sidebar */}

                    <div className="page-wrapper-sub flex w-full flex-col dark:bg-darkgray">
                        {/* Top Header  */}
                        <Header />

                        <div
                            className={`h-full rounded-bb bg-lightgray dark:bg-dark`}
                        >
                            {/* Body Content  */}
                            <div className={`w-full`}>
                                <ScrollToTop>
                                    <div className="container py-30">
                                        <SellerProfile
                                            roleData={roleData}
                                            totalProduk={totalProduk}
                                        />
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
