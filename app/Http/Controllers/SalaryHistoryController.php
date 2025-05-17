<?php

// app/Http/Controllers/Api/SalaryHistoryController.php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Salary;
use App\Models\SalaryDeductionHistories;

class SalaryHistoryController extends Controller
{
    public function index(Request $request)
{
    $user = $request->user(); // User dari Sanctum

    $query = Salary::with('salarySetting')
        ->where('user_id', $user->id);

    // Filter berdasarkan tanggal jika tersedia
    if ($request->has('from')) {
        $query->whereDate('pay_date', '>=', $request->input('from'));
    }

    if ($request->has('to')) {
        $query->whereDate('pay_date', '<=', $request->input('to'));
    }

    $salaries = $query->orderBy('pay_date', 'desc')->get();

    return response()->json([
        'status' => 'success',
        'data' => $salaries,
    ]);
}
//megambil data potongan gaji
public function showDeductions($salary_id)
{
    $deductions = SalaryDeductionHistories::with(['attendance'])
        ->where('salary_id', $salary_id)
        ->orderBy('created_at', 'desc') // Tambahkan ini untuk urutan descending
        ->get();

    if ($deductions->isEmpty()) {
        return response()->json([   
            'status' => 'not_found',
            'message' => 'Tidak ada potongan untuk salary_id ini.',
        ], 404);
    }

    return response()->json([
        'status' => 'success',
        'data' => $deductions,
    ]);
}

}
