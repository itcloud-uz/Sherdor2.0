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

        // Buildings Summary for Object Cards
        $buildingsSummary = \App\Models\Building::with(['floors.units'])->get()->map(function($building) use ($startDate, $endDate) {
            $unitsQuery = \App\Models\Unit::whereHas('floor', function($q) use ($building) {
                $q->where('building_id', $building->id);
            });
            
            $totalUnits = $unitsQuery->count();
            $reservedUnits = (clone $unitsQuery)->where('status', 'reserved')->count();
            $availableUnits = (clone $unitsQuery)->where('status', 'available')->count();
            
            $totalArea = $unitsQuery->sum('area_sqm');
            $availableArea = (clone $unitsQuery)->where('status', 'available')->sum('area_sqm');
            
            $roomsDistribution = (clone $unitsQuery)->whereIn('status', ['sold', 'reserved'])
                ->selectRaw('rooms_count, count(*) as count')
                ->groupBy('rooms_count')
                ->orderBy('rooms_count')
                ->get()
                ->map(function($item) {
                    return [
                        'rooms' => $item->rooms_count,
                        'count' => $item->count
                    ];
                })->toArray();
                
            $totalSum = $unitsQuery->sum('price');
            
            $contractIds = \App\Models\Contract::whereHas('unit.floor', function($q) use ($building) {
                $q->where('building_id', $building->id);
            })->where('status', '!=', 'cancelled')->pluck('id');

            // Filter contracts query by date range if provided
            $contractsQuery = \App\Models\Contract::whereHas('unit.floor', function($q) use ($building) {
                $q->where('building_id', $building->id);
            })->where('status', '!=', 'cancelled');
            if ($startDate && $endDate) {
                $contractsQuery->whereBetween('created_at', [$startDate, $endDate]);
            }
            $filteredContractIds = $contractsQuery->pluck('id');
            $soldUnits = $filteredContractIds->count();

            // Calculate sold area for filtered contracts
            $soldArea = \App\Models\Contract::whereIn('id', $filteredContractIds)
                ->with('unit')
                ->get()
                ->sum(function($c) {
                    return $c->unit->area_sqm ?? 0;
                });

            // Calculate sold sum within date range
            $soldSumQuery = \App\Models\Transaction::whereIn('contract_id', $contractIds)
                ->where('status', 'approved')
                ->where('type', 'income');
            if ($startDate && $endDate) {
                $soldSumQuery->whereBetween('transaction_date', [$startDate, $endDate]);
            }
            $soldSum = $soldSumQuery->sum('amount');
                
            $totalContractAmount = \App\Models\Contract::whereIn('id', $filteredContractIds)->sum('total_amount');
            $debtSum = max(0, $totalContractAmount - $soldSum);
            
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
            'startDate' => $request->input('start_date'),
            'endDate' => $request->input('end_date'),
        ]);
    }
}
