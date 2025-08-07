import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: 'buyer',
        nama_toko: '',
        no_hp: '',
        bukti_pembayaran: null,
    });

    const handleChange = (e) => {
        const { name, type, value, files } = e.target;
        if (type === 'file') {
            setData(name, files[0]); // <== PASTIKAN INI ADA
        } else {
            setData(name, value);
        }
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('register'), {
            forceFormData: true,
            onFinish: () =>
                reset('password', 'password_confirmation', 'bukti_pembayaran'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Register" />

            <form
                onSubmit={submit}
                className="font-manropeMedium text-textgray"
            >
                {/* Name */}
                <div>
                    <InputLabel htmlFor="name" value="Name" />
                    <TextInput
                        id="name"
                        name="name"
                        value={data.name}
                        className="mt-1 block w-full"
                        onChange={handleChange}
                        required
                    />
                    <InputError message={errors.name} className="mt-2" />
                </div>

                {/* Email */}
                <div className="mt-4">
                    <InputLabel htmlFor="email" value="Email" />
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full"
                        onChange={handleChange}
                        required
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                {/* Password */}
                <div className="mt-4">
                    <InputLabel htmlFor="password" value="Password" />
                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        onChange={handleChange}
                        required
                    />
                    <InputError message={errors.password} className="mt-2" />
                </div>

                {/* Confirm Password */}
                <div className="mt-4">
                    <InputLabel
                        htmlFor="password_confirmation"
                        value="Confirm Password"
                    />
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        className="mt-1 block w-full"
                        onChange={handleChange}
                        required
                    />
                    <InputError
                        message={errors.password_confirmation}
                        className="mt-2"
                    />
                </div>

                {/* Role */}
                <div className="mt-4">
                    <InputLabel htmlFor="role" value="Daftar Sebagai" />
                    <select
                        id="role"
                        name="role"
                        value={data.role}
                        onChange={handleChange}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                    >
                        <option value="buyer">Buyer</option>
                        <option value="seller">Seller</option>
                    </select>
                    <InputError message={errors.role} className="mt-2" />
                </div>

                {/* Tambahan Seller */}
                {data.role === 'seller' && (
                    <>
                        {/* Nama Toko */}
                        <div className="mt-4">
                            <InputLabel htmlFor="nama_toko" value="Nama Toko" />
                            <TextInput
                                id="nama_toko"
                                name="nama_toko"
                                value={data.nama_toko}
                                className="mt-1 block w-full"
                                onChange={handleChange}
                            />
                            <InputError
                                message={errors.nama_toko}
                                className="mt-2"
                            />
                        </div>

                        {/* No HP */}
                        <div className="mt-4">
                            <InputLabel htmlFor="no_hp" value="No. HP" />
                            <TextInput
                                id="no_hp"
                                name="no_hp"
                                value={data.no_hp}
                                className="mt-1 block w-full"
                                onChange={handleChange}
                            />
                            <InputError
                                message={errors.no_hp}
                                className="mt-2"
                            />
                        </div>

                        {/* Bukti Pembayaran */}
                        <div className="mt-4">
                            <InputLabel
                                htmlFor="bukti_pembayaran"
                                value="Bukti Pembayaran"
                            />
                            <input
                                type="file"
                                id="bukti_pembayaran"
                                name="bukti_pembayaran"
                                accept="image/*"
                                onChange={handleChange}
                                className="mt-1 block w-full"
                            />
                            <InputError
                                message={errors.bukti_pembayaran}
                                className="mt-2"
                            />
                        </div>

                        {/* Keterangan Pembayaran */}
                        <div className="mt-4 rounded bg-gray-100 p-4">
                            <p className="mt-1 text-sm">
                                Harga registrasi sebesar{' '}
                                <strong>Rp20.000</strong>.
                            </p>
                            <p className="text-sm">
                                Silakan hubungi admin melalui WhatsApp:{' '}
                                <a
                                    href="https://wa.me/6281395735740"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="hover:text-primary-dark text-primary underline"
                                >
                                    0813-9573-5740
                                </a>
                            </p>
                        </div>
                    </>
                )}

                {/* Action Buttons */}
                <div className="mt-6 flex items-center justify-end">
                    <Link
                        href={route('login')}
                        className="rounded-md text-sm text-gray-600 underline hover:text-gray-900"
                    >
                        Already registered?
                    </Link>

                    <PrimaryButton className="ms-4" disabled={processing}>
                        Register
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
