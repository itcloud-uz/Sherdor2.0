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
    private function getPartnerRegisterIds($partner)
    {
        $partnerName = mb_strtolower(str_replace(' ', '', $partner->user->name ?? ''));
        if (empty($partnerName)) return [];

        $pWords = array_filter(explode(' ', mb_strtolower($partner->user->name ?? '')));
        
        $registerIds = \App\Models\CashRegister::all()->filter(function($reg) use ($partnerName, $pWords) {
            $regName = mb_strtolower(str_replace(' ', '', $reg->name));
            if (strpos($regName, $partnerName) !== false || strpos($partnerName, $regName) !== false) {
                return true;
            }
            
            $matches = 0;
            foreach ($pWords as $word) {
                if (strlen($word) < 3) continue;
                if (strpos($regName, $word) !== false) {
                    $matches++;
                }
            }
            return $matches >= 2;
        })->pluck('id')->toArray();

        return $registerIds;
    }

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
        
        // Hardcode company net worth to 0 as requested by the user
        $companyNetWorth = 0;
        
        // Get total UZS across cash registers
        $dynamicUZS = \App\Models\CashRegister::whereNull('parent_id')->get()->sum('balance_uzs');

        foreach ($partners as $partner) {
            $registerIds = $this->getPartnerRegisterIds($partner);

            $isPartnerPayoutQ = function($q) use ($partner) {
                $q->where('target_type', 'partner')
                  ->orWhere('transaction_subtype', 'partner_payout')
                  ->orWhereIn('category', ['Partner Advance', 'Divident', 'Hamkorga to\'lov', 'Hamkor to\'lovi', 'Ta\'sischi to\'lovi'])
                  ->orWhere(function($sq) use ($partner) {
                      $sq->where('partner_id', $partner->id)
                         ->where(function($catQ) {
                             $catQ->whereNull('category')
                                  ->orWhereNotIn('category', ['Qurilish', 'Material', 'Ish haqi', 'Ovqatlanish', 'Texnika', 'Boshqa xarajat']);
                         });
                  });
            };

            // Jami olingan so'mma: all expenses paid to or withdrawn by the partner
            $partner->total_olingan = \App\Models\Transaction::where('status', 'approved')
                ->where(function($q) use ($partner, $registerIds) {
                    $q->where('partner_id', $partner->id)
                      ->orWhere(function($sq) use ($partner) {
                          $sq->where('target_type', 'partner')->where('target_id', $partner->id);
                      });
                    if (!empty($registerIds)) {
                        $q->orWhereIn('account_id', $registerIds);
                    }
                })
                ->where('type', 'expense')
                ->where($isPartnerPayoutQ)
                ->sum('amount');

            // Jami qilingan sarmoya: initial investment + reported expenses on behalf of the company (not payouts/advances) + any incomes
            $dynamicSarmoya = \App\Models\Transaction::where('status', 'approved')
                ->where(function($q) use ($partner, $registerIds) {
                    $q->where('partner_id', $partner->id);
                    if (!empty($registerIds)) {
                        $q->orWhereIn('account_id', $registerIds);
                    }
                })
                ->where(function($q) use ($isPartnerPayoutQ) {
                    $q->where(function($sq) use ($isPartnerPayoutQ) {
                        $sq->where('type', 'expense')
                           ->whereNot($isPartnerPayoutQ);
                    })->orWhere('type', 'income');
                })
                ->sum('amount');

            $partner->dynamic_sarmoya = $dynamicSarmoya;
            $partner->total_sarmoya = $partner->total_invested + $dynamicSarmoya;

            // Hisoblangan ulush: 50% (or share %) of the total cash balance / net profit
            $umumiySumma = $dynamicUZS > 0 ? $dynamicUZS : ($netProfit > 0 ? $netProfit : 0);
            $partner->calculated_share = ($umumiySumma * $partner->investment_share_percentage) / 100;
        }

        $users = \App\Models\User::all(); // Assuming admin can add any user as partner

        return Inertia::render('Admin/Partners/Index', [
            'partners' => $partners,
            'users' => $users,
            'companyNetWorth' => $companyNetWorth,
            'netProfit' => $netProfit
        ]);
    }

    public function fixedAssetsIndex()
    {
        $partners = Partner::with('user')->get();
        $fixedAssets = FixedAsset::with('partner.user')->get();
        
        return Inertia::render('Admin/Partners/Assets', [
            'partners' => $partners,
            'fixedAssets' => $fixedAssets
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
            'transaction_date' => 'nullable|date',
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
            'attachment_path' => $path,
            'transaction_date' => $request->input('transaction_date') ?? now()->toDateString()
        ]);

        return redirect()->back()->with('message', 'Chek yuklandi va tasdiqlash uchun yuborildi.');
    }

    public function getPartnerLedger(Request $request, Partner $partner)
    {
        $registerIds = $this->getPartnerRegisterIds($partner);
        
        $query = \App\Models\Transaction::where(function($q) use ($partner, $registerIds) {
            $q->where('partner_id', $partner->id)
              ->orWhere(function($sq) use ($partner) {
                  $sq->where('target_type', 'partner')->where('target_id', $partner->id);
              });
            if (!empty($registerIds)) {
                $q->orWhereIn('account_id', $registerIds);
            }
        });

        if ($request->filled('from_date')) {
            $query->where(\DB::raw('COALESCE(transaction_date, DATE(created_at))'), '>=', $request->from_date);
        }
        
        if ($request->filled('to_date')) {
            $query->where(\DB::raw('COALESCE(transaction_date, DATE(created_at))'), '<=', $request->to_date);
        }

        $transactions = $query->orderByRaw('COALESCE(transaction_date, created_at) desc')->get();
        return response()->json($transactions);
    }

    public function storeAsset(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'purchase_date' => 'required|date',
            'purchase_cost' => 'required|numeric|min:0',
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
