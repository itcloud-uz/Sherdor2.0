<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll #{{ $payroll->id }} - Sher-Dor ERP</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: #fff;
            color: #000;
            margin: 0;
            padding: 40px;
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 2px solid #000;
            padding-bottom: 20px;
        }
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 28px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .header p {
            margin: 5px 0;
            font-size: 14px;
            color: #555;
        }
        .details-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            border: 1px solid #000;
            padding: 12px;
            text-align: left;
            font-size: 12px;
        }
        th {
            background-color: #f4f4f4;
            font-weight: bold;
            text-transform: uppercase;
        }
        .total-row {
            font-weight: bold;
            background-color: #f4f4f4;
        }
        .total-amount {
            font-size: 18px;
            text-align: right;
        }
        .signatures {
            margin-top: 60px;
            display: flex;
            justify-content: space-between;
        }
        .signature-box {
            width: 45%;
        }
        .signature-line {
            border-bottom: 1px solid #000;
            margin-top: 40px;
            margin-bottom: 5px;
        }
        .signature-text {
            font-size: 12px;
            color: #555;
        }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #0066FF; color: #fff; border: none; cursor: pointer; border-radius: 5px;">🖨️ Chop etish / PDF ga yuklash</button>
        <a href="javascript:history.back()" style="margin-left: 10px; color: #0066FF;">Orqaga qaytish</a>
    </div>

    <div class="header">
        <h1>Oylik va Hisob-kitob Vekseli</h1>
        <p><strong>Hujjat Raqami:</strong> PAY-{{ str_pad($payroll->id, 5, '0', STR_PAD_LEFT) }}</p>
        <p><strong>Sana:</strong> {{ $payroll->created_at->format('d.m.Y H:i') }}</p>
        <p><strong>Kompaniya:</strong> Sher-Dor Construction</p>
    </div>

    <div class="details-section">
        <div>
            <strong>Hisobot turi:</strong> Umumiy Ish haqi va Texnika xarajatlari<br>
            <strong>Status:</strong> Tasdiqlashga yuborilgan (Kassadan)
        </div>
        <div style="text-align: right;">
            <strong>Umumiy Jurnallar Soni:</strong> {{ $payroll->workLogs->count() }} ta<br>
            <strong>Valyuta:</strong> O'zbek so'mi (UZS)
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Sana</th>
                <th>Kim / Nima</th>
                <th>Obyekt</th>
                <th>Ishlangan hajm/soat</th>
                <th>Summa</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payroll->workLogs as $index => $log)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ \Carbon\Carbon::parse($log->date)->format('d.m.Y') }}</td>
                <td>
                    @if($log->worker)
                        {{ $log->worker->name }} (Ishchi)
                    @elseif($log->machinery)
                        {{ $log->machinery->name }} (Texnika)
                    @else
                        Noma'lum
                    @endif
                </td>
                <td>{{ $log->building ? $log->building->name : '-' }}</td>
                <td>
                    {{ $log->hours_worked ? $log->hours_worked . ' soat' : '' }}
                    {{ $log->volume_done ? $log->volume_done . ' hajm' : '' }}
                </td>
                <td style="text-align: right;">{{ number_format($log->calculated_cost, 0, '.', ' ') }} so'm</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="5" style="text-align: right;">JAMI TO'LANISHI KEREK BO'LGAN SUMMA:</td>
                <td class="total-amount">{{ number_format($payroll->total_amount, 0, '.', ' ') }} so'm</td>
            </tr>
        </tbody>
    </table>

    <div class="signatures">
        <div class="signature-box">
            <strong>Boshqaruvchi (Prorab):</strong>
            <div class="signature-line"></div>
            <div class="signature-text">F.I.SH va Imzo</div>
        </div>
        <div class="signature-box">
            <strong>Moliya Bo'limi (Kassir):</strong>
            <div class="signature-line"></div>
            <div class="signature-text">Tasdiqlangan sana va Imzo</div>
        </div>
    </div>
</body>
</html>
