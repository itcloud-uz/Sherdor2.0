<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function dashboard(Request $request)
    {
        $startDate = $request->input('start_date') ? \Carbon\Carbon::parse($request->input('start_date'))->startOfDay() : null;
        $endDate = $request->input('end_date') ? \Carbon\Carbon::parse($request->input('end_date'))->endOfDay() : null;

        $totalRevenue = \App\Models\Transaction::where('type', 'income')->where('status', 'approved')->sum('amount');
        $totalExpenses = \App\Models\Transaction::where('type', 'expense')->where('status', 'approved')->sum('amount');
        $balanceRecord = \App\Models\CompanyBalance::first();
        $netBalance = $balanceRecord->total_balance ?? 0;
        $creditBalance = $balanceRecord->credit_balance ?? 0;
        
        // NEW DASHBOARD FINANCIAL SUMMARY
        $cashIncome = \App\Models\Transaction::where('type', 'income')->where('payment_method', 'cash')->where('status', 'approved')->sum('amount');
        $cashExpense = \App\Models\Transaction::where('type', 'expense')->where('payment_method', 'cash')->where('status', 'approved')->sum('amount');
        $cashBalance = $cashIncome - $cashExpense;

        $bankIncome = \App\Models\Transaction::where('type', 'income')->whereIn('payment_method', ['card', 'bank_transfer'])->where('status', 'approved')->sum('amount');
        $bankExpense = \App\Models\Transaction::where('type', 'expense')->whereIn('payment_method', ['card', 'bank_transfer'])->where('status', 'approved')->sum('amount');
        $bankBalance = $bankIncome - $bankExpense;

        $creditIncome = 0;
        $creditExpense = 0;
        if (\Illuminate\Support\Facades\Schema::hasTable('financial_liabilities')) {
            $creditIncome = \App\Models\FinancialLiability::sum('total_amount'); // Total credits taken
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('liability_payments')) {
            $creditExpense = \App\Models\LiabilityPayment::where('status', 'paid')->sum('amount'); // Total credits repaid
        }
        $crBalance = $creditIncome - $creditExpense;

        $overallIncome = $cashIncome + $bankIncome + $creditIncome;
        $overallExpense = $cashExpense + $bankExpense + $creditExpense;
        $overallBalance = $overallIncome - $overallExpense;

        $financialSummary = [
            'cash' => [
                'income' => $cashIncome,
                'expense' => $cashExpense,
                'balance' => $cashBalance,
            ],
            'bank' => [
                'income' => $bankIncome,
                'expense' => $bankExpense,
                'balance' => $bankBalance,
            ],
            'credit' => [
                'income' => $creditIncome,
                'expense' => $creditExpense,
                'balance' => $crBalance,
            ],
            'overall' => [
                'income' => $overallIncome,
                'expense' => $overallExpense,
                'balance' => $overallBalance,
            ],
        ];
        
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
            'present' => \App\Models\AttendanceLog::whereDate('created_at', $today)->where('type', 'present')->count(),
            'absent' => \App\Models\AttendanceLog::whereDate('created_at', $today)->where('type', 'absent')->count(),
            'total_workers' => \App\Models\Worker::count()
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
            ->latest()
            ->take(10)
            ->get();

        // 6. Marketing & Lead Analytics
        $leadsMetrics = [
            'total' => \App\Models\Lead::count(),
            'new' => \App\Models\Lead::where('status', 'new')->count(),
            'contacted' => \App\Models\Lead::where('status', 'contacted')->count(),
            'qualified' => \App\Models\Lead::where('status', 'qualified')->count(),
            'lost' => \App\Models\Lead::where('status', 'lost')->count(),
        ];

        // Group by source
        $leadsBySource = \App\Models\Lead::selectRaw('source, count(*) as count')
            ->groupBy('source')
            ->pluck('count', 'source')->toArray();

        // Funnel data
        $leadsFunnel = [
            ['name' => 'Yangi', 'data' => [$leadsMetrics['new']]],
            ['name' => 'Bog\'lanilgan', 'data' => [$leadsMetrics['contacted']]],
            ['name' => 'Saralangan', 'data' => [$leadsMetrics['qualified']]],
        ];

        // Lost leads for modal
        $lostLeads = \App\Models\Lead::where('status', 'lost')->select('id', 'full_name', 'phone', 'reason', 'updated_at')->get();

        // Fetch all contract stats in a single query grouped by building_id
        $contractStatsQuery = \DB::table('contracts')
            ->join('units', 'contracts.unit_id', '=', 'units.id')
            ->join('floors', 'units.floor_id', '=', 'floors.id')
            ->select(
                'floors.building_id',
                \DB::raw('count(contracts.id) as sold_units'),
                \DB::raw('sum(units.area_sqm) as sold_area'),
                \DB::raw('sum(contracts.total_amount) as total_contract_amount')
            )
            ->where('contracts.status', '!=', 'cancelled');

        if ($startDate && $endDate) {
            $contractStatsQuery->whereBetween('contracts.created_at', [$startDate, $endDate]);
        }
        $contractStats = $contractStatsQuery->groupBy('floors.building_id')->get()->keyBy('building_id');

        // Fetch all transaction sums in a single query grouped by building_id
        $transactionSumsQuery = \DB::table('transactions')
            ->join('contracts', 'transactions.contract_id', '=', 'contracts.id')
            ->join('units', 'contracts.unit_id', '=', 'units.id')
            ->join('floors', 'units.floor_id', '=', 'floors.id')
            ->select(
                'floors.building_id',
                \DB::raw('sum(transactions.amount) as sold_sum')
            )
            ->where('transactions.status', 'approved')
            ->where('transactions.type', 'income')
            ->where('contracts.status', '!=', 'cancelled');

        if ($startDate && $endDate) {
            $transactionSumsQuery->whereBetween('transactions.transaction_date', [$startDate, $endDate]);
        }
        $transactionSums = $transactionSumsQuery->groupBy('floors.building_id')->get()->keyBy('building_id');

        // Buildings Summary for Object Cards
        $buildingsSummary = \App\Models\Building::with(['floors.units'])->get()->map(function($building) use ($contractStats, $transactionSums) {
            $units = $building->floors->flatMap(function($floor) {
                return $floor->units;
            });
            
            $totalUnits = $units->count();
            $reservedUnits = $units->where('status', 'reserved')->count();
            $availableUnits = $units->where('status', 'available')->count();
            
            $totalArea = $units->sum('area_sqm');
            $availableArea = $units->where('status', 'available')->sum('area_sqm');
            
            $roomsDistribution = $units->whereIn('status', ['sold', 'reserved'])
                ->groupBy('rooms_count')
                ->map(function($group, $roomsCount) {
                    return [
                        'rooms' => $roomsCount,
                        'count' => $group->count()
                    ];
                })->values()->sortBy('rooms')->toArray();
                
            $totalSum = $units->sum('price');
            
            $buildingContractStats = $contractStats->get($building->id);
            $soldUnits = $buildingContractStats ? (int) $buildingContractStats->sold_units : 0;
            $soldArea = $buildingContractStats ? (float) $buildingContractStats->sold_area : 0.0;
            $totalContractAmount = $buildingContractStats ? (float) $buildingContractStats->total_contract_amount : 0.0;

            $buildingTransactionSums = $transactionSums->get($building->id);
            $soldSum = $buildingTransactionSums ? (float) $buildingTransactionSums->sold_sum : 0.0;
            
            $debtSum = max(0.0, $totalContractAmount - $soldSum);
            
            return [
                'id' => $building->id,
                'name' => $building->name,
                'block_name' => $building->block_name ?? 'Asosiy Blok',
                'photo_path' => $building->photo_path,
                'total_units' => $totalUnits,
                'sold_units' => $soldUnits,
                'reserved_units' => $reservedUnits,
                'available_units' => $availableUnits,
                'total_area' => round($totalArea, 2),
                'sold_area' => round($soldArea, 2),
                'available_area' => round($availableArea, 2),
                'rooms_distribution' => $roomsDistribution,
                'total_sum' => $totalSum,
                'sold_sum' => $soldSum,
                'debt_sum' => $debtSum,
            ];
        });

        // 8. Sales by month for the chart
        $oneYearAgo = \Carbon\Carbon::now()->subYear()->startOfMonth();
        $contracts = \App\Models\Contract::where('status', '!=', 'cancelled')
            ->where('created_at', '>=', $oneYearAgo)
            ->get();
            
        $monthlySales = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = \Carbon\Carbon::now()->subMonths($i);
            $monthKey = $date->format('Y-m');
            $monthLabel = $date->translatedFormat('M Y');
            $monthlySales[$monthKey] = [
                'label' => $monthLabel,
                'count' => 0
            ];
        }
        
        foreach ($contracts as $contract) {
            $monthKey = $contract->created_at->format('Y-m');
            if (isset($monthlySales[$monthKey])) {
                $monthlySales[$monthKey]['count']++;
            }
        }
        
        $salesByMonth = array_values($monthlySales);

        // 9. Analytics by building (approved income & expense)
        $buildings = \App\Models\Building::all();
        $buildingAnalyticsQuery = \DB::table('transactions')
            ->select(
                'building_id',
                \DB::raw("sum(case when type = 'income' then amount else 0 end) as total_income"),
                \DB::raw("sum(case when type = 'expense' then amount else 0 end) as total_expense")
            )
            ->where('status', 'approved')
            ->whereNotNull('building_id');

        if ($startDate && $endDate) {
            $buildingAnalyticsQuery->whereBetween('transaction_date', [$startDate, $endDate]);
        }

        $buildingAnalytics = $buildingAnalyticsQuery->groupBy('building_id')->get()->keyBy('building_id');

        $analyticsByBuilding = $buildings->map(function($building) use ($buildingAnalytics) {
            $stats = $buildingAnalytics->get($building->id);
            return [
                'id' => $building->id,
                'name' => $building->name,
                'income' => $stats ? (float) $stats->total_income : 0.0,
                'expense' => $stats ? (float) $stats->total_expense : 0.0,
            ];
        });

        // 10. Analytics by month (approved income & expense)
        $twelveMonthsAgo = \Carbon\Carbon::now()->subMonths(11)->startOfMonth();
        $transactionsForAnalytics = \DB::table('transactions')
            ->select('type', 'amount', 'transaction_date')
            ->where('status', 'approved')
            ->where('transaction_date', '>=', $twelveMonthsAgo)
            ->get();

        $analyticsByMonth = [];
        for ($i = 11; $i >= 0; $i--) {
            $monthDate = \Carbon\Carbon::now()->subMonths($i);
            $monthKey = $monthDate->format('Y-m');
            $monthLabel = $monthDate->translatedFormat('M Y');
            
            $startOfMonth = (clone $monthDate)->startOfMonth()->format('Y-m-d H:i:s');
            $endOfMonth = (clone $monthDate)->endOfMonth()->format('Y-m-d H:i:s');
            
            if ($startDate && $endDate) {
                $filterStart = \Carbon\Carbon::parse($startDate)->startOfDay()->format('Y-m-d H:i:s');
                $filterEnd = \Carbon\Carbon::parse($endDate)->endOfDay()->format('Y-m-d H:i:s');
                
                $actualStart = max($startOfMonth, $filterStart);
                $actualEnd = min($endOfMonth, $filterEnd);
            } else {
                $actualStart = $startOfMonth;
                $actualEnd = $endOfMonth;
            }
            
            $income = 0.0;
            $expense = 0.0;
            
            if ($actualStart <= $actualEnd) {
                $monthTrans = $transactionsForAnalytics->filter(function($t) use ($actualStart, $actualEnd) {
                    return $t->transaction_date >= $actualStart && $t->transaction_date <= $actualEnd;
                });
                
                $income = (float) $monthTrans->where('type', 'income')->sum('amount');
                $expense = (float) $monthTrans->where('type', 'expense')->sum('amount');
            }
            
            $analyticsByMonth[] = [
                'key' => $monthKey,
                'label' => $monthLabel,
                'income' => $income,
                'expense' => $expense,
            ];
        }

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
            'recentActivities' => $recentActivities,
            'leadsMetrics' => $leadsMetrics,
            'leadsBySource' => $leadsBySource,
            'leadsFunnel' => $leadsFunnel,
            'lostLeads' => $lostLeads,
            'financialSummary' => $financialSummary,
            'buildingsSummary' => $buildingsSummary,
            'salesByMonth' => $salesByMonth,
            'analyticsByBuilding' => $analyticsByBuilding,
            'analyticsByMonth' => $analyticsByMonth,
            'startDate' => $request->input('start_date'),
            'endDate' => $request->input('end_date'),
        ]);
    }
}
