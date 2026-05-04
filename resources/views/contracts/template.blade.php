<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', 'Arial', sans-serif; font-size: 13px; line-height: 1.6; color: #000; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; margin-bottom: 15px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: center; }
        .no-border-table td { border: none; text-align: left; vertical-align: top; }
        .mt-5 { margin-top: 50px; }
    </style>
</head>
<body>
    <h3 class="text-center font-bold">HISSADORLIK ShARTLARI ASOSIDA UY-JOY QURISh ShARTNOMASI № {{ $contract->id }}</h3>
    <p class="text-center">«{{ \Carbon\Carbon::parse($contract->created_at)->format('d') }}» {{ \Carbon\Carbon::parse($contract->created_at)->format('F Y') }}-yil &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Urgut shaxri</p>

    <p>«Sher-Dor» hususiy korxonasi, Ustav asosida harakat qiluvchi direktori, J. Raxmonov bir tomondan «SOTUVChI» deb yuritiladi va ikkinchi tomondan, Oʻzbekiston Respublikasi Fuqarolik kodeksi asosida harakat qiluvchi, Oʻzbekiston Respublikasi fuqarosi, <strong>{{ $client->address ?? 'Manzil' }}</strong> manzilida roʻyxatda turuvchi, <strong>{{ $client->first_name }} {{ $client->last_name }}</strong> (JShShIR: {{ $client->pinfl }}, {{ $client->passport_issue_date ? \Carbon\Carbon::parse($client->passport_issue_date)->format('d/m/Y') : '' }}-yilda {{ $client->passport_issued_by ?? 'I.I.B' }} tomonidan berilgan passport ID: {{ $client->passport_number }}), bundan keyin «XARIDOR» deb yuritiladi, mazkur shartnomani qoʻyidagilar tugʻrisida tuzdilar:</p>

    <h4 class="font-bold">2. ShARTNOMA MAVZUSI</h4>
    <p>2.3 Loyiha smeta hujjatlari va tomonlarning kelishuviga koʻra kvartira <strong>{{ $unit->area_sqm }}</strong> kv.metrdan iborat boʻlib, Urgut shahar Doʻstlik mahallasi Navoiy koʻchasi №83-uy manzilida joylashgan yangidan qurilayotgan koʻp qavatli kvartiralarning <strong>{{ $unit->floor->floor_number ?? '1' }}</strong>-qavati, <strong>{{ $unit->unit_number }}</strong>-kvartirasi.</p>
    <p>2.7 «OB'YeKT» ni «XARIDOR» ga qurib bitkazib berishning taxminiy muddati shartnoma imzolangan kundan boshlab ikki yil ichida.</p>

    <h4 class="font-bold">3. MOLIYaLAShTIRISh MIQDORI VA TARTIBI</h4>
    <table>
        <thead>
            <tr>
                <th>Qavat</th>
                <th>Kvartiraning umumiy maydoni (kv.m.)</th>
                <th>1 kv.m uchun narxi QQS bilan</th>
                <th>Kvartiraning umumiy narxi QQS bilan</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $unit->floor->floor_number ?? '1' }}</td>
                <td>{{ $unit->area_sqm }}</td>
                <td>{{ number_format($unit->price, 0, ',', ' ') }} so'm</td>
                <td><strong>{{ number_format($contract->total_amount, 0, ',', ' ') }} so'm</strong></td>
            </tr>
        </tbody>
    </table>

    <p>3.2 Shartnoma soʻmmasining boshlang'ich to'lovi: <strong>{{ number_format($contract->down_payment, 0, ',', ' ') }}</strong> soʻm miqdoridagi mablagʻ «XARIDOR» tomonidan shartnoma imzolangan kundan boshlab 6 bank ish kuni ichida, qolgan soʻmma <strong>{{ $contract->term_months }}</strong> oy ichida toʻlov grafigi asosida toʻlanadi.</p>

    <h4 class="font-bold mt-5">11. TOMONLARNING XUQUQIY MANZILLARI VA BANK RYeKVIZITLARI:</h4>
    <table class="no-border-table">
        <tr>
            <td style="width: 50%;">
                <strong>«XARIDOR»</strong><br>
                F.I.Sh: {{ $client->last_name }} {{ $client->first_name }}<br>
                Manzil: {{ $client->address ?? 'Manzil' }}<br>
                Pasport: {{ $client->passport_number }}<br>
                JShShIR: {{ $client->pinfl }}<br>
                Telefon: {{ $client->phone }}<br><br>
                Imzo: ___________________
            </td>
            <td style="width: 50%;">
                <strong>«KORXONA»</strong><br>
                «Sher-Dor» Xususiy korxonasi<br>
                Manzil: Samarqand viloyati Urgut tumani Yuqori Tegana mahalla<br>
                H/R: 2020 8000 6005 5796 1001<br>
                Bank: ATB “Agrobank” Urgut tuman filiali<br>
                MFO: 00300, INN: 202 162 496<br>
                Telefon: (97) 708-44-44<br><br>
                Direktor: J. Raxmonov<br>
                M.O'.<br>
                Imzo: ___________________
            </td>
        </tr>
    </table>
</body>
</html>
