<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractTemplate extends Model
{
    protected $fillable = [
        'name',
        'type',
        'description',
        'docx_path',
        'preview_html',
        'placeholders'
    ];

    protected $casts = [
        'placeholders' => 'array'
    ];

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    public function variantGroups()
    {
        return $this->hasMany(ClauseVariantGroup::class, 'contract_template_id');
    }

    /**
     * Compile the template preview HTML by replacing placeholders with actual contract data.
     */
    public function compile(Contract $contract): string
    {
        $contract->load(['client', 'unit.floor.building', 'paymentSchedules']);

        $date = $contract->created_at ? \Carbon\Carbon::parse($contract->created_at) : now();

        $client = $contract->client;
        $unit = $contract->unit;

        $building = $unit->floor->building ?? null;
        $templateName = $this->name ?? '';

        $searchText = strtolower(
            ($contract->contract_number ?? '') . ' ' .
            ($templateName) . ' ' .
            ($this->description ?? '') . ' ' .
            ($building->name ?? '') . ' ' .
            ($building->block_name ?? '') . ' ' .
            ($building->corpus_name ?? '')
        );

        $isZamon = (
            strpos($searchText, 'zamon') !== false ||
            strpos($searchText, 'hayot') !== false ||
            strpos($searchText, 'sharipov') !== false ||
            strpos($searchText, '302455208') !== false ||
            strpos(strtolower(base_path()), 'sellerarp') !== false
        );

        $isCyrillic = false;
        if (!empty($this->docx_path)) {
            $fullDocx = storage_path('app/private/' . $this->docx_path);
            if (!file_exists($fullDocx)) {
                $fullDocx = storage_path('app/' . $this->docx_path);
            }
            if (file_exists($fullDocx)) {
                $zip = new \ZipArchive();
                if ($zip->open($fullDocx) === TRUE) {
                    $docXml = $zip->getFromName('word/document.xml');
                    if ($docXml && preg_match('/[а-яА-ЯёЁ]/u', $docXml)) {
                        $isCyrillic = true;
                    }
                    $zip->close();
                }
            }
        }
        if (!$isCyrillic && !empty($this->name) && preg_match('/[а-яА-ЯёЁ]/u', $this->name)) {
            $isCyrillic = true;
        }
        if (!$isCyrillic && !empty($this->preview_html) && preg_match('/[а-яА-ЯёЁ]/u', $this->preview_html)) {
            $isCyrillic = true;
        }
        if (!$isZamon && in_array($this->type, ['residential', 'commercial'])) {
            $isCyrillic = true;
        }

        $monthsUzLat = [
            1 => 'yanvar', 2 => 'fevral', 3 => 'mart', 4 => 'aprel',
            5 => 'may', 6 => 'iyun', 7 => 'iyul', 8 => 'avgust',
            9 => 'sentabr', 10 => 'oktabr', 11 => 'noyabr', 12 => 'dekabr'
        ];
        $monthsUzCyr = [
            1 => 'январ', 2 => 'феврал', 3 => 'март', 4 => 'апрел',
            5 => 'май', 6 => 'июн', 7 => 'июл', 8 => 'август',
            9 => 'сентябр', 10 => 'октябр', 11 => 'ноябр', 12 => 'декабр'
        ];
        $monthsUz = $isCyrillic ? $monthsUzCyr : $monthsUzLat;
        $kun = $date->format('d');
        $oy = $monthsUz[(int)$date->format('m')] ?? ($isCyrillic ? 'июл' : 'mart');
        $oyNum = $date->format('m');
        $yil = $date->format('Y');

        $settings = SystemSetting::first();

        if ($isZamon) {
            $korxonaNomi = '“ZAMON SIFAT SERVIS” MCHJ';
            $korxonaManzili = 'Samarqand shahri, Firdavsiy ko`chasi 81-uy';
            $korxonaHr = '2020 8000 6005 5796 1001';
            $korxonaBank = 'ATB “Agrobank” Samarqand shahar filiali';
            $korxonaMfo = '00300';
            $korxonaInn = '302455208';
            $korxonaDirector = 'E. Sharipov';
            $korxonaTelefon = '(97) 708-44-44, (93) 573-29-29';
        } else {
            $korxonaNomi = ($settings && $settings->company_name) ? $settings->company_name : ($isCyrillic ? '«Шер-Дор» ҳусусий корхонаси' : '“SHER-DOR” XUSUSIY KORXONASI');
            $korxonaManzili = ($settings && $settings->company_address) ? $settings->company_address : ($isCyrillic ? 'Ургут туман Дўстлик махалласи Навои-Шох кўчаси №3-уй' : 'Samarqand viloyati, Urgut tumani, Do`stlik MFY, Navoiy ko`chasi 83-uy');
            $korxonaHr = ($settings && $settings->company_bank_account) ? $settings->company_bank_account : '2020 8000 6005 5796 1001';
            $korxonaBank = ($settings && $settings->company_bank_name) ? $settings->company_bank_name : ($isCyrillic ? 'АТБ “Агробанк” Ургут туман филиали' : 'ATB “Agrobank” Urgut tuman filiali');
            $korxonaMfo = ($settings && $settings->company_mfo) ? $settings->company_mfo : '00300';
            $korxonaInn = ($settings && $settings->company_inn) ? $settings->company_inn : '202 162 496';
            $korxonaDirector = ($settings && $settings->company_director) ? $settings->company_director : ($isCyrillic ? 'Ж. Рахмонов' : 'J. Raxmonov');
            $korxonaTelefon = ($settings && $settings->company_phone) ? $settings->company_phone : '(97) 708-44-44, (93) 573-29-29';
        }

        $totalAmount = $contract->total_amount ?? 0;
        $downPayment = $contract->down_payment ?? 0;
        $totalAmountWords = $isCyrillic ? self::numberToWordsCyrillic($totalAmount) : self::numberToWords($totalAmount);
        $downPaymentWords = $isCyrillic ? self::numberToWordsCyrillic($downPayment) : self::numberToWords($downPayment);
        $downPaymentPercent = $totalAmount > 0 ? round(($downPayment / $totalAmount) * 100) : 0;
        
        $termMonths = $contract->term_months ?? 1;
        $termMonthsWords = $isCyrillic ? self::numberToWordsCyrillic($termMonths) : self::numberToWords($termMonths);

        $blockVal = ($unit && $unit->floor && $unit->floor->building) ? ($unit->floor->building->block_name ?: ($unit->floor->building->corpus_name ?: $unit->floor->building->name)) : '';
        $floorVal = $contract->floor ?? ($unit && $unit->floor ? $unit->floor->floor_number : '');
        $unitVal = $unit ? $unit->unit_number : '';
        $unitType = $unit ? $unit->type : 'residential';

        $blockDesc = $isCyrillic ? self::formatBlockUzCyrillic($blockVal) : self::formatBlockUz($blockVal);
        $floorDesc = $isCyrillic ? self::formatFloorUzCyrillic($floorVal) : self::formatFloorUz($floorVal);
        $unitDesc = $isCyrillic ? self::formatUnitUzCyrillic($unitVal, $unitType) : self::formatUnitUz($unitVal, $unitType);
        $combinedVal = "{$blockDesc} {$floorDesc}, {$unitDesc}";

        $mijozPhoneClean = self::cleanPhoneForContract($client ? ($client->phone . ($client->phone_2 ? ', ' . $client->phone_2 : '')) : '_______');

        $replacements = [
            '{{ XONA_TAFSILOTI }}' => $combinedVal, '{{XONA_TAFSILOTI}}' => $combinedVal,
            '{{ XONADON_TAFSILOTI }}' => $combinedVal, '{{XONADON_TAFSILOTI}}' => $combinedVal,
            '{{ OBYEKT_TAFSILOTI }}' => $combinedVal, '{{OBYEKT_TAFSILOTI}}' => $combinedVal,
            '{{ ХОНА_ТАФСИЛОТИ }}' => $combinedVal, '{{ХОНА_ТАФСИЛОТИ}}' => $combinedVal,
            '{{ ХОНАДОН_ТАФСИЛОТИ }}' => $combinedVal, '{{ХОНАДОН_ТАФСИЛОТИ}}' => $combinedVal,
            '{{ ОБЪЕКТ_ТАФСИЛОТИ }}' => $combinedVal, '{{ОБЪЕКТ_ТАФСИЛОТИ}}' => $combinedVal,
            '{{ KUN }}' => $kun, '{{KUN}}' => $kun,
            '{{ OY }}' => $oy, '{{OY}}' => $oy,
            '{{ OY_NUM }}' => $oyNum, '{{OY_NUM}}' => $oyNum,
            '{{ YIL }}' => $yil, '{{YIL}}' => $yil,
            '{{ SHARTNOMA_RAQAMI }}' => $contract->contract_number ?: $contract->id, '{{SHARTNOMA_RAQAMI}}' => $contract->contract_number ?: $contract->id,
            '{{ MIJOZ_FISH }}' => $client ? ($client->last_name . ' ' . $client->first_name . ' ' . ($client->middle_name ?? '')) : 'Noma\'lum', '{{MIJOZ_FISH}}' => $client ? ($client->last_name . ' ' . $client->first_name . ' ' . ($client->middle_name ?? '')) : 'Noma\'lum',
            '{{ MIJOZ_FISH_QISQA }}' => $client ? (mb_substr($client->first_name, 0, 1, 'UTF-8') . '. ' . ($client->last_name ?: '')) : '_______', '{{MIJOZ_FISH_QISQA}}' => $client ? (mb_substr($client->first_name, 0, 1, 'UTF-8') . '. ' . ($client->last_name ?: '')) : '_______',
            '{{ MIJOZ_MANZILI }}' => $client ? ($client->address ?? '_______') : '_______', '{{MIJOZ_MANZILI}}' => $client ? ($client->address ?? '_______') : '_______',
            '{{ MIJOZ_MANZIL }}' => $client ? ($client->address ?? '_______') : '_______', '{{MIJOZ_MANZIL}}' => $client ? ($client->address ?? '_______') : '_______',
            '{{ MIJOZ_JSHSHIR }}' => $client ? ($client->pinfl ?? '_______') : '_______', '{{MIJOZ_JSHSHIR}}' => $client ? ($client->pinfl ?? '_______') : '_______',
            '{{ PASPORT_SERIYA }}' => $client ? ($client->passport_number ?? $client->passport_series ?? '_______') : '_______', '{{PASPORT_SERIYA}}' => $client ? ($client->passport_number ?? $client->passport_series ?? '_______') : '_______',
            '{{ PASPORT_SANA }}' => self::formatPassportDate($client), '{{PASPORT_SANA}}' => self::formatPassportDate($client),
            '{{ PASPORT_JOYI }}' => self::formatPassportAuthority($client), '{{PASPORT_JOYI}}' => self::formatPassportAuthority($client),
            '{{ BLOK }}' => ($unit && $unit->floor && $unit->floor->building) ? ($unit->floor->building->block_name ?: ($unit->floor->building->corpus_name ?: $unit->floor->building->name)) : '_______', '{{BLOK}}' => ($unit && $unit->floor && $unit->floor->building) ? ($unit->floor->building->block_name ?: ($unit->floor->building->corpus_name ?: $unit->floor->building->name)) : '_______',
            '{{ QAVAT }}' => $contract->floor ?? ($unit && $unit->floor ? $unit->floor->floor_number : '_______'), '{{QAVAT}}' => $contract->floor ?? ($unit && $unit->floor ? $unit->floor->floor_number : '_______'),
            '{{ XONA_RAQAMI }}' => $unit ? $unit->unit_number : '_______', '{{XONA_RAQAMI}}' => $unit ? $unit->unit_number : '_______',
            '{{ XONALAR_SONI }}' => $contract->rooms ?? ($unit ? $unit->rooms_count : '_______'), '{{XONALAR_SONI}}' => $contract->rooms ?? ($unit ? $unit->rooms_count : '_______'),
            '{{ MAYDON }}' => $unit ? number_format($unit->area_sqm, 2, ',', ' ') : '_______', '{{MAYDON}}' => $unit ? number_format($unit->area_sqm, 2, ',', ' ') : '_______',
            '{{ KV_METR_NARXI }}' => number_format($contract->price_per_sqm, 0, ',', ' '), '{{KV_METR_NARXI}}' => number_format($contract->price_per_sqm, 0, ',', ' '),
            '{{ UMUMIY_NARX }}' => number_format($totalAmount, 0, ',', ' '), '{{UMUMIY_NARX}}' => number_format($totalAmount, 0, ',', ' '),
            '{{ UMUMIY_NARX_SOZ_BILAN }}' => $totalAmountWords, '{{UMUMIY_NARX_SOZ_BILAN}}' => $totalAmountWords,
            '{{ BOSHLANGICH_TOLOV }}' => number_format($downPayment, 0, ',', ' '), '{{BOSHLANGICH_TOLOV}}' => number_format($downPayment, 0, ',', ' '),
            '{{ BOSHLANGICH_TOLOV_SOZ_BILAN }}' => $downPaymentWords, '{{BOSHLANGICH_TOLOV_SOZ_BILAN}}' => $downPaymentWords,
            '{{ BOSHLANGICH_TOLOV_FOIZ }}' => $downPaymentPercent, '{{BOSHLANGICH_TOLOV_FOIZ}}' => $downPaymentPercent,
            '{{ MUDDAT_OY }}' => $termMonths, '{{MUDDAT_OY}}' => $termMonths,
            '{{ MUDDAT_OY_SOZ_BILAN }}' => $termMonthsWords, '{{MUDDAT_OY_SOZ_BILAN}}' => $termMonthsWords,
            '{{ MIJOZ_TELEFON }}' => $mijozPhoneClean, '{{MIJOZ_TELEFON}}' => $mijozPhoneClean,
            '{{ DIREKTOR }}' => $korxonaDirector, '{{DIREKTOR}}' => $korxonaDirector,
            '{{ KORXONA_NOMI }}' => $korxonaNomi, '{{KORXONA_NOMI}}' => $korxonaNomi,
            '{{ KORXONA_MANZILI }}' => $korxonaManzili, '{{KORXONA_MANZILI}}' => $korxonaManzili,
            '{{ KORXONA_BANK_ACCOUNT }}' => $korxonaHr, '{{KORXONA_BANK_ACCOUNT}}' => $korxonaHr,
            '{{ KORXONA_BANK }}' => $korxonaBank, '{{KORXONA_BANK}}' => $korxonaBank,
            '{{ KORXONA_MFO }}' => $korxonaMfo, '{{KORXONA_MFO}}' => $korxonaMfo,
            '{{ KORXONA_INN }}' => $korxonaInn, '{{KORXONA_INN}}' => $korxonaInn,
            '{{ KORXONA_TELEFON }}' => $korxonaTelefon, '{{KORXONA_TELEFON}}' => $korxonaTelefon,

            // Zamon Sifat Servis placeholders mappings in compile method:
            '{{ ZAMON_BUYURTMACHI_FISH }}' => $client ? ($client->last_name . ' ' . $client->first_name . ' ' . ($client->middle_name ?? '')) : '_______', '{{ZAMON_BUYURTMACHI_FISH}}' => $client ? ($client->last_name . ' ' . $client->first_name . ' ' . ($client->middle_name ?? '')) : '_______',
            '{{ ZAMON_BUYURTMACHI_PROPISKA }}' => $client ? ($client->address ?? '_______') : '_______', '{{ZAMON_BUYURTMACHI_PROPISKA}}' => $client ? ($client->address ?? '_______') : '_______',
            '{{ ZAMON_PASPORT_SERIYA }}' => $client ? substr(preg_replace('/[^A-Z]/i', '', strtoupper($client->passport_number ?? $client->passport_series ?? '')), 0, 2) : '_______', '{{ZAMON_PASPORT_SERIYA}}' => $client ? substr(preg_replace('/[^A-Z]/i', '', strtoupper($client->passport_number ?? $client->passport_series ?? '')), 0, 2) : '_______',
            '{{ ZAMON_PASPORT_RAQAM }}' => $client ? preg_replace('/[^0-9]/', '', $client->passport_number ?? $client->passport_series ?? '') : '_______', '{{ZAMON_PASPORT_RAQAM}}' => $client ? preg_replace('/[^0-9]/', '', $client->passport_number ?? $client->passport_series ?? '') : '_______',
            '{{ ZAMON_PASPORT_JOYI }}' => self::formatPassportAuthority($client), '{{ZAMON_PASPORT_JOYI}}' => self::formatPassportAuthority($client),
            '{{ ZAMON_PASPORT_SANA }}' => self::formatPassportDate($client), '{{ZAMON_PASPORT_SANA}}' => self::formatPassportDate($client),
            '{{ ZAMON_PASPORT_KUN }}' => ($client && $client->passport_issued_date) ? \Carbon\Carbon::parse($client->passport_issued_date)->format('d') : '___', '{{ZAMON_PASPORT_KUN}}' => ($client && $client->passport_issued_date) ? \Carbon\Carbon::parse($client->passport_issued_date)->format('d') : '___',
            '{{ ZAMON_PASPORT_OY }}' => ($client && $client->passport_issued_date) ? ($monthsUz[(int)\Carbon\Carbon::parse($client->passport_issued_date)->format('m')] ?? '_______') : '________', '{{ZAMON_PASPORT_OY}}' => ($client && $client->passport_issued_date) ? ($monthsUz[(int)\Carbon\Carbon::parse($client->passport_issued_date)->format('m')] ?? '_______') : '________',
            '{{ ZAMON_PASPORT_YIL }}' => ($client && $client->passport_issued_date) ? \Carbon\Carbon::parse($client->passport_issued_date)->format('Y') : '____', '{{ZAMON_PASPORT_YIL}}' => ($client && $client->passport_issued_date) ? \Carbon\Carbon::parse($client->passport_issued_date)->format('Y') : '____',
            '{{ ZAMON_BUYURTMACHI_TUGILGAN_SANA }}' => ($client && $client->birth_date) 
                ? (\Carbon\Carbon::parse($client->birth_date)->format('d') . ' ' . ($monthsUz[(int)\Carbon\Carbon::parse($client->birth_date)->format('m')] ?? 'avgust') . ' ' . \Carbon\Carbon::parse($client->birth_date)->format('Y') . ' yil') 
                : '_______', '{{ZAMON_BUYURTMACHI_TUGILGAN_SANA}}' => ($client && $client->birth_date) 
                ? (\Carbon\Carbon::parse($client->birth_date)->format('d') . ' ' . ($monthsUz[(int)\Carbon\Carbon::parse($client->birth_date)->format('m')] ?? 'avgust') . ' ' . \Carbon\Carbon::parse($client->birth_date)->format('Y') . ' yil') 
                : '_______',
            '{{ ZAMON_PODEZD }}' => $unit ? ($unit->podyezd ?: ($unit->podez ? $unit->podez->number : '_______')) : '_______', '{{ZAMON_PODEZD}}' => $unit ? ($unit->podyezd ?: ($unit->podez ? $unit->podez->number : '_______')) : '_______',
            '{{ ZAMON_XONADON }}' => $unit ? $unit->unit_number : '_______', '{{ZAMON_XONADON}}' => $unit ? $unit->unit_number : '_______',
            '{{ ZAMON_QAVAT }}' => $contract->floor ?? ($unit && $unit->floor ? $unit->floor->floor_number : '_______'), '{{ZAMON_QAVAT}}' => $contract->floor ?? ($unit && $unit->floor ? $unit->floor->floor_number : '_______'),
            '{{ ZAMON_XONALAR }}' => $contract->rooms ?? ($unit ? $unit->rooms_count : '_______'), '{{ZAMON_XONALAR}}' => $contract->rooms ?? ($unit ? $unit->rooms_count : '_______'),
            '{{ ZAMON_MAYDON }}' => $unit ? number_format($unit->area_sqm, 2, ',', ' ') : '_______', '{{ZAMON_MAYDON}}' => $unit ? number_format($unit->area_sqm, 2, ',', ' ') : '_______',
            '{{ ZAMON_KV_METR_NARXI }}' => number_format($contract->price_per_sqm, 0, ',', ' '), '{{ZAMON_KV_METR_NARXI}}' => number_format($contract->price_per_sqm, 0, ',', ' '),
            '{{ ZAMON_KV_METR_NARXI_SOZ }}' => self::numberToWords($contract->price_per_sqm), '{{ZAMON_KV_METR_NARXI_SOZ}}' => self::numberToWords($contract->price_per_sqm),
            '{{ ZAMON_UMUMIY_NARX }}' => number_format($totalAmount, 0, ',', ' '), '{{ZAMON_UMUMIY_NARX}}' => number_format($totalAmount, 0, ',', ' '),
            '{{ ZAMON_UMUMIY_NARX_SOZ }}' => $totalAmountWords, '{{ZAMON_UMUMIY_NARX_SOZ}}' => $totalAmountWords,
            '{{ ZAMON_BOSHLANGICH_TOLOV }}' => number_format($downPayment, 0, ',', ' '), '{{ZAMON_BOSHLANGICH_TOLOV}}' => number_format($downPayment, 0, ',', ' '),
            '{{ ZAMON_BOSHLANGICH_TOLOV_SOZ }}' => $downPaymentWords, '{{ZAMON_BOSHLANGICH_TOLOV_SOZ}}' => $downPaymentWords,
            '{{ ZAMON_BOSHLANGICH_FOIZ }}' => $downPaymentPercent, '{{ZAMON_BOSHLANGICH_FOIZ}}' => $downPaymentPercent,
            '{{ ZAMON_TELEFON }}' => $mijozPhoneClean, '{{ZAMON_TELEFON}}' => $mijozPhoneClean,
        ];

        $selectedVariants = $contract->selected_variants ?? [];
        if (isset($selectedVariants['custom_placeholders']) && is_array($selectedVariants['custom_placeholders'])) {
            foreach ($selectedVariants['custom_placeholders'] as $customKey => $customVal) {
                $replacements["{{ $customKey }}"] = $customVal;
                $replacements["{{$customKey}}"] = $customVal;
            }
        }

        // Process selected variants for dynamic clauses in HTML preview as well!
        foreach ($this->variantGroups as $group) {
            $anchorKey = trim($group->anchor, '{} ');
            
            $variantData = $selectedVariants[$group->anchor] ?? $selectedVariants[$anchorKey] ?? null;
            $variant = null;
            
            if ($variantData && isset($variantData['key'])) {
                $variant = $group->variants()->where('key', $variantData['key'])->first();
            }
            
            // Fallback to first variant if none selected
            if (!$variant) {
                $variant = $group->variants()->first();
            }
            
            if ($variant) {
                $variantText = $variant->content;
                $subPlaceholdersData = $variantData['placeholders'] ?? [];
                
                $subPlaceholdersData['JAMI_NARX'] = number_format($totalAmount, 0, ',', ' ');
                $subPlaceholdersData['JAMI_NARX_SOZDA'] = $totalAmountWords;
                $subPlaceholdersData['ZAMON_UMUMIY_NARX'] = number_format($totalAmount, 0, ',', ' ');
                $subPlaceholdersData['ZAMON_UMUMIY_NARX_SOZ'] = $totalAmountWords;
                
                $foiz = isset($subPlaceholdersData['BOSHLANGICH_FOIZ']) 
                    ? (float)$subPlaceholdersData['BOSHLANGICH_FOIZ'] 
                    : (isset($subPlaceholdersData['ZAMON_BOSHLANGICH_FOIZ']) ? (float)$subPlaceholdersData['ZAMON_BOSHLANGICH_FOIZ'] : null);
                
                if ($foiz !== null) {
                    $calcDownPayment = round($totalAmount * ($foiz / 100));
                    $subPlaceholdersData['BOSHLANGICH_TOLOV'] = number_format($calcDownPayment, 0, ',', ' ');
                    $subPlaceholdersData['BOSHLANGICH_TOLOV_SOZ_BILAN'] = self::numberToWords($calcDownPayment);
                    $subPlaceholdersData['ZAMON_BOSHLANGICH_TOLOV'] = number_format($calcDownPayment, 0, ',', ' ');
                    $subPlaceholdersData['ZAMON_BOSHLANGICH_TOLOV_SOZ'] = self::numberToWords($calcDownPayment);
                } else {
                    $subPlaceholdersData['BOSHLANGICH_TOLOV'] = number_format($downPayment, 0, ',', ' ');
                    $subPlaceholdersData['BOSHLANGICH_TOLOV_SOZ_BILAN'] = $downPaymentWords;
                    $subPlaceholdersData['ZAMON_BOSHLANGICH_TOLOV'] = number_format($downPayment, 0, ',', ' ');
                    $subPlaceholdersData['ZAMON_BOSHLANGICH_TOLOV_SOZ'] = $downPaymentWords;
                }
                
                $m = isset($subPlaceholdersData['MUDDAT_OY']) ? (int)$subPlaceholdersData['MUDDAT_OY'] : $termMonths;
                $subPlaceholdersData['MUDDAT_OY'] = $m;
                $subPlaceholdersData['MUDDAT_OY_SOZ_BILAN'] = self::numberToWords($m);
                $subPlaceholdersData['ZAMON_BOSHLANGICH_FOIZ'] = $downPaymentPercent;
                
                $calcDownPayment = ($foiz !== null) ? round($totalAmount * ($foiz / 100)) : $downPayment;
                $remaining = $totalAmount - $calcDownPayment;
                $monthly = $m > 0 ? round($remaining / $m) : 0;
                $subPlaceholdersData['OYLIK_TOLOV'] = number_format($monthly, 0, ',', ' ');
                $subPlaceholdersData['OYLIK_TOLOV_SOZ_BILAN'] = self::numberToWords($monthly);
                
                $variantReplacements = [];
                foreach ($subPlaceholdersData as $k => $v) {
                    $variantReplacements["{{ $k }}"] = $v;
                    $variantReplacements["{{$k}}"] = $v;
                }
                
                $compiledVariantText = str_replace(array_keys($variantReplacements), array_values($variantReplacements), $variantText);
                
                $replacements["{{ {$anchorKey} }}"] = $compiledVariantText;
                $replacements["{{{$anchorKey}}}"] = $compiledVariantText;
            } else {
                $replacements["{{ {$anchorKey} }}"] = '';
                $replacements["{{{$anchorKey}}}"] = '';
            }
        }

        $html = str_replace(array_keys($replacements), array_values($replacements), $this->preview_html ?? '');
        
        // Clean double phone prefixes
        $html = preg_replace('/(\+?998\s*){2,}/', '+998 ', $html);
        
        // Clean up excess underscores adjacent to words/numbers/parentheses in HTML preview
        $html = preg_replace('/(?<=[a-zA-Z0-9\x{0400}-\x{04FF}\)])_+/u', ' ', $html);
        $html = preg_replace('/_+(?=[a-zA-Z0-9\x{0400}-\x{04FF}\(])/u', ' ', $html);
        return $html;
    }

    /**
     * Renders the docx template with variables and selected variants using Python.
     */
    public function compileDocx(Contract $contract, array $selectedVariants = []): string
    {
        $contract->load(['client', 'unit.floor.building', 'paymentSchedules']);

        $date = $contract->created_at ? \Carbon\Carbon::parse($contract->created_at) : now();

        $client = $contract->client;
        $unit = $contract->unit;

        $building = $unit->floor->building ?? null;
        $templateName = $this->name ?? '';

        $searchText = strtolower(
            ($contract->contract_number ?? '') . ' ' .
            ($templateName) . ' ' .
            ($this->description ?? '') . ' ' .
            ($building->name ?? '') . ' ' .
            ($building->block_name ?? '') . ' ' .
            ($building->corpus_name ?? '')
        );

        $isZamon = (
            strpos($searchText, 'zamon') !== false ||
            strpos($searchText, 'hayot') !== false ||
            strpos($searchText, 'sharipov') !== false ||
            strpos($searchText, '302455208') !== false ||
            strpos(strtolower(base_path()), 'sellerarp') !== false
        );

        $isCyrillic = false;
        if (!empty($this->docx_path)) {
            $fullDocx = storage_path('app/private/' . $this->docx_path);
            if (!file_exists($fullDocx)) {
                $fullDocx = storage_path('app/' . $this->docx_path);
            }
            if (file_exists($fullDocx)) {
                $zip = new \ZipArchive();
                if ($zip->open($fullDocx) === TRUE) {
                    $docXml = $zip->getFromName('word/document.xml');
                    if ($docXml && preg_match('/[а-яА-ЯёЁ]/u', $docXml)) {
                        $isCyrillic = true;
                    }
                    $zip->close();
                }
            }
        }
        if (!$isCyrillic && !empty($this->name) && preg_match('/[а-яА-ЯёЁ]/u', $this->name)) {
            $isCyrillic = true;
        }
        if (!$isCyrillic && !empty($this->preview_html) && preg_match('/[а-яА-ЯёЁ]/u', $this->preview_html)) {
            $isCyrillic = true;
        }
        if (!$isZamon && in_array($this->type, ['residential', 'commercial'])) {
            $isCyrillic = true;
        }

        $monthsUzLat = [
            1 => 'yanvar', 2 => 'fevral', 3 => 'mart', 4 => 'aprel',
            5 => 'may', 6 => 'iyun', 7 => 'iyul', 8 => 'avgust',
            9 => 'sentabr', 10 => 'oktabr', 11 => 'noyabr', 12 => 'dekabr'
        ];
        $monthsUzCyr = [
            1 => 'январ', 2 => 'феврал', 3 => 'март', 4 => 'апрел',
            5 => 'май', 6 => 'июн', 7 => 'июл', 8 => 'август',
            9 => 'сентябр', 10 => 'октябр', 11 => 'ноябр', 12 => 'декабр'
        ];
        $monthsUz = $isCyrillic ? $monthsUzCyr : $monthsUzLat;
        $kun = $date->format('d');
        $oy = $monthsUz[(int)$date->format('m')] ?? ($isCyrillic ? 'июл' : 'mart');
        $oyNum = $date->format('m');
        $yil = $date->format('Y');

        $settings = SystemSetting::first();

        if ($isZamon) {
            $korxonaNomi = '“ZAMON SIFAT SERVIS” MCHJ';
            $korxonaManzili = 'Samarqand shahri, Firdavsiy ko`chasi 81-uy';
            $korxonaHr = '2020 8000 6005 5796 1001';
            $korxonaBank = 'ATB “Agrobank” Samarqand shahar filiali';
            $korxonaMfo = '00300';
            $korxonaInn = '302455208';
            $korxonaDirector = 'E. Sharipov';
            $korxonaTelefon = '(97) 708-44-44, (93) 573-29-29';
        } else {
            $korxonaNomi = ($settings && $settings->company_name) ? $settings->company_name : ($isCyrillic ? '«Шер-Дор» ҳусусий корхонаси' : '“SHER-DOR” XUSUSIY KORXONASI');
            $korxonaManzili = ($settings && $settings->company_address) ? $settings->company_address : ($isCyrillic ? 'Ургут туман Дўстлик махалласи Навои-Шох кўчаси №3-уй' : 'Samarqand viloyati, Urgut tumani, Do`stlik MFY, Navoiy ko`chasi 83-uy');
            $korxonaHr = ($settings && $settings->company_bank_account) ? $settings->company_bank_account : '2020 8000 6005 5796 1001';
            $korxonaBank = ($settings && $settings->company_bank_name) ? $settings->company_bank_name : ($isCyrillic ? 'АТБ “Агробанк” Ургут туман филиали' : 'ATB “Agrobank” Urgut tuman filiali');
            $korxonaMfo = ($settings && $settings->company_mfo) ? $settings->company_mfo : '00300';
            $korxonaInn = ($settings && $settings->company_inn) ? $settings->company_inn : '202 162 496';
            $korxonaDirector = ($settings && $settings->company_director) ? $settings->company_director : ($isCyrillic ? 'Ж. Рахмонов' : 'J. Raxmonov');
            $korxonaTelefon = ($settings && $settings->company_phone) ? $settings->company_phone : '(97) 708-44-44, (93) 573-29-29';
        }

        $totalAmount = $contract->total_amount ?? 0;
        $downPayment = $contract->down_payment ?? 0;
        $totalAmountWords = $isCyrillic ? self::numberToWordsCyrillic($totalAmount) : self::numberToWords($totalAmount);
        $downPaymentWords = $isCyrillic ? self::numberToWordsCyrillic($downPayment) : self::numberToWords($downPayment);
        $downPaymentPercent = $totalAmount > 0 ? round(($downPayment / $totalAmount) * 100) : 0;
        
        $termMonths = $contract->term_months ?? 1;
        $termMonthsWords = $isCyrillic ? self::numberToWordsCyrillic($termMonths) : self::numberToWords($termMonths);

        $blockVal = ($unit && $unit->floor && $unit->floor->building) ? ($unit->floor->building->block_name ?: ($unit->floor->building->corpus_name ?: $unit->floor->building->name)) : '';
        $floorVal = $contract->floor ?? ($unit && $unit->floor ? $unit->floor->floor_number : '');
        $unitVal = $unit ? $unit->unit_number : '';
        $unitType = $unit ? $unit->type : 'residential';

        $blockDesc = $isCyrillic ? self::formatBlockUzCyrillic($blockVal) : self::formatBlockUz($blockVal);
        $floorDesc = $isCyrillic ? self::formatFloorUzCyrillic($floorVal) : self::formatFloorUz($floorVal);
        $unitDesc = $isCyrillic ? self::formatUnitUzCyrillic($unitVal, $unitType) : self::formatUnitUz($unitVal, $unitType);
        $combinedVal = "{$blockDesc} {$floorDesc}, {$unitDesc}";

        $mijozPhoneClean = self::cleanPhoneForContract($client ? ($client->phone . ($client->phone_2 ? ', ' . $client->phone_2 : '')) : '_______');

        // Core placeholders context
        $context = [
            'XONA_TAFSILOTI' => $combinedVal,
            'XONADON_TAFSILOTI' => $combinedVal,
            'OBYEKT_TAFSILOTI' => $combinedVal,
            'ХОНА_ТАФСИЛОТИ' => $combinedVal,
            'ХОНАДОН_ТАФСИЛОТИ' => $combinedVal,
            'ОБЪЕКТ_ТАФСИЛОТИ' => $combinedVal,
            'KUN' => $kun,
            'OY' => $oy,
            'OY_NUM' => $oyNum,
            'YIL' => $yil,
            'SHARTNOMA_RAQAMI' => $contract->contract_number ?: $contract->id,
            'MIJOZ_FISH' => $client ? ($client->last_name . ' ' . $client->first_name . ' ' . ($client->middle_name ?? '')) : 'Noma\'lum',
            'MIJOZ_FISH_QISQA' => $client ? (mb_substr($client->first_name, 0, 1, 'UTF-8') . '. ' . ($client->last_name ?: '')) : '_______',
            'MIJOZ_MANZILI' => $client ? ($client->address ?? '_______') : '_______',
            'MIJOZ_MANZIL' => $client ? ($client->address ?? '_______') : '_______',
            'MIJOZ_JSHSHIR' => $client ? ($client->pinfl ?? '_______') : '_______',
            'PASPORT_SERIYA' => $client ? ($client->passport_number ?? $client->passport_series ?? '_______') : '_______',
            'PASPORT_SANA' => self::formatPassportDate($client),
            'PASPORT_JOYI' => self::formatPassportAuthority($client),
            'BLOK' => ($unit && $unit->floor && $unit->floor->building) ? ($unit->floor->building->block_name ?: ($unit->floor->building->corpus_name ?: $unit->floor->building->name)) : '_______',
            'QAVAT' => $contract->floor ?? ($unit && $unit->floor ? $unit->floor->floor_number : '_______'),
            'XONA_RAQAMI' => $unit ? $unit->unit_number : '_______',
            'XONALAR_SONI' => $contract->rooms ?? ($unit ? $unit->rooms_count : '_______'),
            'MAYDON' => $unit ? number_format($unit->area_sqm, 2, ',', ' ') : '_______',
            'KV_METR_NARXI' => number_format($contract->price_per_sqm, 0, ',', ' '),
            'UMUMIY_NARX' => number_format($totalAmount, 0, ',', ' '),
            'UMUMIY_NARX_SOZ_BILAN' => $totalAmountWords,
            'BOSHLANGICH_TOLOV' => number_format($downPayment, 0, ',', ' '),
            'BOSHLANGICH_TOLOV_SOZ_BILAN' => $downPaymentWords,
            'BOSHLANGICH_TOLOV_FOIZ' => $downPaymentPercent,
            'MUDDAT_OY' => $termMonths,
            'MUDDAT_OY_SOZ_BILAN' => $termMonthsWords,
            'TOLOV_MUDDAT_KUN' => ($contract->payment_limit_days ?? 6) . ' (' . ($isCyrillic ? self::numberToWordsCyrillic($contract->payment_limit_days ?? 6) : self::numberToWords($contract->payment_limit_days ?? 6)) . ') банк',
            'MIJOZ_TELEFON' => $mijozPhoneClean,
            'DIREKTOR' => $korxonaDirector,
            'KORXONA_NOMI' => $korxonaNomi,
            'KORXONA_MANZILI' => $korxonaManzili,
            'KORXONA_BANK_ACCOUNT' => $korxonaHr,
            'KORXONA_BANK' => $korxonaBank,
            'KORXONA_MFO' => $korxonaMfo,
            'KORXONA_INN' => $korxonaInn,
            'KORXONA_TELEFON' => $korxonaTelefon,

            // Zamon Sifat Servis placeholders mappings:
            'ZAMON_BUYURTMACHI_FISH' => $client ? ($client->last_name . ' ' . $client->first_name . ' ' . ($client->middle_name ?? '')) : '_______',
            'ZAMON_BUYURTMACHI_PROPISKA' => $client ? ($client->address ?? '_______') : '_______',
            'ZAMON_PASPORT_SERIYA' => $client ? substr(preg_replace('/[^A-Z]/i', '', strtoupper($client->passport_number ?? $client->passport_series ?? '')), 0, 2) : '_______',
            'ZAMON_PASPORT_RAQAM' => $client ? preg_replace('/[^0-9]/', '', $client->passport_number ?? $client->passport_series ?? '') : '_______',
            'ZAMON_PASPORT_JOYI' => self::formatPassportAuthority($client),
            'ZAMON_PASPORT_SANA' => self::formatPassportDate($client),
            'ZAMON_PASPORT_KUN' => ($client && $client->passport_issued_date) ? \Carbon\Carbon::parse($client->passport_issued_date)->format('d') : '___',
            'ZAMON_PASPORT_OY' => ($client && $client->passport_issued_date) ? ($monthsUz[(int)\Carbon\Carbon::parse($client->passport_issued_date)->format('m')] ?? '_______') : '________',
            'ZAMON_PASPORT_YIL' => ($client && $client->passport_issued_date) ? \Carbon\Carbon::parse($client->passport_issued_date)->format('Y') : '____',
            'ZAMON_BUYURTMACHI_TUGILGAN_SANA' => ($client && $client->birth_date) 
                ? (\Carbon\Carbon::parse($client->birth_date)->format('d') . ' ' . ($monthsUz[(int)\Carbon\Carbon::parse($client->birth_date)->format('m')] ?? 'avgust') . ' ' . \Carbon\Carbon::parse($client->birth_date)->format('Y') . ' yil') 
                : '_______',
            'ZAMON_PODEZD' => $unit ? ($unit->podyezd ?: ($unit->podez ? $unit->podez->number : '_______')) : '_______',
            'ZAMON_XONADON' => $unit ? $unit->unit_number : '_______',
            'ZAMON_QAVAT' => $contract->floor ?? ($unit && $unit->floor ? $unit->floor->floor_number : '_______'),
            'ZAMON_XONALAR' => $contract->rooms ?? ($unit ? $unit->rooms_count : '_______'),
            'ZAMON_MAYDON' => $unit ? number_format($unit->area_sqm, 2, ',', ' ') : '_______',
            'ZAMON_KV_METR_NARXI' => number_format($contract->price_per_sqm, 0, ',', ' '),
            'ZAMON_KV_METR_NARXI_SOZ' => self::numberToWords($contract->price_per_sqm),
            'ZAMON_UMUMIY_NARX' => number_format($totalAmount, 0, ',', ' '),
            'ZAMON_UMUMIY_NARX_SOZ' => $totalAmountWords,
            'ZAMON_BOSHLANGICH_TOLOV' => number_format($downPayment, 0, ',', ' '),
            'ZAMON_BOSHLANGICH_TOLOV_SOZ' => $downPaymentWords,
            'ZAMON_BOSHLANGICH_FOIZ' => $downPaymentPercent,
            'ZAMON_TELEFON' => $mijozPhoneClean,
        ];

        $allSchedulesList = [];
        $installmentsList = [];
        $indexAll = 1;
        $indexInst = 1;
        $sumAll = 0;
        $sumInst = 0;
        
        $schedules = $contract->paymentSchedules()->orderBy('due_date', 'asc')->get();

        foreach ($schedules as $inst) {
            $sumAll += $inst->expected_amount;
            $formattedDate = \Carbon\Carbon::parse($inst->due_date)->format('d/m/Y') . '-yil';
            $formattedSana = \Carbon\Carbon::parse($inst->due_date)->format('d.m.Y');
            $formattedAmount = number_format($inst->expected_amount, 0, ',', ' ');
            $typeText = $inst->payment_type === 'down_payment' ? "Boshlang'ich to'lov" : ($inst->payment_type === 'full' ? "Bir yo'la to'lov" : "Bo'lib to'lash");

            $item = [
                'index' => $indexAll++,
                'date' => $formattedDate,
                'sana' => $formattedSana,
                'amount' => $formattedAmount,
                'amount_raw' => $inst->expected_amount,
                'type' => $typeText,
                'status' => $inst->status === 'paid' ? "To'langan" : "Kutilmoqda"
            ];

            $allSchedulesList[] = $item;

            if ($inst->payment_type !== 'down_payment') {
                $instItem = $item;
                $instItem['index'] = $indexInst++;
                $sumInst += $inst->expected_amount;
                $installmentsList[] = $instItem;
            }
        }

        // Provide installments (excluding down_payment) for standard 2-ilova loop templates
        $context['installments'] = !empty($installmentsList) ? $installmentsList : $allSchedulesList;
        $context['installments_total'] = number_format($sumInst > 0 ? $sumInst : $sumAll, 0, ',', ' ');
        $context['installments_count'] = count(!empty($installmentsList) ? $installmentsList : $allSchedulesList);

        // Provide all schedules under all common template placeholder aliases for 2-ilova
        $context['schedules'] = $allSchedulesList;
        $context['payment_schedules'] = $allSchedulesList;
        $context['all_schedules'] = $allSchedulesList;
        $context['ilova_2'] = $allSchedulesList;
        $context['ilova2'] = $allSchedulesList;
        $context['grafik'] = $allSchedulesList;
        $context['tolov_jadvali'] = $allSchedulesList;
        $context['schedules_total'] = number_format($sumAll, 0, ',', ' ');
        $context['schedules_count'] = count($allSchedulesList);

        if ($contract->payment_type === 'full') {
            $context['installments'] = [];
            $context['schedules'] = [];
            $context['payment_schedules'] = [];
            $context['all_schedules'] = [];
            $context['ilova_2'] = [];
            $context['ilova2'] = [];
            $context['grafik'] = [];
            $context['tolov_jadvali'] = [];
            $context['installments_count'] = 0;
            $context['schedules_count'] = 0;
        }

        $selectedVariants = $contract->selected_variants ?? [];
        if (isset($selectedVariants['custom_placeholders']) && is_array($selectedVariants['custom_placeholders'])) {
            foreach ($selectedVariants['custom_placeholders'] as $customKey => $customVal) {
                $context[$customKey] = $customVal;
            }
        }

        // Process selected variants for dynamic clauses
        foreach ($this->variantGroups as $group) {
            $anchorKey = trim($group->anchor, '{} '); // remove braces from {{ANCHOR}} to match key
            
            $variantData = $selectedVariants[$group->anchor] ?? $selectedVariants[$anchorKey] ?? null;
            $variant = null;
            
            if ($variantData && isset($variantData['key'])) {
                $variant = $group->variants()->where('key', $variantData['key'])->first();
            }
            
            // Fallback to first variant if none selected
            if (!$variant) {
                $variant = $group->variants()->first();
            }
            
            if ($variant) {
                $variantText = $variant->content;
                $subPlaceholdersData = $variantData['placeholders'] ?? [];
                
                // Add automatic helper sub-placeholders:
                $subPlaceholdersData['JAMI_NARX'] = number_format($totalAmount, 0, ',', ' ');
                $subPlaceholdersData['JAMI_NARX_SOZDA'] = $totalAmountWords;
                $subPlaceholdersData['ZAMON_UMUMIY_NARX'] = number_format($totalAmount, 0, ',', ' ');
                $subPlaceholdersData['ZAMON_UMUMIY_NARX_SOZ'] = $totalAmountWords;
                
                // Calculate BOSHLANGICH_TOLOV based on percent if provided
                $foiz = isset($subPlaceholdersData['BOSHLANGICH_FOIZ']) 
                    ? (float)$subPlaceholdersData['BOSHLANGICH_FOIZ'] 
                    : (isset($subPlaceholdersData['ZAMON_BOSHLANGICH_FOIZ']) ? (float)$subPlaceholdersData['ZAMON_BOSHLANGICH_FOIZ'] : null);
                
                if ($foiz !== null) {
                    $calcDownPayment = round($totalAmount * ($foiz / 100));
                    $subPlaceholdersData['BOSHLANGICH_TOLOV'] = number_format($calcDownPayment, 0, ',', ' ');
                    $subPlaceholdersData['BOSHLANGICH_TOLOV_SOZ_BILAN'] = self::numberToWords($calcDownPayment);
                    $subPlaceholdersData['ZAMON_BOSHLANGICH_TOLOV'] = number_format($calcDownPayment, 0, ',', ' ');
                    $subPlaceholdersData['ZAMON_BOSHLANGICH_TOLOV_SOZ'] = self::numberToWords($calcDownPayment);
                } else {
                    $subPlaceholdersData['BOSHLANGICH_TOLOV'] = number_format($downPayment, 0, ',', ' ');
                    $subPlaceholdersData['BOSHLANGICH_TOLOV_SOZ_BILAN'] = $downPaymentWords;
                    $subPlaceholdersData['ZAMON_BOSHLANGICH_TOLOV'] = number_format($downPayment, 0, ',', ' ');
                    $subPlaceholdersData['ZAMON_BOSHLANGICH_TOLOV_SOZ'] = $downPaymentWords;
                }

                $m = isset($subPlaceholdersData['MUDDAT_OY']) ? (int)$subPlaceholdersData['MUDDAT_OY'] : $termMonths;
                $subPlaceholdersData['MUDDAT_OY'] = $m;
                $subPlaceholdersData['MUDDAT_OY_SOZ_BILAN'] = self::numberToWords($m);
                $subPlaceholdersData['ZAMON_BOSHLANGICH_FOIZ'] = $downPaymentPercent;
                
                $calcDownPayment = ($foiz !== null) ? round($totalAmount * ($foiz / 100)) : $downPayment;
                $remaining = $totalAmount - $calcDownPayment;
                $monthly = $m > 0 ? round($remaining / $m) : 0;
                $subPlaceholdersData['OYLIK_TOLOV'] = number_format($monthly, 0, ',', ' ');
                $subPlaceholdersData['OYLIK_TOLOV_SOZ_BILAN'] = self::numberToWords($monthly);
                
                // Build substitutions for the variant text
                $variantReplacements = [];
                foreach ($subPlaceholdersData as $k => $v) {
                    $variantReplacements["{{ $k }}"] = $v;
                    $variantReplacements["{{$k}}"] = $v;
                }
                
                $compiledVariantText = str_replace(array_keys($variantReplacements), array_values($variantReplacements), $variantText);
                $context[$anchorKey] = $compiledVariantText;
            } else {
                $context[$anchorKey] = '';
            }
        }

        // Run python to compile DOCX
        $templateDocx = \Illuminate\Support\Facades\Storage::disk('local')->path($this->docx_path);
        if (!file_exists($templateDocx)) {
            throw new \Exception("DOCX template file not found: " . $templateDocx);
        }

        $tempJson = tempnam(sys_get_temp_dir(), 'docx_ctx_');
        file_put_contents($tempJson, json_encode($context, JSON_UNESCAPED_UNICODE));

        $outputDocxName = 'contract_' . $contract->id . '_' . time() . '.docx';
        $outputDirectory = storage_path('app/public/contracts');
        if (!file_exists($outputDirectory)) {
            mkdir($outputDirectory, 0755, true);
        }
        $outputDocxPath = $outputDirectory . '/' . $outputDocxName;

        try {
            self::runPython('compile "' . $templateDocx . '" "' . addslashes($tempJson) . '" "' . $outputDocxPath . '"');
        } finally {
            @unlink($tempJson);
        }

        if (!file_exists($outputDocxPath)) {
            throw new \Exception("Failed to generate DOCX contract.");
        }

        return 'public/contracts/' . $outputDocxName;
    }

    /**
     * Executes Python document processor command using the configured PYTHON_PATH.
     */
    public static function runPython(string $args): string
    {
        $pythonScript = base_path('app/Python/docx_processor.py');
        
        $candidates = [
            config('app.python_path'),
            base_path('venv/bin/python3'),
            base_path('venv/bin/python'),
            base_path('telegram_bot/venv/bin/python3'),
            base_path('telegram_bot/venv/bin/python'),
            base_path('venv/Scripts/python.exe'),
            'python3',
            'python'
        ];

        $pythonPath = 'python3';
        foreach ($candidates as $cand) {
            if (!empty($cand)) {
                if (strpos($cand, 'venv/') === 0 || strpos($cand, 'venv\\') === 0) {
                    $cand = base_path($cand);
                }
                if (file_exists($cand) && is_executable($cand)) {
                    $pythonPath = $cand;
                    break;
                }
            }
        }

        $command = escapeshellcmd($pythonPath) . ' ' . escapeshellarg($pythonScript) . ' ' . $args . ' 2>&1';
        $output = shell_exec($command);
        
        if ($output) {
            $decoded = json_decode($output, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (is_array($decoded) && isset($decoded['error'])) {
                    throw new \Exception($decoded['error'] . (isset($decoded['traceback']) ? "\n" . $decoded['traceback'] : ''));
                }
                return $output;
            }
        }

        throw new \Exception("Python execution failed. Path used: {$pythonPath}. Output: " . ($output ?: 'No output.'));
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

    /**
     * Convert number to ordinal words in Uzbek.
     */
    public static function numberToOrdinalUzbekWords($number): string
    {
        $number = (int)$number;
        if ($number == 0) {
            return 'nolinchi';
        }
        
        $words = self::numberToWords($number);
        $parts = explode(' ', $words);
        $lastWord = end($parts);
        
        $ordinals = [
            'bir' => 'birinchi',
            'ikki' => 'ikkinchi',
            'uch' => 'uchinchi',
            'to\'rt' => 'to\'rtinchi',
            'besh' => 'beshinchi',
            'olti' => 'oltinchi',
            'yetti' => 'yettinchi',
            'sakkiz' => 'sakkizinchi',
            'to\'qqiz' => 'to\'qqizinchi',
            'o\'n' => 'o\'ninchi',
            'yigirma' => 'yigirmanchi',
            'o\'ttiz' => 'o\'ttizinchi',
            'qirq' => 'qirqinchi',
            'ellik' => 'ellikinchi',
            'oltmish' => 'oltmishinchi',
            'yetmish' => 'yetmishinchi',
            'sakson' => 'saksoninchi',
            'to\'qson' => 'to\'qsoninchi',
            'yuz' => 'yuzinchi',
            'ming' => 'minginchi',
            'million' => 'millioninchi',
            'milliard' => 'milliardinchi',
            'nol' => 'nolinchi'
        ];
        
        if (isset($ordinals[$lastWord])) {
            $parts[count($parts) - 1] = $ordinals[$lastWord];
        } else {
            $lastChar = substr($lastWord, -1);
            if (in_array($lastChar, ['a', 'e', 'i', 'o', 'u'])) {
                $parts[count($parts) - 1] = $lastWord . 'nchi';
            } else {
                $parts[count($parts) - 1] = $lastWord . 'inchi';
            }
        }
        
        return implode(' ', $parts);
    }

    public static function formatBlockUz($blockName): string
    {
        if (empty($blockName)) {
            return '_______-blok';
        }
        $blockName = trim($blockName);
        if (is_numeric($blockName)) {
            $word = self::numberToOrdinalUzbekWords($blockName);
            return "{$blockName}-({$word}) blok";
        }
        return "{$blockName}-blok";
    }

    public static function formatFloorUz($floorNumber): string
    {
        if ($floorNumber === null || $floorNumber === '') {
            return '_______-qavati';
        }
        $floorNumber = (int)$floorNumber;
        if ($floorNumber < 0) {
            return "{$floorNumber}-(podvol) qavati";
        }
        $word = self::numberToOrdinalUzbekWords($floorNumber);
        return "{$floorNumber}-({$word}) qavati";
    }

    public static function formatUnitUz($unitNumber, $type): string
    {
        if (empty($unitNumber)) {
            return '_______-xonadon';
        }
        $unitNumber = trim($unitNumber);
        $suffix = ($type === 'commercial') ? 'savdo do\'koni' : 'xonadoni';
        if (is_numeric($unitNumber)) {
            $word = self::numberToOrdinalUzbekWords($unitNumber);
            return "{$unitNumber}-({$word}) {$suffix}";
        }
        return "{$unitNumber}-{$suffix}";
    }

    public static function cleanPhoneForContract($phone): string
    {
        if (empty($phone) || trim($phone) === '_______') {
            return '_______';
        }
        $cleaned = trim($phone);
        $cleaned = preg_replace('/(\+?998\s*){2,}/', '+998 ', $cleaned);
        return $cleaned;
    }

    public static function formatPassportAuthority(?Client $client): string
    {
        if (!$client || empty($client->passport_issued_by)) {
            return '_______';
        }
        $joyi = trim($client->passport_issued_by);
        $region = trim($client->region ?? '');
        
        if (!empty($region) && !preg_match('/(viloyat|shahar|respublika|toshkent)/i', $joyi)) {
            $regionFormatted = str_contains(mb_strtolower($region, 'UTF-8'), 'viloyat') ? $region : ($region . ' viloyati');
            $joyi = $regionFormatted . ' ' . $joyi;
        }
        
        if (!preg_match('/tomonidan|berilgan/i', $joyi)) {
            $joyi .= ' tomonidan berilgan';
        }
        
        return $joyi;
    }

    public static function formatPassportDate(?Client $client): string
    {
        if (!$client || empty($client->passport_issued_date)) {
            return '_______';
        }
        $formattedDate = \Carbon\Carbon::parse($client->passport_issued_date)->format('d/m/Y');
        return $formattedDate . '-yilda';
    }

    /**
     * Convert number to words in Uzbek language (Cyrillic).
     */
    public static function numberToWordsCyrillic($number): string
    {
        $number = floor($number);
        if ($number == 0) {
            return 'нол';
        }

        $ones = [
            1 => 'бир', 2 => 'икки', 3 => 'уч', 4 => 'тўрт',
            5 => 'беш', 6 => 'олти', 7 => 'етти', 8 => 'саккиз', 9 => 'тўққиз'
        ];
        $tens = [
            10 => 'ўн', 20 => 'йигирма', 30 => 'ўттиз', 40 => 'қирқ',
            50 => 'эллик', 60 => 'олтмиш', 70 => 'етмиш', 80 => 'саксон', 90 => 'тўқсон'
        ];
        
        $words = [];
        
        if ($number >= 1000000000) {
            $milliards = floor($number / 1000000000);
            $words[] = self::numberToWordsCyrillic($milliards) . ' миллиард';
            $number %= 1000000000;
        }
        
        if ($number >= 1000000) {
            $millions = floor($number / 1000000);
            $words[] = self::numberToWordsCyrillic($millions) . ' миллион';
            $number %= 1000000;
        }
        
        if ($number >= 1000) {
            $thousands = floor($number / 1000);
            $words[] = self::numberToWordsCyrillic($thousands) . ' минг';
            $number %= 1000;
        }
        
        if ($number >= 100) {
            $hundreds = floor($number / 100);
            $words[] = ($hundreds >= 1 ? $ones[$hundreds] . ' ' : '') . 'юз';
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

    /**
     * Convert number to ordinal words in Uzbek (Cyrillic).
     */
    public static function numberToOrdinalUzbekWordsCyrillic($number): string
    {
        $number = (int)$number;
        if ($number == 0) {
            return 'нолинчи';
        }
        
        $words = self::numberToWordsCyrillic($number);
        $parts = explode(' ', $words);
        $lastWord = end($parts);
        
        $ordinals = [
            'бир' => 'биринчи',
            'икки' => 'иккинчи',
            'уч' => 'учинчи',
            'тўрт' => 'тўртинчи',
            'беш' => 'бешинчи',
            'олти' => 'олтинчи',
            'етти' => 'еттинчи',
            'саккиз' => 'саккизинчи',
            'тўққиз' => 'тўққизинчи',
            'ўн' => 'ўнинчи',
            'йигирма' => 'йигирманчи',
            'ўттиз' => 'ўттизинчи',
            'қирқ' => 'қирқинчи',
            'эллик' => 'элликинчи',
            'олтмиш' => 'олтмишинчи',
            'етмиш' => 'етмишинчи',
            'саксон' => 'саксонинчи',
            'тўқсон' => 'тўқсонинчи',
            'юз' => 'юзинчи',
            'минг' => 'мингинчи',
            'миллион' => 'миллионинчи',
            'миллиард' => 'миллиардинчи',
            'нол' => 'нолинчи'
        ];
        
        if (isset($ordinals[$lastWord])) {
            $parts[count($parts) - 1] = $ordinals[$lastWord];
        } else {
            $parts[count($parts) - 1] = $lastWord . 'инчи';
        }
        
        return implode(' ', $parts);
    }

    public static function formatBlockUzCyrillic($blockName): string
    {
        if (empty($blockName)) {
            return '_______-блок';
        }
        $blockName = trim($blockName);
        if (is_numeric($blockName)) {
            $word = self::numberToOrdinalUzbekWordsCyrillic($blockName);
            return "{$blockName}-({$word}) блок";
        }
        return "{$blockName}-блок";
    }

    public static function formatFloorUzCyrillic($floorNumber): string
    {
        if ($floorNumber === null || $floorNumber === '') {
            return '_______-қавати';
        }
        $floorNumber = (int)$floorNumber;
        if ($floorNumber < 0) {
            return "{$floorNumber}-(подвал) қавати";
        }
        $word = self::numberToOrdinalUzbekWordsCyrillic($floorNumber);
        return "{$floorNumber}-({$word}) қавати";
    }

    public static function formatUnitUzCyrillic($unitNumber, $type): string
    {
        if (empty($unitNumber)) {
            return ($type === 'commercial') ? '_______-савдо дўконси' : '_______-квартираси';
        }
        $unitNumber = trim($unitNumber);
        $suffix = ($type === 'commercial') ? 'савдо дўконси' : 'квартираси';
        if (is_numeric($unitNumber)) {
            $word = self::numberToOrdinalUzbekWordsCyrillic($unitNumber);
            return "{$unitNumber}-({$word}) {$suffix}";
        }
        return "{$unitNumber}-{$suffix}";
    }
}
