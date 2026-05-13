<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', 'Arial', sans-serif; font-size: 12px; line-height: 1.5; color: #000; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; margin-bottom: 20px; }
        th, td { border: 1px solid #000; padding: 6px; text-align: center; }
        th { background-color: #f2f2f2; }
        .header-title { font-size: 18px; text-transform: uppercase; margin-bottom: 5px; }
        .header-sub { font-size: 14px; margin-bottom: 20px; color: #555; }
        .info-table { border: none; margin-bottom: 20px; }
        .info-table td { border: none; text-align: left; padding: 4px; }
        .text-danger { color: #d9534f; }
        .text-success { color: #5cb85c; }
    </style>
</head>
<body>

    <div class="text-center">
        <div class="header-title font-bold">MIJOZNING SHAXSIY VARAQASI (LEDGER)</div>
        <div class="header-sub">Akt Sverka / Shaxsiy Varaqa</div>
    </div>

    <table class="info-table">
        <tr>
            <td width="15%"><strong>Mijoz F.I.Sh:</strong></td>
            <td width="35%">{{ $contract->client->full_name }}</td>
            <td width="20%"><strong>Shartnoma ID:</strong></td>
            <td width="30%">#{{ str_pad($contract->id, 5, '0', STR_PAD_LEFT) }}</td>
        </tr>
        <tr>
            <td><strong>Telefon:</strong></td>
            <td>{{ $contract->client->phone }}</td>
            <td><strong>Obyekt / Kvartira:</strong></td>
            <td>
                @if($contract->unit)
                    {{ $contract->unit->floor->building->name ?? 'N/A' }} - {{ $contract->unit->type }} ({{ $contract->unit->area_sqm }} m2)
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
            <td><strong>Jarima stavkasi:</strong></td>
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

    <table>
        <thead>
            <tr>
                <th>No.</th>
                <th>Reja sanasi</th>
                <th>Haqiqiy to'lov sanasi</th>
                <th>Kutilayotgan summa</th>
                <th>To'langan summa</th>
                <th>Qolgan qarz</th>
                <th>Hisoblangan jarima</th>
                <th>Holat</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $totalExpected = 0;
                $totalPaid = 0;
                $totalDebt = 0;
            @endphp
            @foreach($contract->paymentSchedules as $index => $schedule)
                @php
                    $totalExpected += $schedule->expected_amount;
                    $totalPaid += $schedule->paid_amount;
                    $totalDebt += $schedule->remaining_debt;
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ \Carbon\Carbon::parse($schedule->due_date)->format('d.m.Y') }}</td>
                    <td>
                        @if($schedule->actual_payment_date)
                            <div>{{ \Carbon\Carbon::parse($schedule->actual_payment_date)->format('d.m.Y') }}</div>
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ number_format($schedule->expected_amount, 2) }}</td>
                    <td>{{ number_format($schedule->paid_amount, 2) }}</td>
                    <td>
                        @if($schedule->remaining_debt > 0)
                            <span class="text-danger">{{ number_format($schedule->remaining_debt, 2) }}</span>
                        @else
                            0.00
                        @endif
                    </td>
                    <td>
                        @if($schedule->penalty_for_this_month > 0)
                            <span class="text-danger">{{ number_format($schedule->penalty_for_this_month, 2) }}</span>
                        @else
                            0.00
                        @endif
                    </td>
                    <td>
                        @if($schedule->status == 'paid')
                            <strong class="text-success">TO'LANGAN</strong>
                        @elseif($schedule->status == 'partial')
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
                <td class="font-bold">{{ number_format($totalExpected, 2) }}</td>
                <td class="font-bold">{{ number_format($totalPaid, 2) }}</td>
                <td class="font-bold text-danger">{{ number_format($totalDebt, 2) }}</td>
                <td class="font-bold text-danger">{{ number_format($contract->total_penalty_accumulated, 2) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

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
