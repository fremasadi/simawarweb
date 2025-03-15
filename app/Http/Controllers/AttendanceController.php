<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Attendance;
use Carbon\Carbon;
use App\Models\StoreSetting;

class AttendanceController extends Controller
{
    public function checkIn(Request $request)
{
    if ($request->qr_code !== 'simawar') {
        return response()->json(['message' => 'QR Code tidak valid!'], 400);
    }

    $user = Auth::user();
    $today = Carbon::now()->toDateString();
    $attendance = Attendance::where('user_id', $user->id)->where('date', $today)->first();

    if ($attendance) {
        return response()->json(['message' => 'Anda sudah absen hari ini!'], 400);
    }

    $storeSetting = StoreSetting::first();
    if (!$storeSetting) {
        return response()->json(['message' => 'Pengaturan toko tidak ditemukan!'], 500);
    }

    $openTime = Carbon::today()->setTimeFromTimeString($storeSetting->open_time);
    $checkInTime = Carbon::now();
    $lateMinutes = 0;

    if ($checkInTime->greaterThan($openTime)) {
        $lateMinutes = round($openTime->diffInMinutes($checkInTime)); // 🔥 Membulatkan hasil keterlambatan
    }

    $attendance = Attendance::create([
        'user_id'       => $user->id,
        'date'          => $today,
        'check_in'      => $checkInTime->format('H:i:s'),
        'status'        => $lateMinutes > 0 ? 'telat' : 'hadir',
        'late_minutes'  => $lateMinutes,
    ]);

    return response()->json([
        'message' => 'Absensi berhasil!',
        'data' => $attendance
    ], 201);
}

public function history(Request $request)
{
    // Dapatkan user yang sedang login berdasarkan token
    $user = Auth::user();

    // Ambil parameter start_date dan end_date dari request
    $startDate = $request->input('start_date');
    $endDate = $request->input('end_date');

    // Query dasar untuk mendapatkan riwayat absensi
    $query = Attendance::where('user_id', $user->id)
        ->orderBy('date', 'desc');

    // Filter berdasarkan rentang tanggal jika start_date dan end_date disertakan
    if ($startDate && $endDate) {
        $query->whereBetween('date', [$startDate, $endDate]);
    }

    // Eksekusi query
    $history = $query->get();

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
