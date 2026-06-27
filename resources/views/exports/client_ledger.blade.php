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
        <x:ExcelWorkbook>
            <x:ExcelWorksheets>
                <x:ExcelWorksheet>
                    <x:Name>Shaxsiy Varaqa</x:Name>
                    <x:WorksheetOptions>
                        <x:Print>
                            <x:ValidPrinterInfo/>
                            <x:PaperSizeIndex>9</x:PaperSizeIndex> <!-- A4 -->
                            <x:HorizontalResolution>600</x:HorizontalResolution>
                            <x:VerticalResolution>600</x:VerticalResolution>
                        </x:Print>
                        <x:Selected/>
                        <x:DoNotDisplayGridlines/>
                    </x:WorksheetOptions>
                </x:ExcelWorksheet>
            </x:ExcelWorksheets>
        </x:ExcelWorkbook>
    </xml>
    <![endif]-->
    <style>
        @page { size: A4 portrait; margin: 6mm 8mm; }
        body { font-family: 'DejaVu Sans', 'Arial', sans-serif; font-size: 9px; line-height: 1.25; color: #000; margin: 0; padding: 0; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        
        .header-title { font-size: 12px; font-weight: bold; text-align: center; margin-bottom: 2px; text-transform: uppercase; }
        .header-subtitle { font-size: 10px; font-weight: bold; text-align: center; margin-bottom: 12px; text-transform: uppercase; }
        
        .info-layout-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .info-layout-table td { border: none; padding: 0; vertical-align: top; }
        
        .nested-info-table { border-collapse: collapse; width: 99%; margin: 0 auto; }
        .nested-info-table td { border: 1px solid #000; padding: 3px 5px; font-size: 8px; text-align: left; }
        .nested-info-table td.label-cell { font-weight: bold; background-color: #f9f9f9; width: 50%; }
        .nested-info-table td.value-cell { text-align: right; }
        .nested-info-table.borderless td { border: none; background: none; }
        .nested-info-table.borderless td.label-cell { font-weight: bold; width: 40%; }
        .nested-info-table.borderless td.value-cell { text-align: left; }
        
        .main-table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 10px; }
        .main-table th, .main-table td { border: 1px solid #000; padding: 4px 3px; font-size: 8px; text-align: center; }
        .main-table th.yellow-header { background-color: #f2a900; color: #fff; font-weight: bold; font-size: 9px; }
        .main-table th.gray-header { background-color: #555555; color: #fff; font-weight: bold; font-size: 9px; }
        .main-table th.sub-header { background-color: #f2f2f2; font-weight: bold; font-size: 8px; color: #333; }
        
        .summary-table { width: 50%; border-collapse: collapse; margin: 10px auto; }
        .summary-table td { border: 1px solid #000; padding: 4px 6px; font-size: 8px; font-weight: bold; }
        .summary-table td.label-cell { text-align: left; background-color: #f9f9f9; }
        .summary-table td.value-cell { text-align: right; }
        .summary-table td.highlight { background-color: #f2f2f2; }
        
        .footer-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .footer-table td { border: none; padding: 4px; font-size: 8px; vertical-align: top; }
    </style>
</head>
<body>

    @php
        $allSchedules = $contract->paymentSchedules->sortBy('due_date');
        $allTransactions = $contract->transactions->filter(function($t) {
            return $t->status === 'approved';
        });
        
        $incomes = $allTransactions->filter(function($t) {
            return $t->type === 'income';
        })->sortBy(function($t) {
            return $t->transaction_date ?: $t->created_at;
        });

        $refunds = $allTransactions->filter(function($t) {
            return $t->type === 'expense' && $t->category && 
                in_array($t->category->name, ['Mijozga pulni qaytarish', 'Mijozga to\'lov qilish']);
        })->sortBy(function($t) {
            return $t->transaction_date ?: $t->created_at;
        });

        // Build the combined, split rows chronologically (matching Vue view)
        $rows = [];
        $totalPaidAll = 0;
        $totalRefundedAll = 0;
        $totalExpectedInstallment = 0;
        $running_balance = 0;

        // 1. Add all approved income transactions as completed rows
        foreach ($incomes as $t) {
            $convAmount = $t->converted_amount ?? $t->amount;
            $running_balance += $convAmount;
            $totalPaidAll += $convAmount;

            // Determine type
            $pType = 'installment';
            $catName = $t->category ? strtolower($t->category->name) : '';
            $notesText = strtolower($t->notes ?: '');
            if (str_contains($catName, "boshlang") || str_contains($catName, "initial") ||
                str_contains($notesText, "boshlang") || str_contains($notesText, "initial")) {
                $pType = 'down_payment';
            }

            $rows[] = (object)[
                'is_txn' => true,
                'date' => \Carbon\Carbon::parse($t->transaction_date ?: $t->created_at)->startOfDay()->timestamp,
                'due_date_label' => '',
                'expected_amount' => null,
                'paid_date' => \Carbon\Carbon::parse($t->transaction_date ?: $t->created_at)->format('d.m.Y'),
                'paid_amount' => $convAmount,
                'payment_method' => $t->payment_method ?: 'Naqd',
                'refund_date' => null,
                'refund_amount' => null,
                'qoldiq' => $running_balance,
                'is_down_payment' => ($pType === 'down_payment')
            ];
        }

        // 2. Add all approved refunds
        foreach ($refunds as $r) {
            $running_balance -= $r->amount;
            $totalRefundedAll += $r->amount;

            $rows[] = (object)[
                'is_txn' => true,
                'date' => \Carbon\Carbon::parse($r->transaction_date ?: $r->created_at)->startOfDay()->timestamp,
                'due_date_label' => '',
                'expected_amount' => null,
                'paid_date' => null,
                'paid_amount' => null,
                'payment_method' => null,
                'refund_date' => \Carbon\Carbon::parse($r->transaction_date ?: $r->created_at)->format('d.m.Y'),
                'refund_amount' => $r->amount,
                'qoldiq' => $running_balance,
                'is_down_payment' => false
            ];
        }

        // 3. Add all unpaid/partially paid schedules
        foreach ($allSchedules as $s) {
            $remaining = $s->expected_amount - $s->paid_amount;
            if ($remaining > 0.01) {
                $running_balance -= $remaining;
                $totalExpectedInstallment += $remaining;

                $rows[] = (object)[
                    'is_txn' => false,
                    'date' => \Carbon\Carbon::parse($s->due_date)->startOfDay()->timestamp,
                    'due_date_label' => ($s->payment_type === 'down_payment' ? "Bosh to'lov (" : "") . \Carbon\Carbon::parse($s->due_date)->format('d.m.Y') . ($s->payment_type === 'down_payment' ? ")" : ""),
                    'expected_amount' => $remaining,
                    'paid_date' => null,
                    'paid_amount' => null,
                    'payment_method' => null,
                    'refund_date' => null,
                    'refund_amount' => null,
                    'qoldiq' => $running_balance,
                    'is_down_payment' => ($s->payment_type === 'down_payment')
                ];
            }
        }

        // 4. Sort chronologically
        usort($rows, function($a, $b) {
            if ($a->date !== $b->date) {
                return $a->date - $b->date;
            }
            if ($a->is_txn !== $b->is_txn) {
                return $a->is_txn ? -1 : 1;
            }
            return 0;
        });

        // 5. Re-calculate the running balance in chronological order so the ledger math is 100% correct
        $running_balance = 0;
        foreach ($rows as $row) {
            if ($row->is_txn) {
                if ($row->refund_amount) {
                    $running_balance -= $row->refund_amount;
                } else {
                    $running_balance += $row->paid_amount;
                }
            } else {
                $running_balance -= $row->expected_amount;
            }
            $row->qoldiq = $running_balance;
        }

        // Calculate totals for summary box
        $bankPaid = 0;
        $cashPaid = 0;
        foreach ($incomes as $t) {
            $method = strtolower($t->payment_method ?: '');
            if ($method === 'bank') {
                $bankPaid += ($t->converted_amount ?? $t->amount);
            } else {
                $cashPaid += ($t->converted_amount ?? $t->amount);
            }
        }
        
        $totalPenalty = $contract->total_penalty_accumulated ?: 0;
        $remainingDebt = $contract->total_amount - ($totalPaidAll - $totalRefundedAll) + $totalPenalty;
    @endphp

    <div class="header-title">"SHER-DOR" XUSUSIY KORXONASI</div>
    <div class="header-subtitle">MIJOZNING SHAXSIY VARAQASI</div>

    <!-- General Info Grid -->
    <table class="info-layout-table">
        <tr>
            <!-- Left Side: Contract details -->
            <td style="width: 33%;">
                <table class="nested-info-table">
                    <tr>
                        <td class="label-cell">Shartnoma sanasi</td>
                        <td class="value-cell">{{ $contract->created_at ? \Carbon\Carbon::parse($contract->created_at)->format('d.m.Y') : '___' }}</td>
                    </tr>
                    <tr>
                        <td class="label-cell">Shartnoma raqami:</td>
                        <td class="value-cell">{{ $contract->contract_number ?: '#' . str_pad($contract->id, 5, '0', STR_PAD_LEFT) }}</td>
                    </tr>
                    <tr>
                        <td class="label-cell">Qavat</td>
                        <td class="value-cell">{{ $contract->floor ?? ($contract->unit && $contract->unit->floor ? $contract->unit->floor->number : '___') }}</td>
                    </tr>
                    <tr>
                        <td class="label-cell">Xonadon raqami</td>
                        <td class="value-cell" style="mso-number-format:'\@'; vnd.ms-excel.numberformat:@;">{{ $contract->unit ? $contract->unit->unit_number : '___' }}</td>
                    </tr>
                    <tr>
                        <td class="label-cell">Xonadon maydoni</td>
                        <td class="value-cell">{{ $contract->unit ? number_format($contract->unit->area_sqm, 2, ',', ' ') : '___' }}</td>
                    </tr>
                </table>
            </td>
            
            <!-- Middle Side: Client details -->
            <td style="width: 34%;">
                <table class="nested-info-table borderless">
                    <tr>
                        <td class="label-cell" style="text-align: right; padding-right: 10px;">Mijoz F.I.Sh:</td>
                        <td class="value-cell" style="text-align: left; font-weight: bold;">{{ $contract->client->full_name }}</td>
                    </tr>
                    <tr>
                        <td class="label-cell" style="text-align: right; padding-right: 10px;">PINFL:</td>
                        <td class="value-cell" style="text-align: left; mso-number-format:'\@'; vnd.ms-excel.numberformat:@;">{{ $contract->client->pinfl }}</td>
                    </tr>
                    <tr>
                        <td class="label-cell" style="text-align: right; padding-right: 10px;">Pasport raqami:</td>
                        <td class="value-cell" style="text-align: left; mso-number-format:'\@'; vnd.ms-excel.numberformat:@;">{{ $contract->client->passport_number ?: $contract->client->passport_series }}</td>
                    </tr>
                    <tr>
                        <td class="label-cell" style="text-align: right; padding-right: 10px;">Telefon raqami:</td>
                        <td class="value-cell" style="text-align: left; mso-number-format:'\@'; vnd.ms-excel.numberformat:@;">{{ $contract->client->phone }}</td>
                    </tr>
                </table>
            </td>
            
            <!-- Right Side: Financial details -->
            <td style="width: 33%;">
                <table class="nested-info-table">
                    <tr>
                        <td class="label-cell">Xonadon umumiy narxi</td>
                        <td class="value-cell" style="font-weight: bold;">{{ number_format($contract->total_amount, 0, ',', ' ') }}</td>
                    </tr>
                    <tr>
                        <td class="label-cell">Boshlang'ich to'lov</td>
                        <td class="value-cell">{{ number_format($contract->down_payment, 0, ',', ' ') }}</td>
                    </tr>
                    <tr>
                        <td class="label-cell">To'lov muddati</td>
                        <td class="value-cell">{{ $contract->term_months }} oy</td>
                    </tr>
                    <tr>
                        <td class="label-cell">Qoldiq so'mma</td>
                        <td class="value-cell" style="font-weight: bold;">{{ number_format($contract->total_amount - $contract->down_payment, 0, ',', ' ') }}</td>
                    </tr>
                    <tr>
                        <td class="label-cell">Oylik to'lov so'mmasi</td>
                        <td class="value-cell" style="font-weight: bold;">{{ number_format($contract->paymentSchedules->first() ? $contract->paymentSchedules->first()->expected_amount : (($contract->total_amount - $contract->down_payment) / ($contract->term_months ?: 1)), 0, ',', ' ') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Main Payment Ledger Table -->
    <table class="main-table" style="width: 100%; border-collapse: collapse;">
        <colgroup>
            <col style="width: 15%;">
            <col style="width: 13%;">
            <col style="width: 12%;">
            <col style="width: 13%;">
            <col style="width: 11%;">
            <col style="width: 12%;">
            <col style="width: 12%;">
            <col style="width: 12%;">
        </colgroup>
        <thead>
            <tr>
                <th colspan="2" class="yellow-header">Kutilayotgan to'lov</th>
                <th colspan="2" class="yellow-header">Haqiqatda qilingan to'lov</th>
                <th rowspan="2" class="yellow-header" style="vertical-align: middle;">To'lov turi</th>
                <th colspan="2" class="yellow-header">Mijozga qaytarilgan</th>
                <th rowspan="2" class="gray-header" style="vertical-align: middle;">Qoldiq</th>
            </tr>
            <tr>
                <th class="sub-header" style="width: 12%;">To'lov sanasi</th>
                <th class="sub-header" style="width: 13%;">Oylik to'lov so'mmasi</th>
                <th class="sub-header" style="width: 12%;">To'langan sana</th>
                <th class="sub-header" style="width: 13%;">To'langan so'mma</th>
                <th class="sub-header" style="width: 12%;">Qaytarilgan sana</th>
                <th class="sub-header" style="width: 13%;">Qaytarilgan so'mma</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td class="font-bold">{{ $row->due_date_label }}</td>
                    <td class="font-bold text-right">{{ $row->expected_amount ? number_format($row->expected_amount, 0, ',', ' ') : '' }}</td>
                    <td>{{ $row->paid_date ?: '' }}</td>
                    <td class="text-right">{{ $row->paid_amount ? number_format($row->paid_amount, 0, ',', ' ') : '' }}</td>
                    <td class="font-bold" style="text-transform: capitalize;">
                        @if($row->payment_method)
                            @php
                                $m = strtolower($row->payment_method);
                                if ($m === 'bank') echo 'Bank';
                                elseif ($m === 'naqd' || $m === 'cash') echo 'Naqd';
                                elseif ($m === 'plastik' || $m === 'karta') echo 'Plastik';
                                else echo $row->payment_method;
                            @endphp
                        @endif
                    </td>
                    <td>{{ $row->refund_date ?: '' }}</td>
                    <td class="text-right" style="color: red;">{{ $row->refund_amount ? number_format($row->refund_amount, 0, ',', ' ') : '' }}</td>
                    <td class="font-bold text-right" style="{{ $row->qoldiq < 0 ? 'color: red;' : '' }}">
                        @if($row->qoldiq !== null)
                            {{ $row->qoldiq < 0 ? '-' : '' }} {{ number_format(abs($row->qoldiq), 0, ',', ' ') }}
                        @endif
                    </td>
                </tr>
            @endforeach
            
            <!-- Totals row -->
            <tr style="background-color: #f9f9f9; font-weight: bold;">
                <td>JAMI:</td>
                <td class="text-right">{{ number_format($totalExpectedInstallment, 0, ',', ' ') }}</td>
                <td></td>
                <td class="text-right">{{ number_format($totalPaidAll, 0, ',', ' ') }}</td>
                <td></td>
                <td></td>
                <td class="text-right" style="color: red;">{{ number_format($totalRefundedAll, 0, ',', ' ') }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <!-- Bottom Summary Box -->
    <table class="summary-table">
        <tr>
            <td class="label-cell">Naqt to'lov so'mmasi</td>
            <td class="value-cell">{{ number_format($cashPaid, 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td class="label-cell">Bankga to'lov so'mmasi</td>
            <td class="value-cell">{{ number_format($bankPaid, 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td class="label-cell">Jami qaytarilgan so'mma</td>
            <td class="value-cell" style="color: red;">{{ number_format($totalRefundedAll, 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td class="label-cell">Penya so'mmasi</td>
            <td class="value-cell" style="color: red;">{{ number_format($totalPenalty, 0, ',', ' ') }}</td>
        </tr>
        <tr>
            <td class="label-cell highlight">Umumiy qoldiq so'mma</td>
            <td class="value-cell highlight" style="{{ $remainingDebt > 0 ? 'color: red;' : '' }}">{{ number_format($remainingDebt, 0, ',', ' ') }}</td>
        </tr>
    </table>

    <!-- Footer Signatures -->
    <table class="footer-table">
        <tr>
            <td style="width: 50%;">
                <div class="font-bold" style="text-decoration: underline;">«XARIDOR»</div>
                <div style="margin-top: 15px;">F.I.Sh: &nbsp; &nbsp; {{ $contract->client->full_name }}</div>
                <div style="margin-top: 15px;">Imzo: &nbsp; &nbsp; ____________________</div>
            </td>
            <td style="width: 50%; text-align: right;">
                <div class="font-bold" style="text-decoration: underline; text-align: right;">«KORXONA»</div>
                <div style="margin-top: 15px; text-align: right;">«SHER-DOR» XK direktor: J. Raxmonov</div>
                <div style="margin-top: 15px; text-align: right;">Imzo: &nbsp; &nbsp; ____________________ &nbsp; (M.O'.)</div>
            </td>
        </tr>
    </table>

</body>
</html>
