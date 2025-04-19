<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    // Login hanya untuk role "karyawan"
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Cari user berdasarkan email
        $user = User::where('email', $request->email)->first();

        // Jika user tidak ditemukan
        if (!$user) {
            return response()->json(['message' => 'Email tidak ditemukan'], 404);
        }

        // Jika password salah
        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Password salah'], 401);
        }

        // Cek apakah user memiliki role "karyawan"
        if ($user->role !== 'karyawan') {
            return response()->json(['message' => 'Hanya karyawan yang bisa login'], 403);
        }

        // Buat token untuk user
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil',
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
{
    $request->user()->tokens()->delete();

    return response()->json([
        'success' => true, // Tambahkan key `success`
        'message' => 'Logout berhasil'
    ]);
}
}
