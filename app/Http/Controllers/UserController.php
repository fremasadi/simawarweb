<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserController extends Controller
{
    public function getUserProfile(Request $request)
{
    // Ambil user yang sedang login berdasarkan token
    $user = $request->user();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'User tidak ditemukan.'
        ], 404);
    }

    return response()->json([
        'success' => true,
        'message' => 'Data user ditemukan.',
        'data' => [
            'name' => $user->name,
            'email' => $user->email,
            'image' => $user->image ? asset('storage/' . $user->image) : null // URL gambar lengkap
        ]
    ], 200);
}

}
