<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrdersController extends Controller
{
    public function index(Request $request)
    {
        // Ambil user yang sedang login
        $user = $request->user();

        // Cek apakah user memiliki order dengan status "dikerjakan"
        $hasOngoingOrder = Order::where('ditugaskan_ke', $user->id)
            ->where('status', 'dikerjakan')
            ->exists(); // Cek apakah ada order seperti itu

        if ($hasOngoingOrder) {
            return response()->json([
                'success' => false,
                'message' => 'Mohon selesaikan pesanan Anda dulu sebelum mengambil order baru.',
            ], 403);
        }

        // Ambil semua order dengan status "ditugaskan" dan relasi ke SizeModel
        // Tidak perlu relasi image lagi karena menggunakan images JSON
        $orders = Order::with(['sizeModel'])
            ->where('status', 'ditugaskan')
            ->get();

        // Format data agar images menjadi URL lengkap dan sizemodel_id menjadi nama
        $orders = $orders->map(function ($order) {
            // Prepare image URLs
            $imageUrls = [];
            if (!empty($order->images) && is_array($order->images)) {
                foreach ($order->images as $path) {
                    $imageUrls[] = asset('storage/' . $path);
                }
            }

            return [
                'id' => $order->id,
                'name' => $order->name,
                'address' => $order->address,
                'deadline' => $order->deadline,
                'phone' => $order->phone,
                'images' => $imageUrls, // Array URL lengkap gambar
                'quantity' => $order->quantity,
                'size_model' => $order->sizeModel ? $order->sizeModel->name : null, // Nama size model
                'size' => $order->size,
                'status' => $order->status,
                'ditugaskan_ke' => $order->ditugaskan_ke,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Data orders berhasil diambil',
            'data' => $orders
        ], 200);
    }

    public function takeOrder($id)
    {
        // Ambil user yang sedang login berdasarkan token
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // Cari order berdasarkan ID
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order tidak ditemukan'
            ], 404);
        }

        // Pastikan order masih dalam status "ditugaskan"
        if ($order->status !== 'ditugaskan') {
            return response()->json([
                'success' => false,
                'message' => 'Order ini sudah diambil atau selesai'
            ], 400);
        }

        // Update status menjadi "dikerjakan" dan set user yang mengambilnya
        $order->status = 'dikerjakan';
        $order->ditugaskan_ke = $user->id;
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Order berhasil diambil dan sedang dikerjakan',
            'data' => $order
        ], 200);
    }

    public function getOngoingOrders(Request $request)
    {
        // Ambil user yang sedang login
        $user = $request->user();

        // Cari order dengan status "dikerjakan" berdasarkan ditugaskan_ke (user_id)
        // Ganti relasi image dengan sizeModel saja
        $orders = Order::with(['sizeModel'])
                    ->where('ditugaskan_ke', $user->id)
                    ->where('status', 'dikerjakan')
                    ->get();

        // Jika tidak ada pesanan "dikerjakan"
        if ($orders->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Tidak ada order yang sedang dikerjakan.'
            ], 200);
        }

        // Format data agar sesuai kebutuhan
        $formattedOrders = $orders->map(function ($order) {
            // Prepare image URLs
            $imageUrls = [];
            if (!empty($order->images) && is_array($order->images)) {
                foreach ($order->images as $path) {
                    $imageUrls[] = asset('storage/' . $path);
                }
            }

            return [
                'id' => $order->id,
                'name' => $order->name,
                'address' => $order->address,
                'deadline' => $order->deadline,
                'phone' => $order->phone,
                'images' => $imageUrls, // Array URL lengkap gambar
                'quantity' => $order->quantity,
                'size_model' => $order->sizeModel ? $order->sizeModel->name : null, // Nama size model
                'size' => $order->size,
                'status' => $order->status,
                'ditugaskan_ke' => $order->ditugaskan_ke,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Order yang sedang dikerjakan ditemukan.',
            'data' => $formattedOrders
        ], 200);
    }

    public function countCompletedOrders(Request $request)
    {
        // Ambil user yang sedang login berdasarkan token
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan.'
            ], 404);
        }

        // Hitung jumlah pesanan dengan status "selesai" yang ditugaskan ke user
        $completedOrdersCount = Order::where('ditugaskan_ke', $user->id)
                                    ->where('status', 'selesai')
                                    ->count();

        return response()->json([
            'success' => true,
            'message' => 'Jumlah pesanan selesai berhasil dihitung.',
            'total_completed_orders' => $completedOrdersCount
        ], 200);
    }

    public function getCompletedOrders(Request $request)
    {
        // Ambil user yang sedang login
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan.'
            ], 404);
        }

        // Ambil semua order dengan status "selesai" berdasarkan user yang login
        // Ganti relasi image dengan sizeModel saja
        $orders = Order::with(['sizeModel'])
                    ->where('ditugaskan_ke', $user->id)
                    ->where('status', 'selesai')
                    ->get();

        // Jika tidak ada order yang selesai
        if ($orders->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Tidak ada pesanan yang telah selesai.',
                'data' => []
            ], 200);
        }

        // Format data
        $formattedOrders = $orders->map(function ($order) {
            // Prepare image URLs
            $imageUrls = [];
            if (!empty($order->images) && is_array($order->images)) {
                foreach ($order->images as $path) {
                    $imageUrls[] = asset('storage/' . $path);
                }
            }

            return [
                'id' => $order->id,
                'name' => $order->name,
                'address' => $order->address,
                'deadline' => $order->deadline,
                'phone' => $order->phone,
                'images' => $imageUrls, // Array URL lengkap gambar
                'quantity' => $order->quantity,
                'size_model' => $order->sizeModel ? $order->sizeModel->name : null, // Nama size model
                'size' => $order->size,
                'status' => $order->status,
                'ditugaskan_ke' => $order->ditugaskan_ke,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Data pesanan selesai berhasil diambil.',
            'data' => $formattedOrders
        ], 200);
    }
}