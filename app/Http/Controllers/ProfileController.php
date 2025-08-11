<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Produk;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function index()
{
    $user = Auth::user()->load(['penjual', 'pembeli', 'admin']);

    if ($user->role === 'seller') {
        $penjual = $user->penjual;

        // Decode jasa_pengiriman ke array
        if (is_string($penjual->jasa_pengiriman)) {
            $penjual->jasa_pengiriman = json_decode($penjual->jasa_pengiriman, true) ?? [];
        }

        // Statistik produk & transaksi
        $penjualId = optional($penjual)->id;

        $totalProduk = Produk::where('penjual_id', $penjualId)->count();


        return Inertia::render('Seller/ProfilePage', [
            'user' => $user,
            'roleData' => $penjual,
            'totalProduk' => $totalProduk,
        ]);
    }

    // Lainnya tetap
    return match ($user->role) {
        'buyer' => Inertia::render('Buyer/ProfilePage', [
            'user' => $user,
            'roleData' => $user->pembeli
        ]),
        'admin' => Inertia::render('Admin/ProfilePage', [
            'user' => $user,
            'roleData' => $user->admin
        ]),
        default => abort(403, 'Unauthorized role'),
    };
}


    public function edit()
    {
        $user = Auth::user()->load(['penjual', 'pembeli', 'admin']);

        return match ($user->role) {
            'seller' => Inertia::render('Seller/EditPage', [
                'user' => $user,
                'roleData' => $user->penjual
            ]),
            'buyer' => Inertia::render('Buyer/EditPage', [
                'user' => $user,
                'roleData' => $user->pembeli
            ]),
            'admin' => Inertia::render('Admin/EditPage', [
                'user' => $user,
                'roleData' => $user->admin
            ]),
            default => abort(403, 'Unauthorized role'),
        };


    }


    /**
     * Update the user's profile information.
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => "required|email|unique:users,email,{$user->id}",
            'no_hp' => 'nullable|string|max:20',
            'alamat' => 'nullable|string|max:255',
            'foto_profil' => 'nullable|image|max:2048', // opsional
            'is_active' => 'required_if:role,seller|integer|in:0,1',
            'kota' => 'required_if:role,seller|string|max:255',
            'kecamatan' => 'required_if:role,seller|string|max:255',
            'kelurahan' => 'required_if:role,seller|string|max:255',
        ]);


        $no_hp = $request->no_hp;
        if ($no_hp && str_starts_with($no_hp, '0')) {
            $no_hp = '62' . substr($no_hp, 1);
        }

        $user->update($request->only('name', 'email'));

        $fotoProfilPath = null;
        if ($request->hasFile('foto_profil')) {
            $fotoProfilPath = $request->file('foto_profil')->store('foto_profil', 'public');
        }

        match ($user->role) {
            'seller' => $user->penjual()->updateOrCreate(
                ['id_penjual' => $user->id],
                [
                    'nama_toko' => $request->nama_toko,
                    'deskripsi' => $request->deskripsi,
                    'whatsapp_link' => $no_hp ? 'https://wa.me/' . $no_hp : null,
                    'no_hp' => $request->no_hp,
                    'alamat' => $request->alamat,
                    'foto_profil' => $fotoProfilPath ?? $user->penjual->foto_profil ?? null,
                    'is_active' => $request->is_active,
                    'kota' => $request->kota,
                    'kecamatan' => $request->kecamatan,
                    'kelurahan' => $request->kelurahan,
                    'kategori_bisnis' => $request->kategori_bisnis,
                ]
            ),
            'buyer' => $user->pembeli()->updateOrCreate(
                ['id_pembeli' => $user->id],
                [
                    'no_hp' => $request->no_hp,
                    'alamat' => $request->alamat,
                    'foto_profil' => $fotoProfilPath ?? $user->pembeli->foto_profil ?? null,
                ]
            ),
            'admin' => $user->admin()->updateOrCreate(
                ['id_admin' => $user->id],
                [
                    'no_hp' => $request->no_hp,
                    'alamat' => $request->alamat,
                    'foto_profil' => $fotoProfilPath ?? $user->admin->foto_profil ?? null,
                ]
            ),
        };

        return redirect()->route('profile.index')->with('message', 'Profil berhasil diperbarui.');
    }


    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/dashboard-seller');
    }
}
