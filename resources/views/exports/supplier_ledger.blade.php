<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ta'minotchi Hisoboti - {{ $supplier->company_name ?: $supplier->name }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 20px; }
        .info-table { width: 100%; margin-bottom: 20px; }
        .info-table td { padding: 5px; vertical-align: top; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items-table th, .items-table td { border: 1px solid #000; padding: 8px; text-align: left; }
        .items-table th { background-color: #f2f2f2; }
        .text-right { text-align: right; }
        .total { font-weight: bold; font-size: 14px; }
        .text-red { color: red; }
        .text-green { color: green; }
    </style>
</head>
<body>
    <div class="header">
        <h1>TA'MINOTCHI HISOBOTI (LEDGER)</h1>
        <p>Sana: <strong>{{ date('d.m.Y H:i') }}</strong></p>
    </div>

    <table class="info-table">
        <tr>
            <td width="50%">
                <strong>Kompaniya:</strong> {{ $supplier->company_name ?: $supplier->name }}<br>
                <strong>Vakil:</strong> {{ $supplier->name }}<br>
                <strong>Tel:</strong> {{ $supplier->phone }}<br>
                <strong>STIR:</strong> {{ $supplier->inn }}
            </td>
            <td width="50%" class="text-right">
                <strong>Joriy Balans:</strong> 
                <span class="{{ $supplier->balance > 0 ? 'text-red' : 'text-green' }}">
                    {{ number_format(abs($supplier->balance), 2, '.', ' ') }} UZS
                    ({{ $supplier->balance > 0 ? 'Qarzimiz bor' : ($supplier->balance < 0 ? 'Haqimiz bor' : 'Nol') }})
                </span>
            </td>
        </tr>
    </table>

    @php
        $ledger = collect();
        foreach($orders as $o) {
            $ledger->push([
                'date' => $o->order_date,
                'type' => 'order',
                'doc' => 'PO: ' . $o->order_number,
                'debit' => 0,
                'credit' => $o->total_amount,
                'notes' => 'Buyurtma ('.($o->status == 'delivered' ? 'Qabul qilingan' : 'Kutilmoqda').')'
            ]);
        }
        foreach($payments as $p) {
            $ledger->push([
                'date' => $p->transaction_date,
                'type' => 'payment',
                'doc' => 'To\'lov: ' . ucfirst($p->payment_method),
                'debit' => $p->amount,
                'credit' => 0,
                'notes' => $p->notes ?: 'Izohsiz'
            ]);
        }
        $ledger = $ledger->sortBy('date')->values();
        $running_balance = 0;
    @endphp

    <h3 style="margin-bottom: 5px;">Umumiy Hisob-kitob (Akt Sверка)</h3>
    <table class="items-table">
        <thead>
            <tr>
                <th>Sana</th>
                <th>Hujjat turi / Izoh</th>
                <th class="text-right">Kirim (Qarzimiz)</th>
                <th class="text-right">To'lov (Chiqim)</th>
                <th class="text-right">Qoldiq</th>
            </tr>
        </thead>
        <tbody>
            @forelse($ledger as $item)
            @php
                $running_balance += ($item['credit'] - $item['debit']);
            @endphp
            <tr>
                <td>{{ $item['date'] }}</td>
                <td>
                    <strong>{{ $item['doc'] }}</strong><br>
                    <span style="font-size: 10px; color: #555;">{{ $item['notes'] }}</span>
                </td>
                <td class="text-right text-red">{{ $item['credit'] > 0 ? number_format($item['credit'], 2, '.', ' ') : '-' }}</td>
                <td class="text-right text-green">{{ $item['debit'] > 0 ? number_format($item['debit'], 2, '.', ' ') : '-' }}</td>
                <td class="text-right font-bold">{{ number_format($running_balance, 2, '.', ' ') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="text-center">Hisob-kitob ma'lumotlari topilmadi.</td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" class="text-right total">Yakuniy Jami:</td>
                <td class="text-right total text-red">{{ number_format($ledger->sum('credit'), 2, '.', ' ') }}</td>
                <td class="text-right total text-green">{{ number_format($ledger->sum('debit'), 2, '.', ' ') }}</td>
                <td class="text-right total {{ $running_balance > 0 ? 'text-red' : 'text-green' }}">{{ number_format(abs($running_balance), 2, '.', ' ') }}</td>
            </tr>
        </tfoot>
    </table>

    <div style="margin-top: 50px;">
        <table width="100%">
            <tr>
                <td width="50%">Buxgalter: ____________________</td>
                <td width="50%" class="text-right">Ta'minotchi (Muxr): ____________________</td>
            </tr>
        </table>
    </div>
</body>
</html>
