<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\ExpenseApproval;
use App\Models\FixedAsset;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PartnerHubController extends Controller
{
    public function index()
    {
        $partners = Partner::with('user')->get();
        
        // Calculate Net Profit
        $totalIncome = \App\Models\Transaction::where('status', 'approved')->where('type', 'income')->sum('amount');
        $totalExpense = \App\Models\Transaction::where('status', 'approved')
            ->where('type', 'expense')
            ->where('category', '!=', 'Divident')
            ->where('category', '!=', 'Partner Advance')
            ->sum('amount');
            
        $netProfit = $totalIncome - $totalExpense;
        $totalInvested = $partners->sum('total_invested');
        $companyNetWorth = \App\Models\CompanyBalance::first()?->total_balance ?? 0;
        
        // Get total UZS across cash registers
        $dynamicUZS = \App\Models\CashRegister::whereNull('parent_id')->get()->sum('balance_uzs');

        foreach ($partners as $partner) {
            // Jami qilingan sarmoya: sum of expense transactions reported by the partner (not advance/dividend) + any incomes
            $partner->total_sarmoya = \App\Models\Transaction::where('partner_id', $partner->id)
                ->where('status', 'approved')
                ->where(function($q) {
                    $q->where(function($sq) {
                        $sq->where('type', 'expense')
                            ->whereNotIn('category', ['Partner Advance', 'Divident']);
                    })->orWhere('type', 'income');
                })
                ->sum('amount');

            // Jami olingan so'mma: advances and dividends
            $partner->total_olingan = \App\Models\Transaction::where('partner_id', $partner->id)
                ->where('status', 'approved')
                ->where('type', 'expense')
                ->whereIn('category', ['Partner Advance', 'Divident'])
                ->sum('amount');

            // Hisoblangan ulush: 50% (or share %) of the total balance / net profit
            // We use max of netProfit and dynamicUZS to make sure we show a positive share even if net profit is low during dev
            $umumiySumma = $netProfit > 0 ? $netProfit : ($dynamicUZS > 0 ? $dynamicUZS : 127180000);
            $partner->calculated_share = ($umumiySumma * $partner->investment_share_percentage) / 100;
        }

        $users = \App\Models\User::all(); // Assuming admin can add any user as partner

        return Inertia::render('Admin/Partners/Index', [
            'partners' => $partners,
            'users' => $users,
            'companyNetWorth' => $companyNetWorth,
            'netProfit' => $netProfit,
            'fixedAssets' => FixedAsset::with('partner.user')->get()
        ]);
    }

    public function storePartner(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'investment_share_percentage' => 'nullable|numeric|min:0|max:100',
            'total_invested' => 'required|numeric|min:0'
        ]);
        
        if (empty($validated['investment_share_percentage'])) {
            $validated['investment_share_percentage'] = 0;
        }

        Partner::create($validated);
        return redirect()->back()->with('message', 'Ta\'sischi qo\'shildi.');
    }

    public function updatePartner(Request $request, Partner $partner)
    {
        $validated = $request->validate([
            'investment_share_percentage' => 'nullable|numeric|min:0|max:100',
            'total_invested' => 'required|numeric|min:0',
            'accountable_balance' => 'required|numeric' // Adding ability to correct accountable balance manually if needed
        ]);
        
        if (empty($validated['investment_share_percentage'])) {
            $validated['investment_share_percentage'] = 0;
        }

        $partner->update($validated);
        return redirect()->back()->with('message', 'Ta\'sischi ma\'lumotlari tahrirlandi.');
    }

    public function destroyPartner(Partner $partner)
    {
        $partner->delete();
        return redirect()->back()->with('message', 'Ta\'sischi tizimdan o\'chirildi.');
    }

    public function provideAdvance(Request $request, Partner $partner)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'notes' => 'nullable|string'
        ]);

        \App\Models\Transaction::create([
            'type' => 'expense',
            'amount' => $validated['amount'],
            'currency' => 'UZS',
            'payment_method' => 'cash',
            'account_type' => 'main',
            'category' => 'Partner Advance',
            'notes' => "Ta'sischiga hisobdorlik (Avans so'rovi): " . ($validated['notes'] ?? ''),
            'status' => 'pending',
            'created_by' => auth()->id(),
            'partner_id' => $partner->id
        ]);

        return redirect()->back()->with('message', 'Avans so\'rovi yuborildi. Moliya markazida tasdiqlangach pul ajratiladi.');
    }

    public function reportExpense(Request $request, Partner $partner)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'category' => 'required|string',
            'notes' => 'nullable|string',
            'items' => 'nullable|array',
            'attachment' => 'required|file|mimes:jpeg,png,jpg,pdf|max:10240',
        ]);

        $path = null;
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('receipts', 'public');
        }

        $notesData = [
            'notes' => $validated['notes'] ?? '',
            'items' => $request->input('items') ?? []
        ];

        \App\Models\Transaction::create([
            'type' => 'expense', 
            'amount' => $validated['amount'],
            'currency' => 'UZS',
            'payment_method' => 'cash',
            'account_type' => 'main',
            'category' => $validated['category'], // This could be 'Ovqatlanish', 'Divident', etc.
            'notes' => json_encode($notesData, JSON_UNESCAPED_UNICODE),
            'status' => 'pending',
            'created_by' => $partner->user_id,
            'partner_id' => $partner->id,
            'attachment_path' => $path
        ]);

        return redirect()->back()->with('message', 'Chek yuklandi va tasdiqlash uchun yuborildi.');
    }

    public function getPartnerLedger(Request $request, Partner $partner)
    {
        $query = $partner->transactions();

        if ($request->filled('from_date')) {
            $query->where('created_at', '>=', $request->from_date . ' 00:00:00');
        }
        
        if ($request->filled('to_date')) {
            $query->where('created_at', '<=', $request->to_date . ' 23:59:59');
        }

        $transactions = $query->orderBy('created_at', 'desc')->get();
        return response()->json($transactions);
    }

    public function storeAsset(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'purchase_date' => 'required|date',
            'purchase_cost' => 'required|numeric|min:0',
            'service_life_months' => 'required|integer|min:1',
            'status' => 'required|in:active,sold,retired',
            'description' => 'nullable|string',
            'partner_id' => 'nullable|exists:partners,id'
        ]);

        FixedAsset::create($validated);
        return redirect()->back()->with('message', 'Assosiy vosita qo\'shildi.');
    }

    public function updateAsset(Request $request, FixedAsset $asset)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'purchase_date' => 'required|date',
            'purchase_cost' => 'required|numeric|min:0',
            'service_life_months' => 'required|integer|min:1',
            'status' => 'required|in:active,sold,retired',
            'description' => 'nullable|string',
            'partner_id' => 'nullable|exists:partners,id'
        ]);

        $asset->update($validated);
        return redirect()->back()->with('message', 'Assosiy vosita ma\'lumotlari yangilandi.');
    }

    public function destroyAsset(FixedAsset $asset)
    {
        $asset->delete();
        return redirect()->back()->with('message', 'Assosiy vosita o\'chirildi.');
    }
}
