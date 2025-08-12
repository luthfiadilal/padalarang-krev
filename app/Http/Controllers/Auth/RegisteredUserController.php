<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Penjual;
use Illuminate\Http\Request;
use App\Models\BuktiPembayaran;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Auth\Events\Registered;


class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */


    public function store(Request $request): RedirectResponse
    {
        Log::info('Mencoba registrasi user dengan data:', $request->all());
        // Mendefinisikan aturan validasi secara terpisah
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', 'in:buyer,seller'],
        ];

        // Tambahkan aturan validasi khusus untuk role 'seller'
        if ($request->role === 'seller') {
            $rules = array_merge($rules, [
                'nama_toko' => ['required', 'string', 'max:255'],
                'no_hp' => ['required', 'string', 'max:20'],
                'kota' => ['required', 'string', 'max:255'],
                'kecamatan' => ['required', 'string', 'max:255'],
                'kelurahan' => ['required', 'string', 'max:255'],
                'bukti_pembayaran' => ['required', 'image', 'max:2048'],
            ]);
        }

        // Jalankan validasi
        $request->validate($rules);

        // Buat user baru
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        // Jika role-nya seller, buat profil penjual
        if ($user->role === 'seller') {
            $this->createSellerProfile($request, $user);
        }

        Log::info('User baru berhasil dibuat.', ['user_id' => $user->id, 'email' => $user->email, 'role' => $user->role]);

        event(new Registered($user));

        // Auth::login($user);

        return redirect(route('login', absolute: false));
    }

    protected function createSellerProfile(Request $request, User $user): void
    {
        $no_hp = $request->no_hp;

        // Memformat nomor HP jika dimulai dengan '0'
        if (str_starts_with($no_hp, '0')) {
            $no_hp = '62' . substr($no_hp, 1);
        }

        $penjual = Penjual::create([
            'id_penjual' => $user->id,
            'nama_toko' => $request->nama_toko,
            'no_hp' => $no_hp,
            'whatsapp_link' => $no_hp ? 'https://wa.me/' . $no_hp : null,
            'kota' => $request->kota,
            'kecamatan' => $request->kecamatan,
            'kelurahan' => $request->kelurahan,
            'alamat' => "",
            'foto_profil' => "",
            'is_active' => 0,
            'kategori_bisnis' => "null",
        ]);

        // Unggah dan simpan bukti pembayaran jika ada
        if ($request->hasFile('bukti_pembayaran')) {
            $buktiPath = $request->file('bukti_pembayaran')->store('bukti_pembayaran', 'public');
            BuktiPembayaran::create([
                'penjual_id' => $penjual->id,
                'bukti_pembayaran' => $buktiPath,
            ]);
        }
    }
}
