<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Attendance;
use Carbon\Carbon;
use App\Models\StoreSetting;

class AttendanceController extends Controller
{
    // untuk absensi
    public function checkIn(Request $request)
{
    if ($request->qr_code !== 'simawar') {
        return response()->json(['message' => 'QR Code tidak valid!'], 400);
    }
    
    // Cek apakah hari ini Minggu
    if (Carbon::now()->isSunday()) {
        return response()->json(['message' => 'Absensi tidak diperbolehkan pada hari Minggu!'], 400);
    }
    
    $user = Auth::user();
    $today = Carbon::now()->toDateString();
    $attendance = Attendance::where('user_id', $user->id)
        ->where('date', $today)
        ->first();
    
    // Cek jika ini adalah permintaan check-out
    if ($request->has('type') && $request->type === 'check_out') {
        // Logika untuk check-out
        if (!$attendance) {
            return response()->json(['message' => 'Anda belum melakukan absen masuk hari ini!'], 400);
        }
        
        if ($attendance->check_out) {
            return response()->json(['message' => 'Anda sudah absen pulang hari ini!'], 400);
        }
        
        $storeSetting = StoreSetting::first();
        if (!$storeSetting) {
            return response()->json(['message' => 'Pengaturan toko tidak ditemukan!'], 500);
        }
        
        if (!$storeSetting->is_open) {
            return response()->json(['message' => 'Toko sedang tutup!'], 400);
        }
        
        $now = Carbon::now();
        
        // Update data absensi dengan waktu check out
        $attendance->check_out = $now->format('H:i:s');
        $attendance->save();
        
        \Artisan::call('salary:calculate-deductions');
        
        return response()->json([
            'message' => 'Absen pulang berhasil!',
            'data' => $attendance
        ], 200);
    }
    
    // Logika untuk check-in (kode yang sudah ada)
    if ($attendance) {
        return response()->json(['message' => 'Anda sudah absen hari ini!'], 400);
    }
    
    $storeSetting = StoreSetting::first();
    if (!$storeSetting) {
        return response()->json(['message' => 'Pengaturan toko tidak ditemukan!'], 500);
    }
    
    if (!$storeSetting->is_open) {
        return response()->json(['message' => 'Toko sedang tutup!'], 400);
    }
    
    $now = Carbon::now();
    $openTimeToday = Carbon::parse($today . ' ' . $storeSetting->open_time);
    $closeTimeToday = Carbon::parse($today . ' ' . $storeSetting->close_time);
    
    if ($closeTimeToday->lt($openTimeToday)) {
        $closeTimeToday->addDay();
    }
    
    if ($now->lt($openTimeToday)) {
        return response()->json([
            'message' => 'Toko belum buka! Jam buka toko adalah ' . $storeSetting->open_time
        ], 400);
    }
    
    if ($now->gt($closeTimeToday)) {
        return response()->json([
            'message' => 'Toko sudah tutup! Jam operasional toko adalah ' . 
                         $storeSetting->open_time . ' - ' . $storeSetting->close_time
        ], 400);
    }
    
    $lateMinutes = 0;
    if ($now->gt($openTimeToday)) {
        $lateMinutes = $openTimeToday->diffInMinutes($now);
    }
    
    $attendance = Attendance::create([
        'user_id'       => $user->id,
        'date'          => $today,
        'check_in'      => $now->format('H:i:s'),
        'status'        => $lateMinutes > 0 ? 'telat' : 'hadir',
        'late_minutes'  => $lateMinutes,
    ]);
    
    \Artisan::call('salary:calculate-deductions');
    
    return response()->json([
        'message' => 'Absensi berhasil!',
        'data' => $attendance
    ], 201);
}
    
    // untuk tampilkan absensi

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
