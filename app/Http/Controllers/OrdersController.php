<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrdersController extends Controller
{
    /**
     * Format data order untuk response JSON.
     */
    private function formatOrder($order)
    {
        return [
            'id' => $order->id,
            'name' => $order->name,
            'address' => $order->address,
            'deadline' => $order->deadline,
            'phone' => $order->phone,
            'images' => collect($order->images ?? [])->map(fn($img) => asset('storage/' . $img))->values()->all(),
            'quantity' => $order->quantity,
            'size_model' => optional($order->sizeModel)->name,
            'size' => $order->size,
            'status' => $order->status,
            'ditugaskan_ke' => $order->ditugaskan_ke,
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
        ];
    }

    /**
     * Menampilkan semua order yang siap dikerjakan.
     */
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

        $orders = Order::with('sizeModel')
            ->where('status', 'ditugaskan')
            ->get()
            ->map(fn($order) => $this->formatOrder($order));

        return response()->json([
            'success' => true,
            'message' => 'Data orders berhasil diambil',
            'data' => $orders,
        ], 200);
    }

    /**
     * User mengambil order tertentu.
     */
    public function takeOrder($id)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order tidak ditemukan',
            ], 404);
        }

        if ($order->status !== 'ditugaskan') {
            return response()->json([
                'success' => false,
                'message' => 'Order ini sudah diambil atau selesai',
            ], 400);
        }

        $order->status = 'dikerjakan';
        $order->ditugaskan_ke = $user->id;
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Order berhasil diambil dan sedang dikerjakan',
            'data' => $this->formatOrder($order),
        ], 200);
    }

    /**
     * Menampilkan order yang sedang dikerjakan oleh user.
     */
    public function getOngoingOrders(Request $request)
    {
        $user = $request->user();

        $orders = Order::with('sizeModel')
            ->where('ditugaskan_ke', $user->id)
            ->where('status', 'dikerjakan')
            ->get();

        if ($orders->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Tidak ada order yang sedang dikerjakan.',
                'data' => [],
            ], 200);
        }

        $formattedOrders = $orders->map(fn($order) => $this->formatOrder($order));

        return response()->json([
            'success' => true,
            'message' => 'Order yang sedang dikerjakan ditemukan.',
            'data' => $formattedOrders,
        ], 200);
    }

    /**
     * Menghitung jumlah order selesai milik user.
     */
    public function countCompletedOrders(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan.',
            ], 404);
        }

        $completedOrdersCount = Order::where('ditugaskan_ke', $user->id)
            ->where('status', 'selesai')
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'Jumlah pesanan selesai berhasil dihitung.',
            'total_completed_orders' => $completedOrdersCount,
        ], 200);
    }

    /**
     * Menampilkan semua order selesai milik user.
     */
    public function getCompletedOrders(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan.',
            ], 404);
        }

        $orders = Order::with('sizeModel')
            ->where('ditugaskan_ke', $user->id)
            ->where('status', 'selesai')
            ->get();

        if ($orders->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Tidak ada pesanan yang telah selesai.',
                'data' => [],
            ], 200);
        }

        $formattedOrders = $orders->map(fn($order) => $this->formatOrder($order));

        return response()->json([
            'success' => true,
            'message' => 'Data pesanan selesai berhasil diambil.',
            'data' => $formattedOrders,
        ], 200);
    }
}
