<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    protected $fillable = ['user_id', 'investment_share_percentage', 'total_invested', 'accountable_balance'];

    protected $casts = [
        'accountable_balance' => 'decimal:2',
        'total_invested' => 'decimal:2',
        'investment_share_percentage' => 'decimal:2'
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function consensusVotes()
    {
        return $this->hasMany(ConsensusVote::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'partner_id');
    }

    public static function getPartnerRegisterIds($partner)
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

    public static function getAllWithFinancials()
    {
        $partners = static::with('user')->get();

        $totalIncome = (float)\App\Models\Transaction::where('status', 'approved')
            ->where('type', 'income')
            ->where('currency', 'UZS')
            ->sum('amount');

        $totalExpense = (float)\App\Models\Transaction::where('status', 'approved')
            ->where('type', 'expense')
            ->where('currency', 'UZS')
            ->sum('amount');
            
        $netProfit = $totalIncome - $totalExpense;
        $dynamicUZS = (float)\App\Models\CashRegister::whereNull('parent_id')->get()->sum('balance_uzs');

        foreach ($partners as $partner) {
            $registerIds = static::getPartnerRegisterIds($partner);

            $isPartnerPayoutQ = function($q) use ($partner) {
                $q->where(function($sq) use ($partner) {
                    $sq->where('target_type', 'partner')
                       ->where('target_id', $partner->id);
                })
                ->orWhereIn('category', ['Partner Advance', 'Divident', "Hamkorga to'lov", "Hamkor to'lovi", "Ta'sischi to'lovi"])
                ->orWhere(function($sq) use ($partner) {
                    $sq->where('partner_id', $partner->id)
                       ->where(function($catQ) {
                           $catQ->whereNull('category')
                                ->orWhereNotIn('category', ['Qurilish', 'Material', 'Ish haqi', 'Ovqatlanish', 'Texnika', 'Boshqa xarajat']);
                       });
                });
            };

            // Jami olingan summa: all expenses paid to or withdrawn by the partner
            $partner->total_olingan = (float)\App\Models\Transaction::where('status', 'approved')
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
            $dynamicSarmoya = (float)\App\Models\Transaction::where('status', 'approved')
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
            $partner->total_sarmoya = (float)$partner->total_invested + $dynamicSarmoya;
            
            // Aliases for finance
            $partner->invested_amount = $partner->total_sarmoya;
            $partner->withdrawn_amount = $partner->total_olingan;

            // Hisoblangan ulush: 50% (or share %) of the total cash balance / net profit
            $umumiySumma = $dynamicUZS > 0 ? $dynamicUZS : ($netProfit > 0 ? $netProfit : 0);
            $partner->calculated_share = ($umumiySumma * (float)$partner->investment_share_percentage) / 100;
        }

        return [
            'partners' => $partners,
            'totalIncome' => $totalIncome,
            'totalExpense' => $totalExpense,
            'netProfit' => $netProfit,
            'dynamicUZS' => $dynamicUZS,
            'companyNetWorth' => $dynamicUZS,
        ];
    }
}
