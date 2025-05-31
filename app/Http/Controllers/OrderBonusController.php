<?php

// app/Http/Controllers/Api/OrderBonusController.php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OrderBonus;
use Illuminate\Support\Carbon;

class OrderBonusController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $bonuses = OrderBonus::with('order')
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $bonuses,
        ]);
    }
}
