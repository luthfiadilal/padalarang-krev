<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Penjual;
use Illuminate\Http\Request;
use App\Models\BuktiPembayaran;
use Illuminate\Validation\Rules;
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
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => 'required|in:buyer,seller', // pastikan role dikirim
            'nama_toko' => 'required_if:role,seller|string|max:255',
            'no_hp' => 'required_if:role,seller|string|max:20',
            'kota' => 'required_if:role,seller|string|max:255',
            'kecamatan' => 'required_if:role,seller|string|max:255',
            'kelurahan' => 'required_if:role,seller|string|max:255',
            'bukti_pembayaran' => [
                'nullable',
                'required_if:role,seller',
                'image',
                'max:2048',
            ],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role, // bisa seller atau buyer
        ]);

        // Jika seller, buat profil penjual juga
        if ($request->role === 'seller') {
            $no_hp = $request->no_hp;
            if ($no_hp && str_starts_with($no_hp, '0')) {
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

            if ($request->hasFile('bukti_pembayaran')) {
                $buktiPath = $request->file('bukti_pembayaran')->store('bukti_pembayaran', 'public');

                BuktiPembayaran::create([
                    'penjual_id' => $penjual->id,
                    'bukti_pembayaran' => $buktiPath,
                ]);
            }
        }

        event(new Registered($user));

        // Auth::login($user);

        return redirect(route('login', absolute: false));
    }
}
