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

    <h3 style="margin-bottom: 5px;">Kirim qilingan buyurtmalar (Olingan mahsulotlar)</h3>
    <table class="items-table">
        <thead>
            <tr>
                <th>Sana</th>
                <th>Buyurtma №</th>
                <th class="text-right">Summa</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $order)
            <tr>
                <td>{{ $order->order_date }}</td>
                <td>{{ $order->order_number }}</td>
                <td class="text-right">{{ number_format($order->total_amount, 2, '.', ' ') }} UZS</td>
            </tr>
            @empty
            <tr>
                <td colspan="3" class="text-center">Kirim qilingan buyurtmalar yo'q.</td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" class="text-right total">Jami Qarzlar:</td>
                <td class="text-right total">{{ number_format($orders->sum('total_amount'), 2, '.', ' ') }} UZS</td>
            </tr>
        </tfoot>
    </table>

    <h3 style="margin-bottom: 5px; margin-top: 30px;">Qilingan To'lovlar</h3>
    <table class="items-table">
        <thead>
            <tr>
                <th>Sana</th>
                <th>To'lov Turi</th>
                <th>Izoh</th>
                <th class="text-right">Summa</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payments as $payment)
            <tr>
                <td>{{ $payment->transaction_date }}</td>
                <td>{{ ucfirst($payment->payment_method) }}</td>
                <td>{{ $payment->notes }}</td>
                <td class="text-right">{{ number_format($payment->amount, 2, '.', ' ') }} UZS</td>
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
                <td class="text-right total">{{ number_format($payments->sum('amount'), 2, '.', ' ') }} UZS</td>
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
