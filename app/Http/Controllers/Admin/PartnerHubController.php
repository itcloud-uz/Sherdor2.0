<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\ExpenseApproval;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PartnerHubController extends Controller
{
    public function index()
    {
        $partners = Partner::with('user')->get();
        $users = \App\Models\User::all(); // Assuming admin can add any user as partner
        
        // Calculate Net Profit
        $totalIncome = \App\Models\Transaction::where('status', 'approved')->where('type', 'income')->sum('amount');
        $totalExpense = \App\Models\Transaction::where('status', 'approved')
            ->where('type', 'expense')
            ->where('category', '!=', 'Divident')
            ->where('category', '!=', 'Partner Advance')
            ->sum('amount');
            
        $netProfit = $totalIncome - $totalExpense;

        return Inertia::render('Admin/Partners/Index', [
            'partners' => $partners,
            'users' => $users,
            'companyNetWorth' => \App\Models\CompanyBalance::first()->total_balance ?? 0,
            'netProfit' => $netProfit
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
            'attachment' => 'required|file|mimes:jpeg,png,jpg,pdf|max:10240',
        ]);

        $path = null;
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('receipts', 'public');
        }

        \App\Models\Transaction::create([
            'type' => 'expense', 
            'amount' => $validated['amount'],
            'currency' => 'UZS',
            'payment_method' => 'cash',
            'account_type' => 'main',
            'category' => $validated['category'], // This could be 'Ovqatlanish', 'Divident', etc.
            'notes' => "Ta'sischi xarajati (Chek topshirildi): " . ($validated['notes'] ?? ''),
            'status' => 'pending',
            'created_by' => $partner->user_id,
            'partner_id' => $partner->id,
            'attachment_path' => $path
        ]);

        return redirect()->back()->with('message', 'Chek yuklandi va tasdiqlash uchun yuborildi.');
    }

    public function getPartnerLedger(Partner $partner)
    {
        $transactions = $partner->transactions()->orderBy('created_at', 'desc')->get();
        return response()->json($transactions);
    }
}
