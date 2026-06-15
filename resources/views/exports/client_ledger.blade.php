<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        body { font-family: 'DejaVu Sans', 'Arial', sans-serif; font-size: 10px; line-height: 1.4; color: #000; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; margin-bottom: 15px; table-layout: auto; }
        th, td { border: 1px solid #000; padding: 4px 2px; text-align: center; word-wrap: break-word; }
        th { background-color: #f2f2f2; font-size: 10px; }
        .header-title { font-size: 16px; text-transform: uppercase; margin-bottom: 5px; }
        .header-sub { font-size: 12px; margin-bottom: 15px; color: #555; }
        .info-table { border: none; margin-bottom: 20px; }
        .info-table td { border: none; text-align: left; padding: 4px; }
        .text-danger { color: #d9534f; }
        .text-success { color: #5cb85c; }
    </style>
</head>
<body>

    <div class="text-center">
        @if(isset($type) && $type === 'down_payment')
            <div class="header-title font-bold">MIJOZNING SHAXSIY VARAQASI (BOSHLANG'ICH TO'LOV GRAFIGI)</div>
        @elseif(isset($type) && $type === 'installments')
            <div class="header-title font-bold">MIJOZNING SHAXSIY VARAQASI (ASOSIY SHARTNOMA GRAFIGI)</div>
        @else
            <div class="header-title font-bold">MIJOZNING SHAXSIY VARAQASI (LEDGER)</div>
        @endif
        <div class="header-sub">Akt Sverka / Shaxsiy Varaqa</div>
    </div>

    <table class="info-table">
        <tr>
            <td width="15%"><strong>Mijoz F.I.Sh:</strong></td>
            <td width="35%">{{ $contract->client->full_name }}</td>
            <td width="20%"><strong>Shartnoma ID:</strong></td>
            <td width="30%">{{ $contract->contract_number ?: '#' . str_pad($contract->id, 5, '0', STR_PAD_LEFT) }}</td>
        </tr>
        <tr>
            <td><strong>Telefon:</strong></td>
            <td>{{ $contract->client->phone }}</td>
            <td><strong>Obyekt / Kvartira:</strong></td>
            <td>
                @if($contract->unit)
                    {{ $contract->unit->floor->building->name ?? 'N/A' }}{{ ($contract->unit->floor->building->block_name || $contract->unit->floor->building->corpus_name) ? ' (' . ($contract->unit->floor->building->block_name ?: $contract->unit->floor->building->corpus_name) . ')' : '' }} - {{ $contract->unit->type }} ({{ $contract->unit->area_sqm }} m2)
                @else
                    N/A
                @endif
            </td>
        </tr>
        <tr>
            <td><strong>PINFL/Pasport:</strong></td>
            <td>{{ $contract->client->pinfl }} / {{ $contract->client->passport_series }}</td>
            <td><strong>Umumiy summa:</strong></td>
            <td>{{ number_format($contract->total_amount, 2) }}</td>
        </tr>
        <tr>
            <td><strong>To'lanmagan qoldiq summadan penya:</strong></td>
            <td>{{ $contract->daily_penalty_rate }}% kuniga</td>
            <td><strong>Jami jarima:</strong></td>
            <td class="text-danger">{{ number_format($contract->total_penalty_accumulated, 2) }}</td>
        </tr>
        <tr>
            <td><strong>Boshlang'ich to'lov:</strong></td>
            <td>{{ number_format($contract->down_payment, 2) }}</td>
            <td><strong>Qolgan qarz:</strong></td>
            <td>{{ number_format($contract->total_amount - $contract->down_payment, 2) }}</td>
        </tr>
    </table>

    @php
        $isSplit = (bool)$contract->is_down_payment_split;
        $splitMonths = (int)($contract->down_payment_split_months ?? 1);
        
        $allSchedules = $contract->paymentSchedules->sortBy('due_date');
        $transactions = $contract->transactions->filter(function($t) {
            return $t->type === 'income' && $t->status === 'approved';
        })->sortBy(function($t) {
            return $t->transaction_date ?: $t->created_at;
        });

        $calculateRows = function($schedules, $startingBalance, &$remainingPayments) use ($contract) {
            $firstDueDate = $schedules->first() ? \Carbon\Carbon::parse($schedules->first()->due_date) : null;
            
            $preTransactions = collect();
            $activePayments = collect();
            
            foreach($remainingPayments as $t) {
                $tDate = \Carbon\Carbon::parse($t->transaction_date ?: $t->created_at);
                if ($firstDueDate && $tDate->lt($firstDueDate)) {
                    $preTransactions->push($t);
                } else {
                    $activePayments->push($t);
                }
            }
            
            $running_balance = (float)$startingBalance;
            foreach($preTransactions as $t) {
                $running_balance -= (float)$t->amount;
            }
            
            $rows = [];
            $totalExpected = 0;
            $totalPaid = 0;
            
            foreach($schedules->values() as $index => $schedule) {
                $currentDueDate = \Carbon\Carbon::parse($schedule->due_date);
                $nextSchedule = $schedules->values()->get($index + 1);
                $nextDueDate = $nextSchedule ? \Carbon\Carbon::parse($nextSchedule->due_date) : null;
                
                $running_balance += (float)$schedule->expected_amount;
                $totalExpected += (float)$schedule->expected_amount;
                
                $periodTransactions = collect();
                $nextRemaining = collect();
                
                foreach($activePayments as $t) {
                    $tDate = \Carbon\Carbon::parse($t->transaction_date ?: $t->created_at);
                    $isAfterOrEqualCurrent = $tDate->gte($currentDueDate);
                    $isBeforeNext = $nextDueDate ? $tDate->lt($nextDueDate) : true;
                    
                    if ($isAfterOrEqualCurrent && $isBeforeNext) {
                        $periodTransactions->push($t);
                    } else {
                        $nextRemaining->push($t);
                    }
                }
                
                $periodPaidSum = (float)$periodTransactions->sum('amount');
                $totalPaid += $periodPaidSum;
                
                $periodPaymentDates = [];
                $periodPaymentMethods = [];
                
                foreach($periodTransactions as $t) {
                    $dStr = \Carbon\Carbon::parse($t->transaction_date ?: $t->created_at)->format('d.m.Y');
                    if (!in_array($dStr, $periodPaymentDates)) {
                        $periodPaymentDates[] = $dStr;
                    }
                    $method = $t->payment_method ?: 'naqd';
                    if (!in_array($method, $periodPaymentMethods)) {
                        $periodPaymentMethods[] = $method;
                    }
                }
                
                $running_balance -= $periodPaidSum;
                
                $status = 'pending';
                if ($running_balance <= 0) {
                    $status = 'paid';
                } elseif ($periodPaidSum > 0) {
                    $status = 'partial';
                } elseif ($currentDueDate->isPast()) {
                    $status = 'overdue';
                }
                
                $rows[] = (object)[
                    'id' => $schedule->id,
                    'due_date' => $schedule->due_date,
                    'expected_amount' => $schedule->expected_amount,
                    'actual_payment_date' => implode(', ', $periodPaymentDates) ?: '-',
                    'original_payment_amount' => $periodTransactions->first() ? $periodTransactions->first()->amount : 0,
                    'paid_amount' => $periodPaidSum,
                    'payment_method' => implode(', ', $periodPaymentMethods) ?: '-',
                    'remaining_debt' => $running_balance,
                    'penalty_for_this_month' => $schedule->penalty_for_this_month,
                    'status' => $status
                ];
                
                $activePayments = $nextRemaining;
            }
            
            $remainingPayments = $activePayments;
            return [
                'rows' => $rows,
                'totalExpected' => $totalExpected,
                'totalPaid' => $totalPaid,
                'running_balance' => $running_balance
            ];
        };

        // Run calculations
        $remainingPayments = clone $transactions;
        if ($isSplit) {
            $dpSchedules = $allSchedules->slice(0, $splitMonths);
            $instSchedules = $allSchedules->slice($splitMonths);
            
            $dpResult = $calculateRows($dpSchedules, 0, $remainingPayments);
            $instResult = $calculateRows($instSchedules, 0, $remainingPayments);
        } else {
            $instResult = $calculateRows($allSchedules, $contract->down_payment, $remainingPayments);
            $dpResult = null;
        }
        
        $showDownPayment = ($isSplit && (empty($type) || $type === 'down_payment'));
        $showInstallments = (empty($type) || $type === 'installments');
    @endphp

    @if($showDownPayment && $dpResult)
        <h3 style="margin-top: 20px; font-size: 13px; color: #d97706; text-transform: uppercase;">
            @if(empty($type)) 1. @endif Boshlang'ich to'lovni bo'lib to'lash grafigi (Alohida mijoz varaqasi)
        </h3>
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Reja sanasi</th>
                    <th>Haqiqiy to'lov sanasi</th>
                    <th>Kutilayotgan</th>
                    <th>Asl to'lov</th>
                    <th>To'langan</th>
                    <th>To'lov usuli</th>
                    <th>Qolgan qarz</th>
                    <th>Jarima</th>
                    <th>Holat</th>
                </tr>
            </thead>
            <tbody>
                @foreach($dpResult['rows'] as $index => $row)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ \Carbon\Carbon::parse($row->due_date)->format('d.m.Y') }}</td>
                        <td>{{ $row->actual_payment_date }}</td>
                        <td>{{ number_format($row->expected_amount, 2) }}</td>
                        <td>{{ $row->original_payment_amount > 0 ? number_format($row->original_payment_amount, 2) : '-' }}</td>
                        <td>{{ number_format($row->paid_amount, 2) }}</td>
                        <td style="text-transform: uppercase; font-size: 9px;">{{ $row->payment_method }}</td>
                        <td>
                            @if($row->remaining_debt > 0)
                                <span class="text-danger">{{ number_format($row->remaining_debt, 2) }}</span>
                            @else
                                0.00
                            @endif
                        </td>
                        <td>
                            @if($row->penalty_for_this_month > 0)
                                <span class="text-danger">{{ number_format($row->penalty_for_this_month, 2) }}</span>
                            @else
                                0.00
                            @endif
                        </td>
                        <td>
                            @if($row->status == 'paid')
                                <strong class="text-success">TO'LANGAN</strong>
                            @elseif($row->status == 'partial')
                                <strong>QISMAN TO'LANGAN</strong>
                            @else
                                <strong class="text-danger">KUTILMOQDA</strong>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-right font-bold">JAMI:</td>
                    <td class="font-bold">{{ number_format($dpResult['totalExpected'], 2) }}</td>
                    <td></td>
                    <td class="font-bold">{{ number_format($dpResult['totalPaid'], 2) }}</td>
                    <td></td>
                    <td class="font-bold text-danger">{{ number_format($dpResult['running_balance'], 2) }}</td>
                    <td class="font-bold text-danger">0.00</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    @endif

    @if($showInstallments && $instResult)
        @if($isSplit)
            <h3 style="margin-top: 30px; font-size: 13px; color: #4f46e5; text-transform: uppercase;">
                @if(empty($type)) 2. @endif Asosiy shartnoma bo'lib to'lash grafigi
            </h3>
        @endif
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Reja sanasi</th>
                    <th>Haqiqiy to'lov sanasi</th>
                    <th>Kutilayotgan</th>
                    <th>Asl to'lov</th>
                    <th>To'langan</th>
                    <th>To'lov usuli</th>
                    <th>Qolgan qarz</th>
                    <th>Jarima</th>
                    <th>Holat</th>
                </tr>
            </thead>
            <tbody>
                @foreach($instResult['rows'] as $index => $row)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ \Carbon\Carbon::parse($row->due_date)->format('d.m.Y') }}</td>
                        <td>{{ $row->actual_payment_date }}</td>
                        <td>{{ number_format($row->expected_amount, 2) }}</td>
                        <td>{{ $row->original_payment_amount > 0 ? number_format($row->original_payment_amount, 2) : '-' }}</td>
                        <td>{{ number_format($row->paid_amount, 2) }}</td>
                        <td style="text-transform: uppercase; font-size: 9px;">{{ $row->payment_method }}</td>
                        <td>
                            @if($row->remaining_debt > 0)
                                <span class="text-danger">{{ number_format($row->remaining_debt, 2) }}</span>
                            @else
                                0.00
                            @endif
                        </td>
                        <td>
                            @if($row->penalty_for_this_month > 0)
                                <span class="text-danger">{{ number_format($row->penalty_for_this_month, 2) }}</span>
                            @else
                                0.00
                            @endif
                        </td>
                        <td>
                            @if($row->status == 'paid')
                                <strong class="text-success">TO'LANGAN</strong>
                            @elseif($row->status == 'partial')
                                <strong>QISMAN TO'LANGAN</strong>
                            @else
                                <strong class="text-danger">KUTILMOQDA</strong>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-right font-bold">JAMI:</td>
                    <td class="font-bold">{{ number_format($instResult['totalExpected'], 2) }}</td>
                    <td></td>
                    <td class="font-bold">{{ number_format($instResult['totalPaid'], 2) }}</td>
                    <td></td>
                    <td class="font-bold text-danger">{{ number_format($instResult['running_balance'], 2) }}</td>
                    <td class="font-bold text-danger">{{ number_format($contract->total_penalty_accumulated, 2) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    @endif

    @php
        $payments = $contract->transactions->filter(function($t) {
            return $t->type === 'income' && $t->status === 'approved';
        });
    @endphp

    @if($payments->count() > 0)
        <h3 style="margin-top: 30px; font-size: 14px; text-align: left; color: green; text-transform: uppercase;">Mijoz tomonidan amalga oshirilgan to'lovlar tarixi (Kirim)</h3>
        <table>
            <thead>
                <tr>
                    <th>#ID</th>
                    <th>Sana</th>
                    <th>To'lov usuli</th>
                    <th>Summa</th>
                    <th>Izoh</th>
                </tr>
            </thead>
            <tbody>
                @foreach($payments as $transaction)
                    <tr>
                        <td>#{{ $transaction->id }}</td>
                        <td>{{ \Carbon\Carbon::parse($transaction->transaction_date ?: $transaction->created_at)->format('d.m.Y') }}</td>
                        <td style="text-transform: uppercase; font-size: 9px;">{{ $transaction->payment_method ?: '-' }}</td>
                        <td style="color: green; font-weight: bold;">{{ number_format($transaction->amount, 2) }}</td>
                        <td style="text-align: left;">{{ $transaction->notes ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @php
        $refundTransactions = $contract->transactions->filter(function($t) {
            return $t->category && in_array($t->category->name, ['Mijozga pulni qaytarish', 'Mijozga to\'lov qilish']);
        });
    @endphp

    @if(in_array($contract->status, ['cancelled', 'canceled']) && $refundTransactions->count() > 0)
        <h3 style="margin-top: 30px; font-size: 14px; text-align: left; color: red;">MIJOZGA QAYTARILGAN TO'LOVLAR TARIXI (REFUNDS)</h3>
        <table class="table" style="margin-top: 10px;">
            <thead>
                <tr>
                    <th>#ID</th>
                    <th>Sana</th>
                    <th>To'lov usuli</th>
                    <th>Summa</th>
                    <th>Izoh</th>
                </tr>
            </thead>
            <tbody>
                @foreach($refundTransactions as $transaction)
                    <tr>
                        <td>#{{ $transaction->id }}</td>
                        <td>{{ \Carbon\Carbon::parse($transaction->created_at)->format('d.m.Y H:i') }}</td>
                        <td style="text-transform: uppercase;">{{ $transaction->payment_method }}</td>
                        <td style="color: red; font-weight: bold;">{{ number_format($transaction->amount, 2) }}</td>
                        <td>{{ $transaction->notes }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="font-bold text-center" style="margin-top: 50px;">TOMONLARNING HUQUQIY MANZILLARI VA BANK REKVIZITLARI:</div>
    <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
        <tr>
            <th width="50%" style="text-align: center; border: 1px solid #000; padding: 8px;">«ХАРИДОР»</th>
            <th width="50%" style="text-align: center; border: 1px solid #000; padding: 8px;">«КОРХОНА»</th>
        </tr>
        <tr>
            <td style="text-align: left; vertical-align: top; border: 1px solid #000; padding: 8px;">
                <div style="margin-bottom: 5px;"><strong>Ф.И.Ш: {{ $contract->client->full_name }}</strong></div>
                <div style="margin-bottom: 5px;"><strong>Паспорт серия:</strong> {{ $contract->client->passport_series }}</div>
                <div style="margin-bottom: 5px;"><strong>Паспорт берилган вақти:</strong> {{ $contract->client->passport_issued_date ? \Carbon\Carbon::parse($contract->client->passport_issued_date)->format('d.m.Y') : '' }}<br>{{ $contract->client->passport_issued_by }} томонидан берилган</div>
                <div style="margin-bottom: 5px;"><strong>Манзил:</strong> {{ $contract->client->address }}</div>
                <div style="margin-bottom: 5px;"><strong>ЖШШИР:</strong> {{ $contract->client->pinfl }}</div>
                <div style="margin-bottom: 5px;"><strong>Телефон:</strong> {{ $contract->client->phone }}</div>
                <div style="margin-top: 20px;"><strong>Ҳаридор: ______________________</strong></div>
            </td>
            <td style="text-align: left; vertical-align: top; border: 1px solid #000; padding: 8px;">
                <div style="margin-bottom: 5px;"><strong>«Шер-Дор» Хусусий корхонаси</strong></div>
                <div style="margin-bottom: 5px;"><strong>Манзил:</strong> Ургут туман Дўстлик махалласи Навои-Шох кўчаси №3-уй</div>
                <div style="margin-bottom: 5px;"><strong>Х/Р:</strong> 2020 8000 6005 5796 1001</div>
                <div style="margin-bottom: 5px;"><strong>Банк:</strong> АТБ “Агробанк” Ургут туман филиали,</div>
                <div style="margin-bottom: 5px;"><strong>МФО:</strong> 00300, <strong>ИНН:</strong> 202 162 496</div>
                <div style="margin-bottom: 5px;"><strong>Телефон:</strong> (97) 708-44-44, (93) 573-29-29</div>
                <div style="margin-top: 20px;"><strong>Директор: ______________________</strong></div>
            </td>
        </tr>
    </table>

</body>
</html>
