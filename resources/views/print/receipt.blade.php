<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Tranzaksiya Kvitansiyasi - {{ $transaction->receipt_hash }}</title>
    <style>
        body { font-family: sans-serif; font-size: 14px; color: #000; background: #fff; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 20px; margin-bottom: 20px; }
        .title { font-size: 24px; font-weight: bold; text-transform: uppercase; }
        .subtitle { font-size: 12px; color: #666; }
        .content-wrapper { display: flex; justify-content: space-between; }
        .details-col { width: 70%; }
        .qr-col { width: 30%; text-align: center; padding-top: 10px; }
        .details-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .details-table th, .details-table td { padding: 10px; text-align: left; border-bottom: 1px dashed #ccc; }
        .details-table th { width: 45%; color: #555; font-size: 13px; }
        .total-row { font-size: 18px; font-weight: bold; background-color: #f9f9f9; }
        .qr-box { display: inline-block; padding: 15px; border: 1px solid #eee; background: #fafafa; border-radius: 8px; }
        .qr-text { font-size: 10px; color: #555; margin-top: 10px; }
        .footer { text-align: center; font-size: 10px; color: #888; margin-top: 40px; border-top: 1px solid #eee; padding-top: 10px; }

        @media print {
            body { padding: 0; }
            .container { max-width: 100%; border: none; }
            .qr-box { border: 1px solid #000; }
            .total-row { background-color: transparent !important; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="container">
        <div class="header">
            <div class="title">SHER-DOR ERP</div>
            <div class="subtitle">Rasmiy To'lov Kvitansiyasi</div>
        </div>

        <div class="content-wrapper">
            <div class="details-col">
                <table class="details-table">
                    <tr>
                        <th>Kvitansiya Raqami:</th>
                        <td>{{ $transaction->receipt_hash ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <th>Sana:</th>
                        <td>{{ \Carbon\Carbon::parse($transaction->updated_at)->format('d.m.Y H:i') }}</td>
                    </tr>
                    <tr>
                        <th>Mijoz:</th>
                        <td><b>{{ $clientName }}</b></td>
                    </tr>
                    <tr>
                        <th>Kvartira / Obyekt:</th>
                        <td>{{ $apartmentInfo }}</td>
                    </tr>
                    <tr>
                        <th>Mijoz Telefoni:</th>
                        <td>{{ $clientPhone }}</td>
                    </tr>
                    <tr>
                        <th>Turi:</th>
                        <td>{{ strtoupper($transaction->type === 'income' ? 'Kirim' : 'Chiqim') }}</td>
                    </tr>
                    <tr>
                        <th>To'lov Usuli:</th>
                        <td>{{ strtoupper($transaction->payment_method) }}</td>
                    </tr>
                    <tr>
                        <th>Qabul Qildi (Kassir):</th>
                        <td>{{ $transaction->creator->name ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <th>Tasdiqladi (Admin):</th>
                        <td>{{ $transaction->approver->name ?? 'Kutmoqda' }}</td>
                    </tr>
                    <tr>
                        <th>Izoh:</th>
                        <td>{{ $transaction->notes ?? 'Izoh kiritilmagan' }}</td>
                    </tr>
                    <tr class="total-row">
                        <th>Umumiy Summa:</th>
                        <td>{{ number_format($transaction->amount, 0, '.', ' ') }} UZS</td>
                    </tr>
                </table>
            </div>
            <div class="qr-col">
                <div class="qr-box">
                    <img src="{{ $qrApiUrl }}" width="150" height="150" alt="QR Code">
                    <div class="qr-text">Holatni tekshirish<br>uchun skaner qiling</div>
                </div>
            </div>
        </div>

        <div class="footer">
            Ushbu hujjat elektron shaklda yaratilgan. Tizim raqami: {{ $transaction->id }}<br>
            Sher-Dor Moliya Boshqarmasi
        </div>
    </div>
</body>
</html>
