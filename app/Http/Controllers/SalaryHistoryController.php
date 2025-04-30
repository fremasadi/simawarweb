<?php

// app/Http/Controllers/Api/SalaryHistoryController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Salary;

class SalaryHistoryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user(); // Mendapatkan user dari Sanctum

        $salaries = Salary::with('salarySetting')
            ->where('user_id', $user->id)
            ->orderBy('pay_date', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $salaries,
        ]);
    }
}
