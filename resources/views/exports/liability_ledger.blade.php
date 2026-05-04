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
        <div class="header-title font-bold">KREDIT / QARZ SVERKA DALOLATNOMASI</div>
        <div class="header-sub">To'lovlar grafigi va hisob-kitob varaqasi</div>
    </div>

    <table class="info-table">
        <tr>
            <td width="20%"><strong>Kreditor Nomi:</strong></td>
            <td width="30%">{{ $liability->creditor_name }}</td>
            <td width="20%"><strong>Qarz Turi:</strong></td>
            <td width="30%" style="text-transform: uppercase;">{{ str_replace('_', ' ', $liability->type) }}</td>
        </tr>
        <tr>
            <td><strong>Umumiy Summa:</strong></td>
            <td>{{ number_format($liability->total_amount, 2) }}</td>
            <td><strong>Yillik Foiz:</strong></td>
            <td>{{ $liability->interest_rate ? $liability->interest_rate . '%' : 'Yo\'q' }}</td>
        </tr>
        <tr>
            <td><strong>Oylik To'lov:</strong></td>
            <td>{{ $liability->monthly_payment ? number_format($liability->monthly_payment, 2) : 'Belgilanmagan' }}</td>
            <td><strong>Muddat (Oy):</strong></td>
            <td>{{ $liability->term_months }} oy</td>
        </tr>
        <tr>
            <td><strong>Boshlanish Sanasi:</strong></td>
            <td>{{ \Carbon\Carbon::parse($liability->start_date)->format('d.m.Y') }}</td>
            <td><strong>Tugash Sanasi:</strong></td>
            <td>{{ $liability->end_date ? \Carbon\Carbon::parse($liability->end_date)->format('d.m.Y') : 'Belgilanmagan' }}</td>
        </tr>
        <tr>
            <td><strong>Joriy Holat:</strong></td>
            <td class="{{ $liability->status == 'active' ? 'text-danger' : 'text-success' }} font-bold uppercase">
                {{ str_replace('_', ' ', $liability->status) }}
            </td>
            <td><strong>Hujjat ID:</strong></td>
            <td>#LIAB-{{ str_pad($liability->id, 5, '0', STR_PAD_LEFT) }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>No.</th>
                <th>To'lov Sanasi</th>
                <th>To'langan Summa</th>
                <th>Holat</th>
                <th>Izoh (Notes)</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $totalPaid = 0;
            @endphp
            @forelse($liability->payments as $index => $payment)
                @php
                    if($payment->status === 'paid') {
                        $totalPaid += $payment->amount;
                    }
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ \Carbon\Carbon::parse($payment->payment_date)->format('d.m.Y') }}</td>
                    <td>{{ number_format($payment->amount, 2) }}</td>
                    <td>
                        @if($payment->status == 'paid')
                            <strong class="text-success">TO'LANGAN</strong>
                        @else
                            <strong class="text-danger">KUTILMOQDA</strong>
                        @endif
                    </td>
                    <td>{{ $payment->notes ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">Hozircha hech qanday to'lov amalga oshirilmagan.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" class="text-right font-bold">JAMI TO'LANGAN:</td>
                <td class="font-bold text-success">{{ number_format($totalPaid, 2) }}</td>
                <td colspan="2"></td>
            </tr>
            <tr>
                <td colspan="2" class="text-right font-bold">QOLDIQ QARZ:</td>
                <td class="font-bold text-danger">{{ number_format($liability->total_amount - $totalPaid, 2) }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>

    <table class="info-table" style="margin-top: 50px;">
        <tr>
            <td width="50%" class="text-center">
                <br><br>
                _______________________<br>
                <strong>Kompaniya Moliya Bo'limi</strong>
            </td>
            <td width="50%" class="text-center">
                <br><br>
                _______________________<br>
                <strong>Kreditor Imzosi (tasdiq uchun)</strong>
            </td>
        </tr>
    </table>

</body>
</html>
