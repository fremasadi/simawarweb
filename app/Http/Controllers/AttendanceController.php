<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Attendance;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    public function checkIn(Request $request)
    {
        // Pastikan request memiliki QR Code yang benar
        if ($request->qr_code !== 'simawar') {
            return response()->json(['message' => 'QR Code tidak valid!'], 400);
        }

        // Dapatkan user yang sedang login berdasarkan token
        $user = Auth::user();

        // Cek apakah user sudah absen hari ini
        $today = Carbon::now()->toDateString();
        $attendance = Attendance::where('user_id', $user->id)->where('date', $today)->first();

        if ($attendance) {
            return response()->json(['message' => 'Anda sudah absen hari ini!'], 400);
        }

        // Simpan data absensi baru
        $attendance = Attendance::create([
            'user_id'   => $user->id,
            'date'      => $today,
            'check_in'  => Carbon::now()->format('H:i:s'),
            'status'    => 'present', // Bisa diubah sesuai kondisi
        ]);

        return response()->json([
            'message' => 'Absensi berhasil!',
            'data' => $attendance
        ], 201);
    }

    public function history()
{
    // Dapatkan user yang sedang login berdasarkan token
    $user = Auth::user();

    // Ambil riwayat absensi berdasarkan user_id, urutkan dari terbaru
    $history = Attendance::where('user_id', $user->id)
        ->orderBy('date', 'desc')
        ->get();

    // Jika tidak ada riwayat absensi
    if ($history->isEmpty()) {
        return response()->json(['message' => 'Belum ada riwayat absensi.'], 404);
    }

    return response()->json([
        'message' => 'Riwayat absensi ditemukan!',
        'data' => $history
    ], 200);
}

}
