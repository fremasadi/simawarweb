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

    public function showDeductions($salary_id)
{
    $deductions = SalaryDeductionHistories::with(['attendance'])
        ->where('salary_id', $salary_id)
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
