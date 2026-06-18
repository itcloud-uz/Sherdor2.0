<table>
    <thead>
        <tr>
            <th style="font-weight: bold; background-color: #f3f4f6;">Nomer</th>
            <th style="font-weight: bold; background-color: #f3f4f6;">Ta'minotchi (Vakil)</th>
            <th style="font-weight: bold; background-color: #f3f4f6;">Kompaniya Nomi</th>
            <th style="font-weight: bold; background-color: #f3f4f6;">Telefon</th>
            <th style="font-weight: bold; background-color: #f3f4f6;">Kategoriya</th>
            <th style="font-weight: bold; background-color: #f3f4f6;">Xaridlar soni</th>
            <th style="font-weight: bold; background-color: #f3f4f6;">Umumiy summa (so'm)</th>
            <th style="font-weight: bold; background-color: #f3f4f6;">Tovarlar soni</th>
            <th style="font-weight: bold; background-color: #f3f4f6;">Qarzdorlik (so'm)</th>
            <th style="font-weight: bold; background-color: #f3f4f6;">Kredit (so'm)</th>
            <th style="font-weight: bold; background-color: #f3f4f6;">Oxirgi xarid sanasi</th>
        </tr>
    </thead>
    <tbody>
        @foreach($suppliers as $index => $s)
            @php
                $deliveredOrders = $s->orders ? $s->orders->where('status', 'delivered') : collect();
                $ordersCount = $deliveredOrders->count();
                $totalSum = $deliveredOrders->sum('total_amount');
                $totalItems = $deliveredOrders->flatMap(function($order) {
                    return $order->items ?: [];
                })->sum('quantity');
                $lastOrder = $deliveredOrders->sortByDesc('order_date')->first();
                $lastOrderDate = $lastOrder ? $lastOrder->order_date : '—';
                
                $debt = $s->balance > 0 ? $s->balance : 0;
                $credit = $s->balance < 0 ? abs($s->balance) : 0;
            @endphp
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $s->name }}</td>
                <td>{{ $s->company_name ?: '—' }}</td>
                <td>{{ $s->phone ?: '—' }}</td>
                <td>{{ $s->category ?: 'taminotchi' }}</td>
                <td>{{ $ordersCount }}</td>
                <td>{{ $totalSum }}</td>
                <td>{{ $totalItems }}</td>
                <td>{{ $debt }}</td>
                <td>{{ $credit }}</td>
                <td>{{ $lastOrderDate }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
