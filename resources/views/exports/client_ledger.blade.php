<!DOCTYPE html>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta charset="utf-8">
    <title>Mijozning Shaxsiy Varaqasi - {{ $contract->client ? ($contract->client->last_name . ' ' . $contract->client->first_name) : 'Mijoz' }}</title>
    <!--[if gte mso 9]>
    <xml>
        <o:OfficeDocumentSettings>
            <o:AllowPNG/>
        </o:OfficeDocumentSettings>
        @php $x = 'x:'; @endphp
        {!! "<{$x}ExcelWorkbook>" !!}
            {!! "<{$x}ExcelWorksheets>" !!}
                {!! "<{$x}ExcelWorksheet>" !!}
                    {!! "<{$x}Name>Shaxsiy Varaqa</{$x}Name>" !!}
                    {!! "<{$x}WorksheetOptions>" !!}
                        {!! "<{$x}Print>" !!}
                            {!! "<{$x}ValidPrinterInfo/>" !!}
                            {!! "<{$x}PaperSizeIndex>9</{$x}PaperSizeIndex>" !!}
                            {!! "<{$x}HorizontalResolution>600</{$x}HorizontalResolution>" !!}
                            {!! "<{$x}VerticalResolution>600</{$x}VerticalResolution>" !!}
                        {!! "</{$x}Print>" !!}
                        {!! "<{$x}Selected/>" !!}
                        {!! "<{$x}DoNotDisplayGridlines/>" !!}
                    {!! "</{$x}WorksheetOptions>" !!}
                {!! "</{$x}ExcelWorksheet>" !!}
            {!! "</{$x}ExcelWorksheets>" !!}
        {!! "</{$x}ExcelWorkbook>" !!}
    </xml>
    <![endif]-->
    <style>
        @page { 
            size: A4 portrait; 
            margin: 8mm 10mm 10mm 10mm; 
        }
        * { box-sizing: border-box; }
        body { 
            font-family: 'DejaVu Sans', 'Arial', 'Segoe UI', Tahoma, sans-serif; 
            font-size: 9pt; 
            color: #111827; 
            margin: 0; 
            padding: 0; 
            background-color: #fff;
            line-height: 1.35;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }

        .title-header { 
            text-align: center; 
            font-size: 11pt; 
            font-weight: 700; 
            margin-bottom: 12px; 
            line-height: 1.4; 
            text-transform: uppercase;
            color: #111827;
            letter-spacing: 0.5px;
        }

        .info-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 10px; 
        }
        .info-table td { 
            padding: 4px 7px; 
            font-size: 8.5pt; 
            border: 1px solid #374151; 
            vertical-align: middle; 
        }
        .info-label { 
            font-weight: bold; 
            background-color: #f3f4f6; 
            color: #1f2937;
        }

        .ledger-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 12px; 
            page-break-inside: auto;
        }
        .ledger-table th { 
            border: 1px solid #1f2937; 
            padding: 5px 4px; 
            font-size: 8pt; 
            font-weight: bold; 
            text-align: center; 
            background-color: #e5e7eb; 
            color: #111827;
            vertical-align: middle;
        }
        .ledger-table td { 
            border: 1px solid #4b5563; 
            padding: 4px 5px; 
            font-size: 8pt; 
            vertical-align: middle; 
        }
        .ledger-table tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        .summary-table { 
            width: 65%; 
            margin: 12px auto; 
            border-collapse: collapse; 
            page-break-inside: avoid;
        }
        .summary-table td { 
            border: 1px solid #374151; 
            padding: 5px 10px; 
            font-size: 8.5pt; 
        }
        .summary-table td.label {
            font-weight: 600;
            background-color: #f9fafb;
            color: #1f2937;
        }
        .summary-table td.val { 
            text-align: right; 
            font-weight: bold;
            font-family: 'Consolas', 'Courier New', monospace; 
        }

        .footer-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
            page-break-inside: avoid;
        }
        .footer-table td { 
            border: none; 
            padding: 5px; 
            font-size: 8.5pt; 
            vertical-align: top; 
        }
        
        .no-print { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            background: #0f172a; 
            color: white; 
            padding: 12px 24px; 
            margin-bottom: 18px; 
            font-family: system-ui, -apple-system, sans-serif; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.15); 
            border-radius: 0 0 10px 10px;
        }
        .no-print-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            font-weight: bold;
            font-size: 12px;
            border-radius: 8px;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        .no-print-btn:hover {
            opacity: 0.9;
        }
        
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; margin: 0; background: #fff; }
            .ledger-table th { background-color: #e5e7eb !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .info-label { background-color: #f3f4f6 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body style="padding-bottom: 25px;">

    @php
        $client = $contract->client;
        $unit = $contract->unit;
        $building = $unit->floor->building ?? null;
        $template = $contract->template;

        // Dynamic Company & Director resolution per contract & environment
        $contractTextSearch = strtolower(
            ($contract->contract_number ?? '') . ' ' .
            ($template->name ?? '') . ' ' .
            ($template->description ?? '') . ' ' .
            ($building->name ?? '') . ' ' .
            ($building->block_name ?? '') . ' ' .
            ($building->corpus_name ?? '')
        );

        $isZamonOrHayot = (
            strpos($contractTextSearch, 'zamon') !== false ||
            strpos($contractTextSearch, 'hayot') !== false ||
            strpos($contractTextSearch, 'sharipov') !== false ||
            strpos($contractTextSearch, '302455208') !== false ||
            strpos(strtolower(base_path()), 'sellerarp') !== false
        );

        $isBaraka = (
            strpos($contractTextSearch, 'baraka') !== false ||
            strpos(strtolower(base_path()), 'baraka') !== false
        );

        $settings = \App\Models\SystemSetting::first();

        if ($isZamonOrHayot) {
            $companyName = '“ZAMON SIFAT SERVIS” MCHJ';
            $directorName = 'E. Sharipov';
            $companyLabel = '«БАЖАРУВЧИ»';
            $buyerLabel = '«БУЮРТМАЧИ»';
        } elseif ($isBaraka) {
            $companyName = '“BARAKA RESIDENCE” MCHJ';
            $directorName = ($settings && $settings->company_director) ? $settings->company_director : 'Direktor';
            $companyLabel = '«KORXONA»';
            $buyerLabel = '«XARIDOR»';
        } else {
            $companyName = ($settings && $settings->company_name) ? $settings->company_name : '“SHER-DOR” XUSUSIY KORXONASI';
            $directorName = ($settings && $settings->company_director) ? $settings->company_director : 'J. Raxmonov';
            $companyLabel = '«KORXONA»';
            $buyerLabel = '«XARIDOR»';
        }

        $clientName = $client ? trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '') . ' ' . ($client->middle_name ?? '')) : 'Noma\'lum';
        $pinfl = $client->pinfl ?? '-';
        
        // Clean passport representation without repetition
        $series = trim($client->passport_series ?? '');
        $num = trim($client->passport_number ?? '');
        if ($series && $num) {
            if ($series === $num) {
                $passport = $series;
            } elseif (strpos($num, $series) !== false) {
                $passport = $num;
            } elseif (strpos($series, $num) !== false) {
                $passport = $series;
            } else {
                $passport = $series . ' ' . $num;
            }
        } else {
            $passport = $series ?: ($num ?: '-');
        }

        $phone = $client->phone ?? '-';

        $contractDate = $contract->created_at ? \Carbon\Carbon::parse($contract->created_at)->format('d.m.Y') : date('d.m.Y');
        $contractNumber = $contract->contract_number ?: ($unit ? "{$unit->floor->floor_number}/{$unit->unit_number}" : ('#' . str_pad($contract->id, 5, '0', STR_PAD_LEFT)));
        $floorNumber = $unit->floor->floor_number ?? '-';
        $unitNumber = $unit->unit_number ?? '-';
        
        $areaVal = $unit->area_sqm ?? $unit->area ?? null;
        $unitArea = $areaVal ? (rtrim(rtrim(number_format((float)$areaVal, 2, '.', ' '), '0'), '.')) : '-';

        $totalPrice = (float)($contract->total_amount ?? 0);
        $downPayment = (float)($contract->down_payment ?? 0);

        $schedules = $contract->paymentSchedules ? $contract->paymentSchedules->sortBy('due_date') : collect();
        $monthlySchedules = $schedules->where('payment_type', '!=', 'down_payment');
        $termMonths = $contract->installment_period ?? ($monthlySchedules->count() ?: 12);
        $remPrice = max(0, $totalPrice - $downPayment);
        $monthlyAmount = $monthlySchedules->first() ? (float)$monthlySchedules->first()->expected_amount : ($termMonths > 0 ? $remPrice / $termMonths : 0);

        $transactions = $contract->transactions ? $contract->transactions->where('type', 'income')->where('status', 'approved')->sortBy('transaction_date') : collect();
        $refunds = $contract->transactions ? $contract->transactions->where('type', 'expense')->where('status', 'approved')->sortBy('transaction_date') : collect();

        // Identify down payment transactions
        $downPaymentTxs = $transactions->filter(function($t) {
            $notes = strtolower(($t->notes ?? '') . ' ' . ($t->description ?? ''));
            return strpos($notes, 'boshlang') !== false || strpos($notes, 'avans') !== false || strpos($notes, 'первоначал') !== false;
        });

        // Fallback: If no transaction has "boshlang" notes, match the first transaction if down_payment exists
        if ($downPaymentTxs->isEmpty() && $downPayment > 0 && $transactions->isNotEmpty()) {
            $firstTx = $transactions->first();
            if (abs($firstTx->amount - $downPayment) < 1 || ($contract->created_at && \Carbon\Carbon::parse($firstTx->transaction_date)->diffInDays(\Carbon\Carbon::parse($contract->created_at)) <= 3)) {
                $downPaymentTxs = collect([$firstTx]);
            }
        }

        $monthlyTxs = $transactions->reject(function($t) use ($downPaymentTxs) {
            return $downPaymentTxs->contains('id', $t->id);
        });

        $cashPaid = 0;
        $bankPaid = 0;
        $cardPaid = 0;
        foreach ($transactions as $t) {
            if ($t->payment_method === 'bank_transfer' || $t->payment_method === 'bank') {
                $bankPaid += $t->amount;
            } elseif ($t->payment_method === 'card' || $t->payment_method === 'terminal') {
                $cardPaid += $t->amount;
            } else {
                $cashPaid += $t->amount;
            }
        }
        $totalPaid = $cashPaid + $bankPaid + $cardPaid;
        $totalRefunded = $refunds->sum('amount');
        $penaltyAccrued = $contract->total_penalty_accumulated ?? 0;
        $netBalance = max(0, $totalPrice - $totalPaid + $totalRefunded);
    @endphp

    <!-- TOP ACTION BAR FOR STANDALONE / PREVIEW PAGE (HIDDEN IN PRINT & EXPORTS) -->
    @if(empty($isExport))
    <div class="no-print">
        <div style="display: flex; align-items: center; gap: 12px;">
            <span style="font-size: 20px;">📄</span>
            <div>
                <div style="font-weight: bold; font-size: 14px; letter-spacing: 0.3px;">MIJOZNING SHAXSIY VARAQASI</div>
                <div style="font-size: 11px; color: #94a3b8;">{{ $companyName }} • Rasmiy Hujjat</div>
            </div>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <button onclick="window.print()" class="no-print-btn" style="background: #2563eb; color: white;">
                🖨️ Chop etish (Print)
            </button>
            <a href="/admin/crm/ledger/{{ $contract->id }}/export/xlsx" class="no-print-btn" style="background: #059669; color: white;">
                📊 Excel Yuklash
            </a>
            <a href="/admin/crm/ledger/{{ $contract->id }}/export/pdf" class="no-print-btn" style="background: #dc2626; color: white;">
                📄 PDF Yuklash
            </a>
        </div>
    </div>
    @endif

    <!-- HEADER TITLE -->
    <div class="title-header">
        «{{ $companyName }}»<br>
        MIJOZNING SHAXSIY VARAQASI
    </div>

    <!-- CLIENT & CONTRACT INFO HEADER TABLES -->
    <table class="info-table">
        <tr>
            <td class="info-label" style="width: 20%;">Mijoz F.I.Sh:</td>
            <td class="font-bold" style="width: 30%;">{{ $clientName }}</td>
            <td class="info-label" style="width: 20%;">PINFL:</td>
            <td style="width: 30%;">{{ $pinfl }}</td>
        </tr>
        <tr>
            <td class="info-label">Pasport raqami:</td>
            <td class="font-bold">{{ $passport }}</td>
            <td class="info-label">Telefon raqami:</td>
            <td>{{ $phone }}</td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td class="info-label" style="width: 20%;">Shartnoma sanasi:</td>
            <td style="width: 30%;">{{ $contractDate }}</td>
            <td class="info-label" style="width: 20%;">Xonadon umumiy narx:</td>
            <td class="font-bold text-right" style="width: 30%;">{{ number_format($totalPrice, 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td class="info-label">Shartnoma raqami:</td>
            <td class="font-bold">{{ $contractNumber }}</td>
            <td class="info-label">Boshlang'ich to'lov:</td>
            <td class="text-right font-bold">{{ number_format($downPayment, 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td class="info-label">Qavat:</td>
            <td>{{ $floorNumber }}</td>
            <td class="info-label">To'lov muddati:</td>
            <td class="text-right">{{ $termMonths }} oylik</td>
        </tr>
        <tr>
            <td class="info-label">Xonadon raqami:</td>
            <td>{{ $unitNumber }}</td>
            <td class="info-label">Qoldiq summa:</td>
            <td class="text-right font-bold">{{ number_format($remPrice, 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td class="info-label">Xonadon maydoni:</td>
            <td>{{ $unitArea }} kv.m</td>
            <td class="info-label">Oylik to'lov summasi:</td>
            <td class="text-right font-bold">{{ number_format($monthlyAmount, 0, ',', ' ') }}</td>
        </tr>
    </table>

    <!-- MAIN SCHEDULE & PAYMENTS TABLE -->
    @if($contract->payment_type === 'full')
        <div style="text-align: center; margin: 15px 0 10px 0; font-size: 11px; font-weight: bold;">
            Ushbu shartnoma bo'yicha to'lov to'liq (bir martalik) belgilangan.<br>
            Umumiy summa: {{ number_format($totalPrice, 0, ',', ' ') }} so'm
        </div>
        <table class="ledger-table">
            <thead>
                <tr>
                    <th colspan="3" style="width: 45%;">Haqiqatda qilingan to'lov</th>
                    <th colspan="2" style="width: 35%;">Mijozga qaytarilgan</th>
                    <th rowspan="2" style="width: 20%;">Qoldiq</th>
                </tr>
                <tr>
                    <th style="width: 15%;">To'langan sana</th>
                    <th style="width: 18%;">To'langan summa</th>
                    <th style="width: 12%;">To'lov turi</th>
                    <th style="width: 15%;">Qaytarilgan sana</th>
                    <th style="width: 20%;">Qaytarilgan summa</th>
                </tr>
            </thead>
            <tbody>
                @php 
                    $runningBalance = $totalPrice;
                @endphp
                @foreach($transactions as $tx)
                    @php
                        $runningBalance -= $tx->amount;
                        $pmStr = ($tx->payment_method === 'bank_transfer' || $tx->payment_method === 'bank') ? 'Bank' : (($tx->payment_method === 'card' || $tx->payment_method === 'terminal') ? 'Plastik' : 'Naqd');
                    @endphp
                    <tr>
                        <td class="text-center">{{ \Carbon\Carbon::parse($tx->transaction_date ?: $tx->created_at)->format('d.m.Y') }}</td>
                        <td class="text-right font-bold">{{ number_format($tx->amount, 0, ',', ' ') }}</td>
                        <td class="text-center">{{ $pmStr }}</td>
                        <td class="text-center">-</td>
                        <td class="text-right">-</td>
                        <td class="text-right font-bold">{{ number_format(max(0, $runningBalance), 0, ',', ' ') }}</td>
                    </tr>
                @endforeach
                @foreach($refunds as $ref)
                    @php
                        $runningBalance += $ref->amount;
                    @endphp
                    <tr>
                        <td class="text-center">-</td>
                        <td class="text-right">-</td>
                        <td class="text-center">-</td>
                        <td class="text-center">{{ \Carbon\Carbon::parse($ref->transaction_date ?: $ref->created_at)->format('d.m.Y') }}</td>
                        <td class="text-right font-bold">{{ number_format($ref->amount, 0, ',', ' ') }}</td>
                        <td class="text-right font-bold">{{ number_format(max(0, $runningBalance), 0, ',', ' ') }}</td>
                    </tr>
                @endforeach
                @if($transactions->count() === 0 && $refunds->count() === 0)
                    <tr>
                        <td colspan="6" class="text-center" style="padding: 12px; color: #666;">To'lovlar mavjud emas</td>
                    </tr>
                @endif
                <!-- TOTALS ROW FOR FULL -->
                <tr style="font-weight: bold; background-color: #f3f4f6;">
                    <td class="text-center">JAMI</td>
                    <td class="text-right font-bold">{{ number_format($totalPaid, 0, ',', ' ') }}</td>
                    <td class="text-center">-</td>
                    <td class="text-center">-</td>
                    <td class="text-right font-bold">{{ number_format($totalRefunded, 0, ',', ' ') }}</td>
                    <td class="text-right font-bold">{{ number_format($netBalance, 0, ',', ' ') }}</td>
                </tr>
            </tbody>
        </table>
    @else
    <table class="ledger-table">
        <thead>
            <tr>
                <th colspan="2" style="width: 28%;">Kutilayotgan to'lov</th>
                <th colspan="2" style="width: 28%;">Haqiqatda qilingan to'lov</th>
                <th rowspan="2" style="width: 10%;">To'lov turi</th>
                <th colspan="2" style="width: 20%;">Mijozga qaytarilgan</th>
                <th rowspan="2" style="width: 14%;">Qoldiq</th>
            </tr>
            <tr>
                <th style="width: 13%;">To'lov sanasi</th>
                <th style="width: 15%;">Kutilayotgan summa</th>
                <th style="width: 13%;">To'langan sana</th>
                <th style="width: 15%;">To'langan summa</th>
                <th style="width: 10%;">Qaytarilgan sana</th>
                <th style="width: 10%;">Qaytarilgan summa</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $runningBalance = $totalPrice;
                $sumExp = 0;
                $sumPaid = 0;
                $sumRef = 0;
            @endphp

            <!-- DOWN PAYMENT ROWS -->
            @if($downPaymentTxs->count() > 0)
                @foreach($downPaymentTxs as $dtx)
                    @php
                        $sumPaid += $dtx->amount;
                        $runningBalance -= $dtx->amount;
                        $pm = ($dtx->payment_method === 'bank_transfer' || $dtx->payment_method === 'bank') ? 'Bank' : (($dtx->payment_method === 'card' || $dtx->payment_method === 'terminal') ? 'Plastik' : 'Naqd');
                        if ($loop->first) {
                            $sumExp += $downPayment;
                        }
                    @endphp
                    <tr>
                        <td class="text-center font-bold">Boshlang'ich to'lov</td>
                        <td class="text-right font-bold">{{ $loop->first ? number_format($downPayment, 0, ',', ' ') : '-' }}</td>
                        <td class="text-center">{{ \Carbon\Carbon::parse($dtx->transaction_date ?: $dtx->created_at)->format('d.m.Y') }}</td>
                        <td class="text-right font-bold">{{ number_format($dtx->amount, 0, ',', ' ') }}</td>
                        <td class="text-center">{{ $pm }}</td>
                        <td class="text-center">-</td>
                        <td class="text-right">-</td>
                        <td class="text-right font-bold">{{ number_format(max(0, $runningBalance), 0, ',', ' ') }}</td>
                    </tr>
                @endforeach
            @else
                @php
                    $sumExp += $downPayment;
                @endphp
                <tr>
                    <td class="text-center font-bold">Boshlang'ich to'lov</td>
                    <td class="text-right font-bold">{{ number_format($downPayment, 0, ',', ' ') }}</td>
                    <td class="text-center">-</td>
                    <td class="text-right">-</td>
                    <td class="text-center">-</td>
                    <td class="text-center">-</td>
                    <td class="text-right">-</td>
                    <td class="text-right font-bold">{{ number_format($runningBalance, 0, ',', ' ') }}</td>
                </tr>
            @endif

            <!-- MONTHLY SCHEDULE ROWS -->
            @php
                $monthlyTxsArray = $monthlyTxs->values();
                $refundsArray = $refunds->values();
                $maxRows = max($monthlySchedules->count(), $monthlyTxsArray->count(), $refundsArray->count());
                $schList = $monthlySchedules->values();
            @endphp

            @for($i = 0; $i < $maxRows; $i++)
                @php
                    $sch = $schList->get($i);
                    $tx = $monthlyTxsArray->get($i);
                    $ref = $refundsArray->get($i);

                    $expAmt = $sch ? (float)$sch->expected_amount : 0;
                    $paidAmt = $tx ? (float)$tx->amount : 0;
                    $refAmt = $ref ? (float)$ref->amount : 0;

                    $sumExp += $expAmt;
                    $sumPaid += $paidAmt;
                    $sumRef += $refAmt;

                    if ($tx) {
                        $runningBalance -= $paidAmt;
                    }
                    if ($ref) {
                        $runningBalance += $refAmt;
                    }

                    $pmStr = '-';
                    if ($tx) {
                        $pmStr = ($tx->payment_method === 'bank_transfer' || $tx->payment_method === 'bank') ? 'Bank' : (($tx->payment_method === 'card' || $tx->payment_method === 'terminal') ? 'Plastik' : 'Naqd');
                    }

                    // Balance formatting
                    if ($tx || $ref) {
                        if ($runningBalance < 0) {
                            $qoldiqDisplay = '+ ' . number_format(abs($runningBalance), 0, ',', ' ') . ' (Ortiqcha)';
                        } else {
                            $qoldiqDisplay = number_format($runningBalance, 0, ',', ' ');
                        }
                    } else {
                        // Future scheduled month with no payment made yet
                        if ($runningBalance <= 0) {
                            $qoldiqDisplay = '0';
                        } else {
                            $qoldiqDisplay = number_format($runningBalance, 0, ',', ' ');
                        }
                    }
                @endphp
                <tr>
                    <td class="text-center">{{ $sch ? \Carbon\Carbon::parse($sch->due_date)->format('d.m.Y') : '-' }}</td>
                    <td class="text-right font-bold">{{ $sch ? number_format($sch->expected_amount, 0, ',', ' ') : '-' }}</td>
                    <td class="text-center">{{ $tx ? \Carbon\Carbon::parse($tx->transaction_date ?: $tx->created_at)->format('d.m.Y') : '-' }}</td>
                    <td class="text-right font-bold">{{ $tx ? number_format($tx->amount, 0, ',', ' ') : '-' }}</td>
                    <td class="text-center">{{ $pmStr }}</td>
                    <td class="text-center">{{ $ref ? \Carbon\Carbon::parse($ref->transaction_date ?: $ref->created_at)->format('d.m.Y') : '-' }}</td>
                    <td class="text-right font-bold">{{ $ref ? number_format($ref->amount, 0, ',', ' ') : '-' }}</td>
                    <td class="text-right font-bold">{{ $qoldiqDisplay }}</td>
                </tr>
            @endfor

            <!-- TOTALS ROW -->
            <tr style="font-weight: bold; background-color: #f3f4f6;">
                <td class="text-center">JAMI</td>
                <td class="text-right font-bold">{{ number_format($totalPrice, 0, ',', ' ') }}</td>
                <td class="text-center">-</td>
                <td class="text-right font-bold">{{ number_format($totalPaid, 0, ',', ' ') }}</td>
                <td class="text-center">-</td>
                <td class="text-center">-</td>
                <td class="text-right font-bold">{{ number_format($totalRefunded, 0, ',', ' ') }}</td>
                <td class="text-right font-bold">{{ number_format($netBalance, 0, ',', ' ') }}</td>
            </tr>
        </tbody>
    </table>
    @endif

    <!-- SUMMARY TOTALS BOX -->
    <table class="summary-table">
        <tr>
            <td class="label">Naqd to'lov summasi</td>
            <td class="val">{{ number_format($cashPaid, 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td class="label">Bank to'lov summasi</td>
            <td class="val">{{ number_format($bankPaid + $cardPaid, 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td class="label">Jami qaytarilgan summa</td>
            <td class="val">{{ number_format($totalRefunded, 0, ',', ' ') }}</td>
        </tr>
        @if($penaltyAccrued > 0)
        <tr>
            <td class="label">Penya summasi</td>
            <td class="val" style="color: #dc2626;">{{ number_format($penaltyAccrued, 0, ',', ' ') }}</td>
        </tr>
        @endif
        <tr>
            <td class="label" style="background-color: #e5e7eb; font-size: 9pt;">Umumiy qoldiq summa</td>
            <td class="val" style="background-color: #e5e7eb; font-size: 9pt; color: #111827;">{{ number_format($netBalance, 0, ',', ' ') }}</td>
        </tr>
    </table>

    <!-- SIGNATURE FOOTER -->
    <table class="footer-table">
        <tr>
            <td style="width: 50%; text-align: center;">
                <strong>«XARIDOR»</strong><br><br>
                <strong>F.I.Sh:</strong> {{ $clientName }}<br><br>
                ______________________________
            </td>
            <td style="width: 50%; text-align: center;">
                <strong>«KORXONA»</strong><br><br>
                <strong>{{ $companyName }} rahbari: {{ $directorName }}</strong><br><br>
                ______________________________
            </td>
        </tr>
    </table>

</body>
</html>
