<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractTemplate extends Model
{
    protected $fillable = [
        'name',
        'type',
        'description',
        'content'
    ];

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * Compile the template content by replacing placeholders with actual contract data.
     */
    public function compile(Contract $contract): string
    {
        $contract->load(['client', 'unit.floor.building']);

        $date = $contract->created_at ? \Carbon\Carbon::parse($contract->created_at) : now();
        $monthsUz = [
            1 => 'yanvar', 2 => 'fevral', 3 => 'mart', 4 => 'aprel',
            5 => 'may', 6 => 'iyun', 7 => 'iyul', 8 => 'avgust',
            9 => 'sentabr', 10 => 'oktabr', 11 => 'noyabr', 12 => 'dekabr'
        ];
        $kun = $date->format('d');
        $oy = $monthsUz[(int)$date->format('m')] ?? 'mart';
        $yil = $date->format('Y');

        $client = $contract->client;
        $unit = $contract->unit;

        // Default Company info
        $korxonaNomi = '«SHER-DOR» xususiy korxonasi';
        $direktorName = 'J.Raxmonov';
        $korxonaManzili = 'Urgut tuman Do`stlik maxallasi Navoi-Shox ko`chasi №3-uy';
        $korxonaHr = '2020 8000 6005 5796 1001';
        $korxonaBank = 'ATB “Agrobank” Urgut tuman filiali';
        $korxonaMfo = '00300';
        $korxonaInn = '202 162 496';
        $korxonaTelefon = '(97) 708-44-44, (93) 573-29-29';

        $totalAmount = $contract->total_amount ?? 0;
        $downPayment = $contract->down_payment ?? 0;
        $totalAmountWords = self::numberToWords($totalAmount);
        $downPaymentWords = self::numberToWords($downPayment);
        $downPaymentPercent = $totalAmount > 0 ? round(($downPayment / $totalAmount) * 100) : 0;
        
        $termMonths = $contract->term_months ?? 1;
        $termMonthsWords = self::numberToWords($termMonths);

        $replacements = [
            '{{KUN}}' => $kun,
            '{{OY}}' => $oy,
            '{{YIL}}' => $yil,
            '{{SHARTNOMA_RAQAMI}}' => $contract->contract_number ?: $contract->id,
            '{{MIJOZ_FISH}}' => $client ? ($client->last_name . ' ' . $client->first_name . ' ' . ($client->middle_name ?? '')) : 'Noma\'lum',
            '{{MIJOZ_MANZILI}}' => $client ? ($client->address ?? '_______') : '_______',
            '{{MIJOZ_JSHSHIR}}' => $client ? ($client->pinfl ?? '_______') : '_______',
            '{{PASPORT_SERIYA}}' => $client ? ($client->passport_series ?? '_______') : '_______',
            '{{PASPORT_SANA}}' => ($client && $client->passport_issued_date) ? \Carbon\Carbon::parse($client->passport_issued_date)->format('d/m/Y') : '_______',
            '{{PASPORT_JOYI}}' => $client ? ($client->passport_issued_by ?? '_______') : '_______',
            '{{BLOK}}' => ($unit && $unit->floor && $unit->floor->building) ? ($unit->floor->building->block_name ?: ($unit->floor->building->corpus_name ?: $unit->floor->building->name)) : '_______',
            '{{QAVAT}}' => $contract->floor ?? ($unit && $unit->floor ? $unit->floor->number : '_______'),
            '{{XONA_RAQAMI}}' => $unit ? $unit->unit_number : '_______',
            '{{XONALAR_SONI}}' => $contract->rooms ?? ($unit ? $unit->rooms_count : '_______'),
            '{{MAYDON}}' => $unit ? number_format($unit->area_sqm, 2, ',', ' ') : '_______',
            '{{KV_METR_NARXI}}' => number_format($contract->price_per_sqm, 0, ',', ' '),
            '{{UMUMIY_NARX}}' => number_format($totalAmount, 0, ',', ' '),
            '{{UMUMIY_NARX_SOZ_BILAN}}' => $totalAmountWords,
            '{{BOSHLANGICH_TOLOV}}' => number_format($downPayment, 0, ',', ' '),
            '{{BOSHLANGICH_TOLOV_SOZ_BILAN}}' => $downPaymentWords,
            '{{BOSHLANGICH_TOLOV_FOIZ}}' => $downPaymentPercent,
            '{{MUDDAT_OY}}' => $termMonths,
            '{{MUDDAT_OY_SOZ_BILAN}}' => $termMonthsWords,
            '{{MIJOZ_TELEFON}}' => $client ? ($client->phone . ($client->phone_2 ? ', ' . $client->phone_2 : '')) : '_______',
            '{{DIREKTOR}}' => $direktorName,
            '{{KORXONA_NOMI}}' => $korxonaNomi,
            '{{KORXONA_MANZILI}}' => $korxonaManzili,
            '{{KORXONA_BANK_ACCOUNT}}' => $korxonaHr,
            '{{KORXONA_BANK}}' => $korxonaBank,
            '{{KORXONA_MFO}}' => $korxonaMfo,
            '{{KORXONA_INN}}' => $korxonaInn,
            '{{KORXONA_TELEFON}}' => $korxonaTelefon,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $this->content);
    }

    /**
     * Convert number to words in Uzbek language.
     */
    public static function numberToWords($number): string
    {
        $number = floor($number);
        if ($number == 0) {
            return 'nol';
        }

        $ones = [
            1 => 'bir', 2 => 'ikki', 3 => 'uch', 4 => 'to\'rt',
            5 => 'besh', 6 => 'olti', 7 => 'yetti', 8 => 'sakkiz', 9 => 'to\'qqiz'
        ];
        $tens = [
            10 => 'o\'n', 20 => 'yigirma', 30 => 'o\'ttiz', 40 => 'qirq',
            50 => 'ellik', 60 => 'oltmish', 70 => 'yetmish', 80 => 'sakson', 90 => 'to\'qson'
        ];
        
        $words = [];
        
        if ($number >= 1000000000) {
            $milliards = floor($number / 1000000000);
            $words[] = self::numberToWords($milliards) . ' milliard';
            $number %= 1000000000;
        }
        
        if ($number >= 1000000) {
            $millions = floor($number / 1000000);
            $words[] = self::numberToWords($millions) . ' million';
            $number %= 1000000;
        }
        
        if ($number >= 1000) {
            $thousands = floor($number / 1000);
            $words[] = self::numberToWords($thousands) . ' ming';
            $number %= 1000;
        }
        
        if ($number >= 100) {
            $hundreds = floor($number / 100);
            $words[] = ($hundreds > 1 ? $ones[$hundreds] . ' ' : '') . 'yuz';
            $number %= 100;
        }
        
        if ($number >= 10) {
            $tenPart = floor($number / 10) * 10;
            $words[] = $tens[$tenPart];
            $number %= 10;
        }
        
        if ($number > 0) {
            $words[] = $ones[$number];
        }
        
        return implode(' ', $words);
    }
}
