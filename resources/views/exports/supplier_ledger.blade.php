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
        $groupedItems = collect();
        foreach($orders as $o) {
            foreach($o->items as $i) {
                $name = strtoupper($i->item_name);
                if(!isset($groupedItems[$name])) {
                    $groupedItems[$name] = collect();
                }
                $groupedItems[$name]->push([
                    'date' => $o->order_date,
                    'unit' => $i->unit,
                    'qty' => $i->quantity,
                    'price' => $i->unit_price,
                    'total' => $i->total_price,
                ]);
            }
        }
    @endphp

    <table width="100%" style="margin-bottom: 20px;">
        <tr>
            @forelse($groupedItems as $category => $items)
            <td width="50%" valign="top" style="padding: 5px;">
                <h3 style="text-align: center; font-size: 14px; margin-bottom: 5px; border-bottom: 2px solid #000; padding-bottom: 3px;">{{ $category }}</h3>
                <table class="items-table" style="font-size: 10px;">
                    <thead>
                        <tr>
                            <th>№</th>
                            <th>Номи</th>
                            <th>Сана</th>
                            <th>Ўлчов</th>
                            <th>Дона/Miqdor</th>
                            <th>Нархи</th>
                            <th>Жами нархи</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $sum = 0; @endphp
                        @foreach($items as $index => $item)
                        @php $sum += $item['total']; @endphp
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $category }}</td>
                            <td>{{ date('d.m.Y', strtotime($item['date'])) }}</td>
                            <td>{{ $item['unit'] }}</td>
                            <td>{{ $item['qty'] }}</td>
                            <td>{{ number_format($item['price'], 0, '.', ' ') }}</td>
                            <td>{{ number_format($item['total'], 0, '.', ' ') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6" class="text-right total">Jami ({{ $category }}):</td>
                            <td class="total text-red">{{ number_format($sum, 0, '.', ' ') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </td>
            @if($loop->iteration % 2 == 0)
                </tr><tr>
            @endif
            @empty
            <td>
                <p style="text-align: center; color: #777;">Hozircha mahsulotlar qabul qilinmagan.</p>
            </td>
            @endforelse
        </tr>
    </table>

    <h3 style="margin-bottom: 5px; font-size: 14px; border-bottom: 2px solid #000; padding-bottom: 3px;">To'lovlar Tarixi (Chiqim)</h3>
    <table class="items-table" style="font-size: 10px;">
        <thead>
            <tr>
                <th>Sana</th>
                <th>To'lov Turi</th>
                <th>Izoh</th>
                <th class="text-right">Summa</th>
            </tr>
        </thead>
        <tbody>
            @php $total_payment = 0; @endphp
            @forelse($payments as $p)
            @php $total_payment += $p->amount; @endphp
            <tr>
                <td>{{ date('d.m.Y', strtotime($p->transaction_date)) }}</td>
                <td>{{ ucfirst($p->payment_method) }}</td>
                <td>{{ $p->notes ?: '-' }}</td>
                <td class="text-right text-green font-bold">{{ number_format($p->amount, 0, '.', ' ') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="text-center">To'lovlar topilmadi.</td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="text-right total">Jami To'lovlar:</td>
                <td class="text-right total text-green">{{ number_format($total_payment, 0, '.', ' ') }}</td>
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
