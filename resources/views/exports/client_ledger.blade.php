<!DOCTYPE html>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta charset="utf-8">
    <title>Hisob-kitob jurnali - {{ $contract->client ? ($contract->client->last_name . ' ' . $contract->client->first_name) : 'Mijoz' }}</title>
    <!--[if gte mso 9]>
    <xml>
        <o:OfficeDocumentSettings>
            <o:AllowPNG/>
        </o:OfficeDocumentSettings>
        @php $x = 'x:'; @endphp
        {!! "<{$x}ExcelWorkbook>" !!}
            {!! "<{$x}ExcelWorksheets>" !!}
                {!! "<{$x}ExcelWorksheet>" !!}
                    {!! "<{$x}Name>Hisob-kitob Jurnali</{$x}Name>" !!}
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
        @page { size: A4 landscape; margin: 8mm; }
        body { font-family: 'Times New Roman', 'Arial', sans-serif; font-size: 11px; color: #000; margin: 0; padding: 15px; background: #fff; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }

        table.grid-table { width: 100%; border-collapse: collapse; margin-bottom: 0px; }
        table.grid-table th, table.grid-table td { border: 1px solid #000; padding: 6px 8px; font-size: 11px; vertical-align: middle; }
        table.grid-table th { font-weight: bold; text-align: center; background-color: #f2f2f2; }

        .no-print { display: flex; justify-content: space-between; align-items: center; background: #1e293b; color: white; padding: 12px 24px; margin-bottom: 20px; font-family: system-ui, sans-serif; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>

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
        } elseif ($isBaraka) {
            $companyName = '“BARAKA RESIDENCE” MCHJ';
            $directorName = ($settings && $settings->company_director) ? $settings->company_director : 'Direktor';
        } else {
            $companyName = ($settings && $settings->company_name) ? $settings->company_name : '“SHER-DOR” XUSUSIY KORXONASI';
            $directorName = ($settings && $settings->company_director) ? $settings->company_director : 'J. Raxmonov';
        }

        $clientName = $client ? trim(($client->last_name ?? '') . ' ' . ($client->first_name ?? '') . ' ' . ($client->middle_name ?? '')) : 'Noma\'lum';
        $phone = $client->phone ?? '-';

        $contractDate = $contract->created_at ? \Carbon\Carbon::parse($contract->created_at)->format('d.m.Y') : date('d.m.Y');
        $contractNumber = $contract->contract_number ?: ($unit ? "{$unit->floor->floor_number}/{$unit->unit_number}" : ('#' . str_pad($contract->id, 5, '0', STR_PAD_LEFT)));
        $floorNumber = $unit->floor->floor_number ?? '-';
        $unitNumber = $unit->unit_number ?? '-';

        $totalPrice = (float)($contract->total_amount ?? 0);
        $downPayment = (float)($contract->down_payment ?? 0);

        $transactions = $contract->transactions ? $contract->transactions->where('type', 'income')->where('status', 'approved')->sortBy('transaction_date') : collect();
        $refunds = $contract->transactions ? $contract->transactions->where('type', 'expense')->where('status', 'approved')->sortBy('transaction_date') : collect();

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
        $netBalance = max(0, $totalPrice - $totalPaid + $totalRefunded);
    @endphp

    <!-- TOP ACTION BAR FOR STANDALONE PAGE -->
    <div class="no-print">
        <div style="display: flex; align-items: center; gap: 12px;">
            <span style="font-size: 20px;">📄</span>
            <div>
                <div style="font-weight: bold; font-size: 14px;">HISOB-KITOB JURNALI</div>
                <div style="font-size: 11px; opacity: 0.8;">{{ $clientName }} • {{ $companyName }}</div>
            </div>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <button onclick="window.print()" style="background: #2563eb; color: white; border: none; padding: 8px 18px; font-weight: bold; font-size: 12px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 6px;">
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

    <!-- CLIENT NAME CENTERED HEADER (Exact sample layout) -->
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 2px;">
        <tr>
            <th style="border: 1.5px solid #000; background: #dbeedf; font-size: 15px; font-weight: bold; text-align: center; padding: 7px;">
                {{ $clientName }}
            </th>
        </tr>
    </table>

    <!-- CONTRACT & CLIENT INFO GRID TABLE (Exact sample layout) -->
    <table class="grid-table" style="margin-bottom: -1px;">
        <thead>
            <tr>
                <th style="width: 13%;">Shartnoma sanasi</th>
                <th style="width: 14%;">Shartnoma №</th>
                <th style="width: 10%;">Uy №</th>
                <th style="width: 10%;">Qavat</th>
                <th style="width: 15%;">Tel raqam</th>
                <th style="width: 18%;">Umumiy qoldiq</th>
                <th style="width: 20%; background: #eef2f5;" rowspan="2">Izoh</th>
            </tr>
            <tr>
                <td class="text-center font-bold">{{ $contractDate }}</td>
                <td class="text-center font-bold">{{ $contractNumber }}</td>
                <td class="text-center font-bold">{{ $unitNumber }}</td>
                <td class="text-center font-bold">{{ $floorNumber }}</td>
                <td class="text-center font-bold">{{ $phone }}</td>
                <td class="text-right font-bold" style="font-size: 12px; color: {{ $netBalance > 0 ? '#b91c1c' : '#047857' }};">
                    {{ number_format($netBalance, 0, ',', ' ') }}
                </td>
            </tr>
        </thead>
    </table>

    <!-- MAIN TRANSACTIONS TABLE (Exact sample layout) -->
    <table class="grid-table">
        <thead>
            <tr>
                <th colspan="3" style="width: 40%; background: #e0f2fe;">To`langan</th>
                <th colspan="3" style="width: 40%; background: #d1fae5;">Qaytarilgan</th>
                <th style="width: 20%; display: none;"></th>
            </tr>
            <tr>
                <th style="width: 12%;">Sana</th>
                <th style="width: 14%;">Bank orqali</th>
                <th style="width: 14%;">Naqt</th>
                <th style="width: 12%;">Sana</th>
                <th style="width: 14%;">Bank orqali</th>
                <th style="width: 14%;">Naqt</th>
                <th style="width: 20%;">Izoh</th>
            </tr>
        </thead>
        <tbody>
            @php
                $sumIncBank = 0;
                $sumIncCash = 0;
                $sumExpBank = 0;
                $sumExpCash = 0;

                $maxRows = max($transactions->count(), $refunds->count(), 14);
                $txList = $transactions->values();
                $refList = $refunds->values();
            @endphp

            @for($i = 0; $i < $maxRows; $i++)
                @php
                    $tx = $txList->get($i);
                    $ref = $refList->get($i);

                    $txDate = $tx ? \Carbon\Carbon::parse($tx->transaction_date ?: $tx->created_at)->format('d.m.Y') : '';
                    $isTxBank = $tx && ($tx->payment_method === 'bank_transfer' || $tx->payment_method === 'bank' || $tx->payment_method === 'card');
                    $txBank = $isTxBank ? $tx->amount : null;
                    $txCash = ($tx && !$isTxBank) ? $tx->amount : null;

                    if ($txBank) $sumIncBank += $txBank;
                    if ($txCash) $sumIncCash += $txCash;

                    $refDate = $ref ? \Carbon\Carbon::parse($ref->transaction_date ?: $ref->created_at)->format('d.m.Y') : '';
                    $isRefBank = $ref && ($ref->payment_method === 'bank_transfer' || $ref->payment_method === 'bank' || $ref->payment_method === 'card');
                    $refBank = $isRefBank ? $ref->amount : null;
                    $refCash = ($ref && !$isRefBank) ? $ref->amount : null;

                    if ($refBank) $sumExpBank += $refBank;
                    if ($refCash) $sumExpCash += $refCash;

                    $note = ($tx ? ($tx->description ?: $tx->notes) : '') ?: ($ref ? ($ref->description ?: $ref->notes) : '');
                @endphp
                <tr>
                    <td class="text-center font-bold">{{ $txDate }}</td>
                    <td class="text-right font-bold">{{ $txBank ? number_format($txBank, 0, ',', ' ') : '' }}</td>
                    <td class="text-right font-bold">{{ $txCash ? number_format($txCash, 0, ',', ' ') : '' }}</td>

                    <td class="text-center font-bold">{{ $refDate }}</td>
                    <td class="text-right font-bold">{{ $refBank ? number_format($refBank, 0, ',', ' ') : '' }}</td>
                    <td class="text-right font-bold">{{ $refCash ? number_format($refCash, 0, ',', ' ') : '' }}</td>

                    <td class="text-left" style="font-size: 10px; color: #4b5563;">{{ $note }}</td>
                </tr>
            @endfor

            <!-- TOTALS ROW (Exact sample layout) -->
            <tr style="font-weight: bold; background-color: #f8fafc; font-size: 11px;">
                <td class="text-center">Жами</td>
                <td class="text-right">{{ $sumIncBank ? number_format($sumIncBank, 0, ',', ' ') : '-' }}</td>
                <td class="text-right">{{ $sumIncCash ? number_format($sumIncCash, 0, ',', ' ') : '-' }}</td>
                <td class="text-center">{{ ($sumExpBank || $sumExpCash) ? '' : '-' }}</td>
                <td class="text-right">{{ $sumExpBank ? number_format($sumExpBank, 0, ',', ' ') : '-' }}</td>
                <td class="text-right">{{ $sumExpCash ? number_format($sumExpCash, 0, ',', ' ') : '-' }}</td>
                <td class="text-right font-bold" style="font-size: 12px;">
                    {{ number_format($netBalance, 2, ',', ' ') }}
                </td>
            </tr>
        </tbody>
    </table>

</body>
</html>

            </td>
        </tr>
    </table>

</body>
</html>
