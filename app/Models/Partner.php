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
        $companyNetWorth = 0; // Kompaniyaning jami qiymati (avvalgi kelishuv bo'yicha 0 UZS)

        foreach ($partners as $partner) {
            $registerIds = static::getPartnerRegisterIds($partner);

            // Barcha tasdiqlangan ta'sischi yoki uning kassasiga bog'langan tranzaksiyalar
            $partnerTxs = \App\Models\Transaction::where('status', 'approved')
                ->where(function($q) use ($partner, $registerIds) {
                    $q->where('partner_id', $partner->id)
                      ->orWhere(function($sq) use ($partner) {
                          $sq->where('target_type', 'partner')->where('target_id', $partner->id);
                      });
                    if (!empty($registerIds)) {
                        $q->orWhereIn('account_id', $registerIds);
                    }
                })
                ->get();

            // Olingan pul: korxonadan ta'sischiga berilgan mablag'lar (Avans, Divident, Payout)
            $olinganTxs = $partnerTxs->filter(function($t) use ($partner) {
                $cat = is_object($t->category) ? ($t->category->name ?? '') : ($t->category ?? '');
                $isPartnerPayout = ($t->target_type === 'partner' && $t->target_id == $partner->id) 
                    || $t->transaction_subtype === 'partner_payout' 
                    || in_array($cat, ['Partner Advance', 'Divident', "Hamkorga to'lov", "Hamkor to'lovi", "Ta'sischi to'lovi"]);
                return $t->type === 'expense' && ($isPartnerPayout || (!$cat && $t->partner_id == $partner->id));
            });

            // Sarmoya: ta'sischi kiritgan sarmoyalar va korxona/obyekt nomidan qilingan barcha xarajatlar
            $sarmoyaTxs = $partnerTxs->filter(function($t) use ($partner) {
                $cat = is_object($t->category) ? ($t->category->name ?? '') : ($t->category ?? '');
                $isPartnerPayout = ($t->target_type === 'partner' && $t->target_id == $partner->id) 
                    || $t->transaction_subtype === 'partner_payout' 
                    || in_array($cat, ['Partner Advance', 'Divident', "Hamkorga to'lov", "Hamkor to'lovi", "Ta'sischi to'lovi"]);
                return $t->type === 'income' || ($t->type === 'expense' && !$isPartnerPayout);
            });

            $totalOlingan = (float)$olinganTxs->sum('amount');
            $dynamicSarmoya = (float)$sarmoyaTxs->sum('amount');
            $totalSarmoya = (float)$partner->total_invested + $dynamicSarmoya;

            $partner->total_olingan = $totalOlingan;
            $partner->dynamic_sarmoya = $dynamicSarmoya;
            $partner->total_sarmoya = $totalSarmoya;
            $partner->sarmoya_count = $sarmoyaTxs->count();
            $partner->olingan_count = $olinganTxs->count();
            
            // Aliases for finance
            $partner->invested_amount = $partner->total_sarmoya;
            $partner->withdrawn_amount = $partner->total_olingan;

            // Hisoblangan ulush: Sof foydadan (Net profit) ta'sischining ulush foizi (%) bo'yicha
            // Masalan: 440,836,596 * 50% = 220,418,298 UZS
            $sharePercentage = (float)$partner->investment_share_percentage;
            $partner->calculated_share = $netProfit > 0 ? (($netProfit * $sharePercentage) / 100) : 0;

            // Hisob/Qarz (Ulush qoldig'i): Olingan pul foyda ulushidan minus qilinadi
            // Masalan: 220,418,298 ulush - 212,080,000 olingan pul = +8,338,298 UZS
            $partner->accountable_balance = $partner->calculated_share - $partner->total_olingan;
        }

        $companyNetWorth = $partners->sum('total_sarmoya');

        return [
            'partners' => $partners,
            'totalIncome' => $totalIncome,
            'totalExpense' => $totalExpense,
            'netProfit' => $netProfit,
            'companyNetWorth' => $companyNetWorth,
        ];
    }
}
