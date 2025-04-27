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

    // Check if store is open based on is_open flag
    if (!$storeSetting->is_open) {
        return response()->json(['message' => 'Toko sedang tutup!'], 400);
    }

    // Get current time
    $currentTime = Carbon::now();
    $currentTimeString = $currentTime->format('H:i:s');
    
    // Parse store open and close times
    $openTime = Carbon::createFromFormat('H:i:s', $storeSetting->open_time);
    $closeTime = Carbon::createFromFormat('H:i:s', $storeSetting->close_time);
    
    // Check if current time is within store operating hours
    if ($currentTimeString < $storeSetting->open_time || $currentTimeString > $storeSetting->close_time) {
        return response()->json([
            'message' => 'Absensi hanya dapat dilakukan pada jam operasional toko (' . 
                         $storeSetting->open_time . ' - ' . $storeSetting->close_time . ')!'
        ], 400);
    }

    // Calculate late minutes based on store open time
    $openTimeToday = Carbon::today()->setTimeFromTimeString($storeSetting->open_time);
    $lateMinutes = 0;

    if ($currentTime->greaterThan($openTimeToday)) {
        $lateMinutes = round($openTimeToday->diffInMinutes($currentTime)); // Membulatkan hasil keterlambatan
    }

    $attendance = Attendance::create([
        'user_id'       => $user->id,
        'date'          => $today,
        'check_in'      => $currentTime->format('H:i:s'),
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
