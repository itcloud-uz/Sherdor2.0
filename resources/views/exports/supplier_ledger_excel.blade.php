<table>
    <thead>
        <tr>
            <th colspan="8" style="font-weight: bold; font-size: 16px; text-align: center;">TA'MINOTCHI HISOBOTI (LEDGER)</th>
        </tr>
        <tr>
            <th colspan="4">Kompaniya: {{ $supplier->company_name ?: $supplier->name }}</th>
            <th colspan="4" style="text-align: right;">Sana: {{ date('d.m.Y H:i') }}</th>
        </tr>
        <tr>
            <th colspan="4">Vakil: {{ $supplier->name }} | Tel: {{ $supplier->phone }}</th>
            <th colspan="4" style="text-align: right;">Joriy Balans: {{ number_format(abs($supplier->balance), 2, '.', '') }} UZS ({{ $supplier->balance > 0 ? 'Qarzimiz bor' : ($supplier->balance < 0 ? 'Haqimiz bor' : 'Nol') }})</th>
        </tr>
        <tr><th colspan="8"></th></tr>
    </thead>
    <tbody>
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
                        'size' => $i->size,
                        'unit' => $i->unit,
                        'qty' => $i->quantity,
                        'price' => $i->unit_price,
                        'total' => $i->total_price,
                    ]);
                }
            }
        @endphp

        @forelse($groupedItems as $category => $items)
            <tr>
                <td colspan="8" style="font-weight: bold; text-align: center; font-size: 14px; background-color: #f3f4f6;">
                    {{ $category }}
                </td>
            </tr>
            <tr>
                <td style="font-weight: bold; border: 1px solid #000;">№</td>
                <td style="font-weight: bold; border: 1px solid #000;">Nomi</td>
                <td style="font-weight: bold; border: 1px solid #000;">Sana</td>
                <td style="font-weight: bold; border: 1px solid #000;">O'lcham</td>
                <td style="font-weight: bold; border: 1px solid #000;">O'lchov</td>
                <td style="font-weight: bold; border: 1px solid #000;">Miqdor</td>
                <td style="font-weight: bold; border: 1px solid #000;">Narxi</td>
                <td style="font-weight: bold; border: 1px solid #000;">Jami narxi</td>
            </tr>
            @php $sum = 0; @endphp
            @foreach($items as $index => $item)
                @php $sum += $item['total']; @endphp
                <tr>
                    <td style="border: 1px solid #000;">{{ $index + 1 }}</td>
                    <td style="border: 1px solid #000;">{{ $category }}</td>
                    <td style="border: 1px solid #000;">{{ date('d.m.Y', strtotime($item['date'])) }}</td>
                    <td style="border: 1px solid #000;">{{ $item['size'] ?: '-' }}</td>
                    <td style="border: 1px solid #000;">{{ $item['unit'] }}</td>
                    <td style="border: 1px solid #000;">{{ $item['qty'] }}</td>
                    <td style="border: 1px solid #000;">{{ number_format($item['price'], 0, '.', '') }}</td>
                    <td style="border: 1px solid #000;">{{ number_format($item['total'], 0, '.', '') }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="7" style="font-weight: bold; text-align: right; border: 1px solid #000;">Jami ({{ $category }}):</td>
                <td style="font-weight: bold; color: red; border: 1px solid #000;">{{ number_format($sum, 0, '.', '') }}</td>
            </tr>
            <tr><td colspan="8"></td></tr>
        @empty
            <tr>
                <td colspan="8" style="text-align: center;">Hozircha mahsulotlar qabul qilinmagan.</td>
            </tr>
            <tr><td colspan="8"></td></tr>
        @endforelse

        <tr>
            <td colspan="8" style="font-weight: bold; text-align: center; font-size: 14px; background-color: #f3f4f6;">
                To'lovlar Tarixi (Chiqim)
            </td>
        </tr>
        <tr>
            <td colspan="2" style="font-weight: bold; border: 1px solid #000;">Sana</td>
            <td colspan="2" style="font-weight: bold; border: 1px solid #000;">To'lov Turi</td>
            <td colspan="2" style="font-weight: bold; border: 1px solid #000;">Izoh</td>
            <td colspan="2" style="font-weight: bold; border: 1px solid #000; text-align: right;">Summa</td>
        </tr>
        @php $total_payment = 0; @endphp
        @forelse($payments as $p)
            @php $total_payment += $p->amount; @endphp
            <tr>
                <td colspan="2" style="border: 1px solid #000;">{{ date('d.m.Y', strtotime($p->transaction_date)) }}</td>
                <td colspan="2" style="border: 1px solid #000;">{{ ucfirst($p->payment_method) }}</td>
                <td colspan="2" style="border: 1px solid #000;">{{ $p->notes ?: '-' }}</td>
                <td colspan="2" style="border: 1px solid #000; text-align: right; color: green;">{{ number_format($p->amount, 0, '.', '') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="8" style="text-align: center; border: 1px solid #000;">To'lovlar topilmadi.</td>
            </tr>
        @endforelse
        <tr>
            <td colspan="6" style="font-weight: bold; text-align: right; border: 1px solid #000;">Jami To'lovlar:</td>
            <td colspan="2" style="font-weight: bold; color: green; border: 1px solid #000; text-align: right;">{{ number_format($total_payment, 0, '.', '') }}</td>
        </tr>
    </tbody>
</table>
