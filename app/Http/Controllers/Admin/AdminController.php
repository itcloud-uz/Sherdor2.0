<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function dashboard()
    {
        $totalRevenue = \App\Models\Transaction::where('type', 'income')->where('status', 'approved')->sum('amount');
        $totalExpenses = \App\Models\Transaction::where('type', 'expense')->where('status', 'approved')->sum('amount');
        $balanceRecord = \App\Models\CompanyBalance::first();
        $netBalance = $balanceRecord->total_balance ?? 0;
        $creditBalance = $balanceRecord->credit_balance ?? 0;
        
        $activeClients = \App\Models\Contract::where('status', 'active')->distinct('client_id')->count('client_id');

        $units = \App\Models\Unit::selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status')->toArray();
        $unitsStatus = [
            'available' => $units['available'] ?? 0,
            'reserved' => $units['reserved'] ?? 0,
            'sold' => $units['sold'] ?? 0,
        ];

        // NEW METRICS
        // 1. Income by category (using transactions, assuming we group by something or just generic for now)
        // Since we don't have categories in transactions, we can group by whether they are from Contracts or not.
        $incomeBySource = [
            'Shartnomalar' => \App\Models\Transaction::where('type', 'income')->whereNotNull('payment_schedule_id')->where('status', 'approved')->sum('amount'),
            'Boshqa tushumlar' => \App\Models\Transaction::where('type', 'income')->whereNull('payment_schedule_id')->where('status', 'approved')->sum('amount'),
        ];

        // 2. Expenses by category (using CashRequest types if available, or just generic)
        $expensesBySource = [
            'Ish haqi va Oyliklar' => \App\Models\CashRequest::where('type', 'expense')->where('status', 'approved')->where('target_type', 'payroll')->sum('amount'),
            'Texnika va Yoqilg\'i' => \App\Models\CashRequest::where('type', 'expense')->where('status', 'approved')->where('target_type', 'machinery')->sum('amount'),
            'Materiallar va Boshqa' => \App\Models\CashRequest::where('type', 'expense')->where('status', 'approved')->where('target_type', 'other')->sum('amount'),
        ];

        // 3. HR Stats (Attendance)
        $today = \Carbon\Carbon::today();
        $attendanceStats = [
            'present' => \App\Models\AttendanceLog::whereDate('date', $today)->where('status', 'present')->count(),
            'absent' => \App\Models\AttendanceLog::whereDate('date', $today)->where('status', 'absent')->count(),
            'total_workers' => \App\Models\Worker::where('status', 'active')->count()
        ];

        // 4. Financial Liabilities (Qarzlar)
        // We will fetch this if the table exists. To avoid errors during migration, we wrap it.
        $totalLiabilities = 0;
        $activeLiabilities = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('financial_liabilities')) {
            $totalLiabilities = \App\Models\FinancialLiability::where('status', 'active')->sum('total_amount');
            // subtract paid amounts
            $paidLiabilities = \App\Models\LiabilityPayment::where('status', 'paid')->sum('amount');
            $totalLiabilities -= $paidLiabilities;

            $activeLiabilities = \App\Models\FinancialLiability::with(['payments' => function($q) {
                $q->where('status', 'paid');
            }])->where('status', 'active')->get()->map(function($liability) {
                $paid = $liability->payments->sum('amount');
                return [
                    'name' => $liability->creditor_name,
                    'type' => $liability->type,
                    'remaining' => $liability->total_amount - $paid
                ];
            });
        }

        // 5. System Alerts (Smart Notifications)
        $systemAlerts = [];
        $pendingCashRequests = \App\Models\CashRequest::where('status', 'pending')->count();
        if ($pendingCashRequests > 0) {
            $systemAlerts[] = ['type' => 'warning', 'message' => "Kassada {$pendingCashRequests} ta tasdiqlanmagan so'rov kutmoqda."];
        }
        $pendingContracts = \App\Models\Contract::where('status', 'pending')->count();
        if ($pendingContracts > 0) {
            $systemAlerts[] = ['type' => 'info', 'message' => "CRM da {$pendingContracts} ta yangi shartnoma tasdiqlashni kutyapti."];
        }

        $recentActivities = \App\Models\Transaction::with(['creator', 'approver', 'paymentSchedule.contract.client'])
            ->where('status', 'pending')
            ->latest()
            ->take(5)
            ->get();

        return Inertia::render('Admin/Dashboard', [
            'totalRevenue' => $totalRevenue,
            'totalExpenses' => $totalExpenses,
            'netBalance' => $netBalance,
            'creditBalance' => $creditBalance,
            'activeClients' => $activeClients,
            'unitsStatus' => $unitsStatus,
            'incomeBySource' => $incomeBySource,
            'expensesBySource' => $expensesBySource,
            'attendanceStats' => $attendanceStats,
            'totalLiabilities' => $totalLiabilities,
            'activeLiabilities' => $activeLiabilities,
            'systemAlerts' => $systemAlerts,
            'recentActivities' => $recentActivities
        ]);
    }
}
