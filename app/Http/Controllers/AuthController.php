<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'fcm_token' => 'required|string',
        ]);
    
        $user = User::where('email', $request->email)->first();
    
        if (!$user) {
            return response()->json(['message' => 'Email tidak ditemukan'], 404);
        }
    
        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Password salah'], 401);
        }
    
        if ($user->role !== 'karyawan') {
            return response()->json(['message' => 'Hanya karyawan yang bisa login'], 403);
        }
    
        // Langsung ganti fcm_token yang lama dengan yang baru
        $user->fcm_token = $request->fcm_token;
        $user->save();
    
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
