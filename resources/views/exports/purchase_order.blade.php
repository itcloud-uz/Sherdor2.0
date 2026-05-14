<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Xarid Buyurtmasi - {{ $order->order_number }}</title>
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
    </style>
</head>
<body>
    <div class="header">
        <h1>XARID BUYURTMASI (PURCHASE ORDER)</h1>
        <p>Raqami: <strong>{{ $order->order_number }}</strong></p>
    </div>

    <table class="info-table">
        <tr>
            <td width="50%">
                <strong>Ta'minotchi:</strong><br>
                {{ $order->supplier->company_name ?: $order->supplier->name }}<br>
                Tel: {{ $order->supplier->phone }}<br>
                STIR: {{ $order->supplier->inn }}
            </td>
            <td width="50%" class="text-right">
                <strong>Sana:</strong> {{ $order->order_date }}<br>
                <strong>Kutilayotgan Sana:</strong> {{ $order->expected_delivery_date ?: '-' }}<br>
                <strong>Status:</strong> {{ $order->status == 'pending' ? 'Kutilmoqda' : 'Qabul Qilindi' }}
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>№</th>
                <th>Mahsulot Nomi</th>
                <th class="text-right">Miqdor</th>
                <th class="text-right">Narxi</th>
                <th class="text-right">Jami</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item->item_name }}</td>
                <td class="text-right">{{ $item->quantity }} {{ $item->unit }}</td>
                <td class="text-right">{{ number_format($item->unit_price, 2, '.', ' ') }} UZS</td>
                <td class="text-right">{{ number_format($item->total_price, 2, '.', ' ') }} UZS</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="text-right total">Umumiy Qiymat:</td>
                <td class="text-right total">{{ number_format($order->total_amount, 2, '.', ' ') }} UZS</td>
            </tr>
        </tfoot>
    </table>

    @if($order->notes)
    <div>
        <strong>Izoh:</strong>
        <p>{{ $order->notes }}</p>
    </div>
    @endif

    <div style="margin-top: 50px;">
        <table width="100%">
            <tr>
                <td width="50%">Buyurtmachi: ____________________</td>
                <td width="50%" class="text-right">Ta'minotchi: ____________________</td>
            </tr>
        </table>
    </div>
</body>
</html>
