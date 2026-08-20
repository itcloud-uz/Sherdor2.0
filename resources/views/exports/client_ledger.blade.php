<!DOCTYPE html>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta charset="utf-8">
    <title>Mijozning Shaxsiy Varaqasi</title>
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
        @page { size: A4 portrait; margin: 10mm; }
        body { font-family: 'Arial', 'DejaVu Sans', sans-serif; font-size: 10px; color: #000; margin: 0; padding: 0; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }

        .title-header { text-align: center; font-size: 12px; font-weight: bold; margin-bottom: 12px; line-height: 1.4; text-transform: uppercase; }

        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .info-table td { padding: 4px 6px; font-size: 10px; border: 1px solid #000; vertical-align: middle; }
        .info-label { font-weight: bold; background-color: #f2f2f2; }

        .ledger-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .ledger-table th { border: 1.5px solid #000; padding: 6px 4px; font-size: 9.5px; font-weight: bold; text-align: center; background-color: #ffffff; }
        .ledger-table td { border: 1px solid #000; padding: 5px 4px; font-size: 9px; vertical-align: middle; }

        .summary-table { width: 60%; margin: 15px auto; border-collapse: collapse; }
        .summary-table td { border: 1px solid #000; padding: 5px 8px; font-size: 9.5px; font-weight: bold; }
        .summary-table td.val { text-align: right; font-family: monospace; }

        .footer-table { width: 100%; border-collapse: collapse; margin-top: 25px; }
        .footer-table td { border: none; padding: 5px; font-size: 10px; vertical-align: top; }
        
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; margin: 0; }
        }
    </style>
</head>
<body style="padding-bottom: 30px;">

    <!-- TOP ACTION BAR FOR STANDALONE PAGE -->
    <div class="no-print" style="background: #1e293b; color: white; padding: 12px 24px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; font-family: system-ui, sans-serif; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
        <div style="display: flex; align-items: center; gap: 12px;">
            <span style="font-size: 18px;">📄</span>
            <div>
                <div style="font-weight: bold; font-size: 14px;">MIJOZNING SHAXSIY VARAQASI</div>
                <div style="font-size: 11px; opacity: 0.8;">Sher-Dor ERP • Rasmiy Hisob-kitob Jurnali</div>
            </div>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <button onclick="window.print()" style="background: #2563eb; color: white; border: none; padding: 8px 18px; font-weight: bold; font-size: 12px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: background 0.2s;">
                🖨️ Chop etish (Print)
            </button>
            <a href="/admin/crm/ledger/{{ $contract->id }}/export/xlsx" style="background: #059669; color: white; text-decoration: none; padding: 8px 16px; font-weight: bold; font-size: 12px; border-radius: 8px; display: flex; align-items: center; gap: 6px;">
                📊 Excel Yuklash
            </a>
            <a href="/admin/crm/ledger/{{ $contract->id }}/export/pdf" style="background: #dc2626; color: white; text-decoration: none; padding: 8px 16px; font-weight: bold; font-size: 12px; border-radius: 8px; display: flex; align-items: center; gap: 6px;">
                📄 PDF Yuklash
            </a>
        </div>
    </div>

    @php
        $client = $contract->client;
        $unit = $contract->unit;

        $settings = \App\Models\SystemSetting::first();
        $companyName = $settings->company_name ?? '"SHER-DOR" XUSUSIY KORXONASI';
        $directorName = $settings->company_director ?? 'J. Raxmonov';

        $clientName = $client ? trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '') . ' ' . ($client->middle_name ?? '')) : 'Noma\'lum';
        $pinfl = $client->pinfl ?? '-';
        $passport = trim(($client->passport_series ?? '') . ' ' . ($client->passport_number ?? ''));
        $phone = $client->phone ?? '-';

        $contractDate = $contract->created_at ? \Carbon\Carbon::parse($contract->created_at)->format('d.m.Y') : date('d.m.Y');
        $contractNumber = $contract->contract_number ?: ($unit ? "{$unit->floor->floor_number}/{$unit->unit_number}" : ('#' . str_pad($contract->id, 5, '0', STR_PAD_LEFT)));
        $floorNumber = $unit->floor->floor_number ?? '-';
        $unitNumber = $unit->unit_number ?? '-';
        $unitArea = $unit->area ? number_format($unit->area, 2, ',', ' ') : '-';

        $totalPrice = (float)($contract->total_amount ?? 0);
        $downPayment = (float)($contract->down_payment ?? 0);

        $schedules = $contract->paymentSchedules ? $contract->paymentSchedules->sortBy('due_date') : collect();
        $monthlySchedules = $schedules->where('payment_type', '!=', 'down_payment');
        $termMonths = $contract->installment_period ?? ($monthlySchedules->count() ?: 12);
        $remPrice = max(0, $totalPrice - $downPayment);
        $monthlyAmount = $monthlySchedules->first() ? $monthlySchedules->first()->expected_amount : ($termMonths > 0 ? $remPrice / $termMonths : 0);

        $transactions = $contract->transactions ? $contract->transactions->where('type', 'income')->where('status', 'approved')->sortBy('transaction_date') : collect();
        $refunds = $contract->transactions ? $contract->transactions->where('type', 'expense')->where('status', 'approved')->sortBy('transaction_date') : collect();

        $downPaymentTxs = $transactions->filter(function($t) {
            return strpos(strtolower($t->notes ?? ''), 'boshlang') !== false || strpos(strtolower($t->description ?? ''), 'boshlang') !== false;
        });

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

    <!-- HEADER TITLE -->
    <div class="title-header">
        {{ $companyName }}<br>
        MIJOZNING SHAXSIY VARAQASI
    </div>

    <!-- CLIENT & CONTRACT INFO HEADER TABLES (Sample Image Exact Layout) -->
    <table class="info-table">
        <tr>
            <td class="info-label" style="width: 20%;">Mijoz F.I.Sh:</td>
            <td class="font-bold" style="width: 30%;">{{ $clientName }}</td>
            <td class="info-label" style="width: 20%;">PINFL:</td>
            <td style="width: 30%;">{{ $pinfl }}</td>
        </tr>
        <tr>
            <td class="info-label">Pasport raqami:</td>
            <td>{{ $passport }}</td>
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
            <td class="info-label">Qoldiq so'mma:</td>
            <td class="text-right font-bold">{{ number_format($remPrice, 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td class="info-label">Xonadon maydoni:</td>
            <td>{{ $unitArea }} kv.m</td>
            <td class="info-label">Oylik to'lov so'mmasi:</td>
            <td class="text-right font-bold">{{ number_format($monthlyAmount, 0, ',', ' ') }}</td>
        </tr>
    </table>

    <!-- MAIN SCHEDULE & PAYMENTS TABLE -->
    <table class="ledger-table">
        <thead>
            <tr>
                <th colspan="2" style="width: 32%;">Kutilayotgan to'lov</th>
                <th colspan="2" style="width: 32%;">Haqiqatda qilingan to'lov</th>
                <th rowspan="2" style="width: 10%;">To'lov turi</th>
                <th colspan="2" style="width: 16%;">Mijozga qaytarilgan</th>
                <th rowspan="2" style="width: 10%;">Qoldiq</th>
            </tr>
            <tr>
                <th style="width: 16%;">To'lov sanasi</th>
                <th style="width: 16%;">Oylik to'lov so'mmasi</th>
                <th style="width: 16%;">To'langan sana</th>
                <th style="width: 16%;">To'langan so'mma</th>
                <th style="width: 8%;">Qaytarilgan sana</th>
                <th style="width: 8%;">Qaytarilgan so'mma</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $runningBalance = 0;
                $sumExpected = 0;
                $sumPaid = 0;
                $sumRefunded = 0;
            @endphp

            <!-- DOWN PAYMENT ROWS -->
            @if($downPaymentTxs->count() > 0)
                @foreach($downPaymentTxs as $dtx)
                    @php
                        $sumPaid += $dtx->amount;
                        $runningBalance -= $dtx->amount;
                        $pm = $dtx->payment_method === 'bank_transfer' ? 'Bank' : ($dtx->payment_method === 'card' ? 'Plastik' : 'Naqt');
                    @endphp
                    <tr>
                        <td class="text-center font-bold">Boshlang'ich to'lov</td>
                        <td class="text-right font-bold"></td>
                        <td class="text-center">{{ \Carbon\Carbon::parse($dtx->transaction_date ?: $dtx->created_at)->format('d.m.Y') }}</td>
                        <td class="text-right font-bold">{{ number_format($dtx->amount, 0, ',', ' ') }}</td>
                        <td class="text-center">{{ $pm }}</td>
                        <td class="text-center"></td>
                        <td class="text-right"></td>
                        <td class="text-right font-bold">{{ number_format($runningBalance, 0, ',', ' ') }}</td>
                    </tr>
                @endforeach
            @else
                <!-- Show row if down payment exists -->
                <tr>
                    <td class="text-center font-bold">Boshlang'ich to'lov</td>
                    <td class="text-right font-bold">{{ number_format($downPayment, 0, ',', ' ') }}</td>
                    <td class="text-center"></td>
                    <td class="text-right"></td>
                    <td class="text-center"></td>
                    <td class="text-center"></td>
                    <td class="text-right"></td>
                    <td class="text-right font-bold"></td>
                </tr>
            @endif

            <!-- MONTHLY SCHEDULE ROWS -->
            @php
                $monthlyTxsArray = $monthlyTxs->values();
                $refundsArray = $refunds->values();
                $maxRows = max($monthlySchedules->count(), $monthlyTxsArray->count());
                $schList = $monthlySchedules->values();
            @endphp

            @for($i = 0; $i < $maxRows; $i++)
                @php
                    $sch = $schList->get($i);
                    $tx = $monthlyTxsArray->get($i);
                    $ref = $refundsArray->get($i);

                    $expAmt = $sch ? $sch->expected_amount : 0;
                    $paidAmt = $tx ? $tx->amount : 0;
                    $refAmt = $ref ? $ref->amount : 0;

                    $sumExpected += $expAmt;
                    $sumPaid += $paidAmt;
                    $sumRefunded += $refAmt;

                    $runningBalance += ($expAmt - $paidAmt + $refAmt);

                    $pmStr = '';
                    if ($tx) {
                        $pmStr = $tx->payment_method === 'bank_transfer' ? 'Bank' : ($tx->payment_method === 'card' ? 'Plastik' : 'Naqt');
                    }
                @endphp
                <tr>
                    <td class="text-center">{{ $sch ? \Carbon\Carbon::parse($sch->due_date)->format('d.m.Y') : '-' }}</td>
                    <td class="text-right font-bold">{{ $sch ? number_format($sch->expected_amount, 0, ',', ' ') : '-' }}</td>
                    <td class="text-center">{{ $tx ? \Carbon\Carbon::parse($tx->transaction_date ?: $tx->created_at)->format('d.m.Y') : '' }}</td>
                    <td class="text-right font-bold">{{ $tx ? number_format($tx->amount, 0, ',', ' ') : '' }}</td>
                    <td class="text-center">{{ $pmStr }}</td>
                    <td class="text-center">{{ $ref ? \Carbon\Carbon::parse($ref->transaction_date ?: $ref->created_at)->format('d.m.Y') : '' }}</td>
                    <td class="text-right font-bold">{{ $ref ? number_format($ref->amount, 0, ',', ' ') : '' }}</td>
                    <td class="text-right font-bold">
                        {{ number_format($runningBalance, 0, ',', ' ') }}
                    </td>
                </tr>
            @endfor

            <!-- TOTALS ROW -->
            <tr style="font-weight: bold; background-color: #f9f9f9;">
                <td class="text-center">JAMI</td>
                <td class="text-right">{{ number_format($remPrice, 0, ',', ' ') }}</td>
                <td class="text-center"></td>
                <td class="text-right">{{ number_format($sumPaid, 0, ',', ' ') }}</td>
                <td class="text-center"></td>
                <td class="text-center"></td>
                <td class="text-right">{{ number_format($sumRefunded, 0, ',', ' ') }}</td>
                <td class="text-right"></td>
            </tr>
        </tbody>
    </table>

    <!-- SUMMARY TOTALS BOX (Bottom Center matching sample image) -->
    <table class="summary-table">
        <tr>
            <td>Naqt to'lov so'mmasi</td>
            <td class="val">{{ number_format($cashPaid, 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td>Bankga to'lov so'mmasi</td>
            <td class="val">{{ number_format($bankPaid + $cardPaid, 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td>Jami qaytarilgan so'mma</td>
            <td class="val">{{ number_format($totalRefunded, 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td>Penya so'mmasi</td>
            <td class="val">{{ number_format($penaltyAccrued, 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td style="background-color: #f2f2f2;">Umumiy qoldiq so'mma</td>
            <td class="val" style="background-color: #f2f2f2;">{{ number_format($netBalance, 0, ',', ' ') }}</td>
        </tr>
    </table>

    <!-- SIGNATURE FOOTER (Sample Image Exact Layout) -->
    <table class="footer-table">
        <tr>
            <td style="width: 50%; text-align: center;">
                <strong>«XARIDOR»</strong><br><br>
                <strong>F.I.Sh:</strong> {{ $clientName }}<br><br>
                ______________________________
            </td>
            <td style="width: 50%; text-align: center;">
                <strong>«KORXONA»</strong><br><br>
                <strong>{{ $companyName }} direktor: {{ $directorName }}</strong><br><br>
                ______________________________
            </td>
        </tr>
    </table>

</body>
</html>
