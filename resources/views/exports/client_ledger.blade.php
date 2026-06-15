<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Mijozning Shaxsiy Varaqasi</title>
    <style>
        @page { size: A4 landscape; margin: 8mm; }
        body { font-family: 'DejaVu Sans', 'Arial', sans-serif; font-size: 10px; line-height: 1.3; color: #000; margin: 0; padding: 0; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        
        .header-title { font-size: 14px; font-weight: bold; text-align: center; margin-bottom: 2px; text-transform: uppercase; }
        .header-subtitle { font-size: 12px; font-weight: bold; text-align: center; margin-bottom: 15px; text-transform: uppercase; }
        
        .info-layout-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .info-layout-table td { border: none; padding: 0; vertical-align: top; }
        
        .nested-info-table { border-collapse: collapse; width: 98%; margin: 0 auto; }
        .nested-info-table td { border: 1px solid #000; padding: 4px 6px; font-size: 10px; text-align: left; }
        .nested-info-table td.label-cell { font-weight: bold; background-color: #f9f9f9; width: 50%; }
        .nested-info-table td.value-cell { text-align: right; }
        .nested-info-table.borderless td { border: none; background: none; }
        .nested-info-table.borderless td.label-cell { font-weight: bold; width: 40%; }
        .nested-info-table.borderless td.value-cell { text-align: left; }
        
        .main-table { width: 100%; border-collapse: collapse; margin-top: 15px; margin-bottom: 15px; }
        .main-table th, .main-table td { border: 1px solid #000; padding: 5px 3px; font-size: 9px; text-align: center; }
        .main-table th.yellow-header { background-color: #ffff00; font-weight: bold; font-size: 10px; }
        .main-table th.gray-header { background-color: #d9d9d9; font-weight: bold; font-size: 10px; }
        .main-table th.sub-header { background-color: #f2f2f2; font-weight: normal; font-size: 9px; }
        
        .summary-table { width: 45%; border-collapse: collapse; margin: 15px auto; }
        .summary-table td { border: 1px solid #000; padding: 5px 8px; font-size: 10px; font-weight: bold; }
        .summary-table td.label-cell { text-align: left; background-color: #f9f9f9; }
        .summary-table td.value-cell { text-align: right; }
        .summary-table td.highlight { background-color: #f2f2f2; }
        
        .footer-table { width: 100%; border-collapse: collapse; margin-top: 30px; }
        .footer-table td { border: none; padding: 5px; font-size: 10px; vertical-align: top; }
    </style>
</head>
<body>

    @php
        $isSplit = (bool)$contract->is_down_payment_split;
        $splitMonths = (int)($contract->down_payment_split_months ?? 1);
        
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

        $firstDueDate = $allSchedules->first() ? \Carbon\Carbon::parse($allSchedules->first()->due_date) : null;
        
        // Categorize incomes into Down Payment (pre-transactions) and Monthly Installments
        $preTransactions = collect();
        $activePayments = collect();
        
        foreach ($incomes as $t) {
            $tDate = \Carbon\Carbon::parse($t->transaction_date ?: $t->created_at);
            // If it occurred before first schedule due date (or if no schedules exist), it is down payment
            if ($firstDueDate && $tDate->lt($firstDueDate)) {
                $preTransactions->push($t);
            } else {
                $activePayments->push($t);
            }
        }

        $rows = [];
        $totalExpectedInstallment = 0;
        $totalPaidAll = 0;
        $totalRefundedAll = 0;

        // 1. Add down payment rows at the top
        foreach ($preTransactions as $t) {
            $rows[] = (object)[
                'is_down_payment' => true,
                'due_date_label' => "Boshlang'ich to'lov",
                'expected_amount' => null,
                'paid_date' => \Carbon\Carbon::parse($t->transaction_date ?: $t->created_at)->format('d.m.Y'),
                'paid_amount' => $t->amount,
                'payment_method' => $t->payment_method ?: 'Naqd',
                'refund_date' => null,
                'refund_amount' => null,
                'qoldiq' => null
            ];
            $totalPaidAll += $t->amount;
        }

        // 2. Add monthly installment rows
        $running_balance = 0;
        foreach ($allSchedules as $index => $schedule) {
            // Find payments associated with this schedule (or that fall within this month)
            $schedulePayments = $activePayments->filter(fn($p) => $p->payment_schedule_id == $schedule->id);
            
            if ($schedulePayments->isEmpty()) {
                $currentDueDate = \Carbon\Carbon::parse($schedule->due_date);
                $nextSchedule = $allSchedules->values()->get($index + 1);
                $nextDueDate = $nextSchedule ? \Carbon\Carbon::parse($nextSchedule->due_date) : null;
                
                $schedulePayments = $activePayments->filter(function($p) use ($currentDueDate, $nextDueDate) {
                    $tDate = \Carbon\Carbon::parse($p->transaction_date ?: $p->created_at);
                    return $tDate->gte($currentDueDate->copy()->subDays(5)) && ($nextDueDate ? $tDate->lt($nextDueDate->copy()->subDays(5)) : true);
                });
            }

            // Find refunds in this month
            $currentDueDate = \Carbon\Carbon::parse($schedule->due_date);
            $nextSchedule = $allSchedules->values()->get($index + 1);
            $nextDueDate = $nextSchedule ? \Carbon\Carbon::parse($nextSchedule->due_date) : null;
            
            $scheduleRefunds = $refunds->filter(function($r) use ($currentDueDate, $nextDueDate) {
                $rDate = \Carbon\Carbon::parse($r->transaction_date ?: $r->created_at);
                return $rDate->gte($currentDueDate->copy()->startOfMonth()) && ($nextDueDate ? $rDate->lt($nextDueDate->copy()->startOfMonth()) : true);
            });

            $paid_amount = $schedulePayments->sum('amount');
            $refunded_amount = $scheduleRefunds->sum('amount');
            
            $paid_date = $schedulePayments->isNotEmpty() ? \Carbon\Carbon::parse($schedulePayments->first()->transaction_date ?: $schedulePayments->first()->created_at)->format('d.m.Y') : '';
            
            // Map payment method label
            $methodLabel = '';
            if ($schedulePayments->isNotEmpty()) {
                $m = strtolower($schedulePayments->first()->payment_method ?: '');
                if ($m === 'naqd' || $m === 'cash') $methodLabel = 'Naqt';
                elseif ($m === 'plastik' || $m === 'card' || $m === 'karta') $methodLabel = 'Plastik';
                elseif ($m === 'bank') $methodLabel = 'Bank';
                else $methodLabel = ucfirst($schedulePayments->first()->payment_method);
            }
            
            $refund_date = $scheduleRefunds->isNotEmpty() ? \Carbon\Carbon::parse($scheduleRefunds->first()->transaction_date ?: $scheduleRefunds->first()->created_at)->format('d.m.Y') : '';

            $running_balance += ($paid_amount - $schedule->expected_amount - $refunded_amount);

            $rows[] = (object)[
                'is_down_payment' => false,
                'due_date_label' => \Carbon\Carbon::parse($schedule->due_date)->format('d.m.Y'),
                'expected_amount' => $schedule->expected_amount,
                'paid_date' => $paid_date,
                'paid_amount' => $paid_amount > 0 ? $paid_amount : null,
                'payment_method' => $methodLabel,
                'refund_date' => $refund_date ?: null,
                'refund_amount' => $refunded_amount > 0 ? $refunded_amount : null,
                'qoldiq' => $running_balance
            ];

            $totalExpectedInstallment += $schedule->expected_amount;
            $totalPaidAll += $paid_amount;
            $totalRefundedAll += $refunded_amount;
        }

        // Categorize payments for summary box
        $bankPaid = 0;
        $cashPaid = 0;
        foreach ($incomes as $t) {
            $method = strtolower($t->payment_method ?: '');
            if ($method === 'bank') {
                $bankPaid += $t->amount;
            } else {
                $cashPaid += $t->amount;
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
                        <td class="value-cell">{{ $contract->unit ? $contract->unit->unit_number : '___' }}</td>
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
                        <td class="value-cell" style="text-align: left;">{{ $contract->client->pinfl }}</td>
                    </tr>
                    <tr>
                        <td class="label-cell" style="text-align: right; padding-right: 10px;">Pasport raqami:</td>
                        <td class="value-cell" style="text-align: left;">{{ $contract->client->passport_number ?: $contract->client->passport_series }}</td>
                    </tr>
                    <tr>
                        <td class="label-cell" style="text-align: right; padding-right: 10px;">Telefon raqami:</td>
                        <td class="value-cell" style="text-align: left;">{{ $contract->client->phone }}</td>
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
                        <td class="value-cell" style="font-weight: bold;">{{ number_format($contract->payment_schedules->first() ? $contract->payment_schedules->first()->expected_amount : (($contract->total_amount - $contract->down_payment) / ($contract->term_months ?: 1)), 0, ',', ' ') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Main Payment Ledger Table -->
    <table class="main-table">
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
