<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Models\OrderBonus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Salary;

class OrdersController extends Controller
{
    // mengambil data order bedasarjkan token
    public function index(Request $request)
    {
        $user = $request->user();

        $hasOngoingOrder = Order::where('ditugaskan_ke', $user->id)
            ->where('status', 'dikerjakan')
            ->exists();

        if ($hasOngoingOrder) {
            return response()->json([
                'success' => false,
                'message' => 'Mohon selesaikan pesanan Anda dulu sebelum mengambil order baru.',
            ], 403);
        }

        $orders = Order::with(['sizeModel'])
            ->where('status', 'ditugaskan')
            ->get();

        $orders = $orders->map(function ($order) {
            $imageUrls = collect($order->images ?? [])->map(function ($img) {
                return is_array($img) && isset($img['photo']) ? asset('storage/' . $img['photo']) : null;
            })->filter()->values()->all();

            return [
                'id' => $order->id,
                'name' => $order->name,
                'address' => $order->address,
                'deadline' => $order->deadline,
                'phone' => $order->phone,
                'images' => $imageUrls,
                'quantity' => $order->quantity,
                'size_model' => optional($order->sizeModel)->name,
                'size' => $order->size,
                'status' => $order->status,
                'ditugaskan_ke' => $order->ditugaskan_ke,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Data orders berhasil diambil',
            'data' => $orders,
        ]);
    }

    // mengambil pesanan bedasarkan karyawan
    public function takeOrder($id)
{
    $user = Auth::user();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    $order = Order::find($id);

    if (!$order) {
        return response()->json([
            'success' => false,
            'message' => 'Order tidak ditemukan'
        ], 404);
    }

    if ($order->status !== 'ditugaskan') {
        return response()->json([
            'success' => false,
            'message' => 'Order ini sudah diambil atau selesai'
        ], 400);
    }

    $order->status = 'dikerjakan';
    $order->ditugaskan_ke = $user->id;
    $order->save();

    // Kirim WA lewat Fonnte
    $phone = $order->phone;
    $message = "Pesanan Anda sedang diproses oleh tim penjahit kami di Rumah Jahit Mawar.";

    try {
        $response = Http::withHeaders([
            'Authorization' => 'R5uHqhjeppTQbDefuzxY', // Ganti dengan token asli kamu
        ])->post('https://api.fonnte.com/send', [
            'target' => $phone,
            'message' => $message,
            'countryCode' => '62',
        ]);

        if ($response->failed()) {
            logger()->error('Gagal kirim WA: ' . $response->body());
        }
    } catch (\Exception $e) {
        logger()->error('Error kirim WA: ' . $e->getMessage());
    }

    return response()->json([
        'success' => true,
        'message' => 'Order berhasil diambil dan sedang dikerjakan',
        'data' => $order
    ]);
}

//mengambil data pesanan karyawan prosess
    public function getOngoingOrders(Request $request)
    {
        $user = $request->user();

        $orders = Order::with(['sizeModel'])
            ->where('ditugaskan_ke', $user->id)
            ->where('status', 'dikerjakan')
            ->get();

        if ($orders->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Tidak ada order yang sedang dikerjakan.'
            ]);
        }

        $formattedOrders = $orders->map(function ($order) {
            $imageUrls = collect($order->images ?? [])->map(function ($img) {
                return is_array($img) && isset($img['photo']) ? asset('storage/' . $img['photo']) : null;
            })->filter()->values()->all();

            return [
                'id' => $order->id,
                'name' => $order->name,
                'address' => $order->address,
                'deadline' => $order->deadline,
                'phone' => $order->phone,
                'images' => $imageUrls,
                'quantity' => $order->quantity,
                'size_model' => optional($order->sizeModel)->name,
                'size' => $order->size,
                'status' => $order->status,
                'ditugaskan_ke' => $order->ditugaskan_ke,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Order yang sedang dikerjakan ditemukan.',
            'data' => $formattedOrders
        ]);
    }

    //jumlah pesanan yang diselesaikan
    public function countCompletedOrders(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan.'
            ], 404);
        }

        $completedOrdersCount = Order::where('ditugaskan_ke', $user->id)
            ->where('status', 'selesai')
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'Jumlah pesanan selesai berhasil dihitung.',
            'total_completed_orders' => $completedOrdersCount
        ]);
    }

    //data pesanan yang selesai
    public function getCompletedOrders(Request $request)
{
    $user = $request->user();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'User tidak ditemukan.'
        ], 404);
    }

    $orders = Order::with(['sizeModel'])
        ->where('ditugaskan_ke', $user->id)
        ->where('status', 'selesai')
        ->orderBy('created_at', 'desc') // Urutkan descending
        ->get();

    if ($orders->isEmpty()) {
        return response()->json([
            'success' => true,
            'message' => 'Tidak ada pesanan yang telah selesai.',
            'data' => []
        ]);
    }

    $formattedOrders = $orders->map(function ($order) {
        $imageUrls = collect($order->images ?? [])->map(function ($img) {
            return is_array($img) && isset($img['photo']) ? asset('storage/' . $img['photo']) : null;
        })->filter()->values()->all();

        return [
            'id' => $order->id,
            'name' => $order->name,
            'address' => $order->address,
            'deadline' => $order->deadline,
            'phone' => $order->phone,
            'images' => $imageUrls,
            'quantity' => $order->quantity,
            'size_model' => optional($order->sizeModel)->name,
            'size' => $order->size,
            'status' => $order->status,
            'ditugaskan_ke' => $order->ditugaskan_ke,
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
        ];
    });

    return response()->json([
        'success' => true,
        'message' => 'Data pesanan selesai berhasil diambil.',
        'data' => $formattedOrders
    ]);
}
public function completeOrder(Request $request, $id)
{
    $user = $request->user();

    $order = Order::where('id', $id)
        ->where('ditugaskan_ke', $user->id)
        ->where('status', 'dikerjakan')
        ->firstOrFail(); // Gunakan firstOrFail agar error 404 jika tidak ketemu

    // Tandai order selesai
    $order->status = 'selesai';
    $order->save();

    $bonus = 0.15 * floatval($order->price);

    DB::transaction(function () use ($user, $bonus, $order) {
        // Ambil pengaturan gaji
        $salarySetting = \App\Models\SalarySetting::firstOrFail();

        // Set tanggal gajian ke tanggal 2 bulan depan
        $payDate = Carbon::now()->addMonth()->day(2)->toDateString();

        $salary = Salary::where('user_id', $user->id)
            ->where('pay_date', $payDate)
            ->first();

        if (!$salary) {
            $baseSalary = $salarySetting->salary;
            $salary = Salary::create([
                'user_id'           => $user->id,
                'salary_setting_id' => $salarySetting->id,
                'base_salary'       => $baseSalary,
                'total_salary'      => $baseSalary + $bonus,
                'total_deduction'   => 0,
                'status'            => 'pending',
                'pay_date'          => $payDate,
            ]);
        } else {
            $salary->total_salary += $bonus;
            $salary->save();
        }

        OrderBonus::create([
            'order_id'     => $order->id,
            'user_id'      => $user->id,
            'salary_id'    => $salary->id,
            'bonus_amount' => $bonus,
        ]);
    });

    // Kirim notifikasi WA
    try {
        $response = Http::withHeaders([
            'Authorization' => 'R5uHqhjeppTQbDefuzxY',
        ])->post('https://api.fonnte.com/send', [
            'target'      => $order->phone,
            'message'     => "Pesanan Anda di Rumah Jahit Mawar telah selesai dan sudah bisa diambil. Terima kasih atas kepercayaan Anda!",
            'countryCode' => '62',
        ]);

        if ($response->failed()) {
            logger()->error('Gagal kirim WA (selesai): ' . $response->body());
        }
    } catch (\Exception $e) {
        logger()->error('Error kirim WA (selesai): ' . $e->getMessage());
    }

    return response()->json([
        'success' => true,
        'message' => 'Pesanan berhasil diselesaikan dan bonus ditambahkan.',
        'data'    => $order->toArray(),
    ]);
}




}
