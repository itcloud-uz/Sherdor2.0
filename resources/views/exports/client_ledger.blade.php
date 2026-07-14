<!DOCTYPE html>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta charset="utf-8">
    <title>To'lov Grafigi - 2-Ilova</title>
    <!--[if gte mso 9]>
    <xml>
        <o:OfficeDocumentSettings>
            <o:AllowPNG/>
        </o:OfficeDocumentSettings>
        @php $x = 'x:'; @endphp
        {!! "<{$x}ExcelWorkbook>" !!}
            {!! "<{$x}ExcelWorksheets>" !!}
                {!! "<{$x}ExcelWorksheet>" !!}
                    {!! "<{$x}Name>To'lov Grafigi</{$x}Name>" !!}
                    {!! "<{$x}WorksheetOptions>" !!}
                        {!! "<{$x}Print>" !!}
                            {!! "<{$x}ValidPrinterInfo/>" !!}
                            {!! "<{$x}PaperSizeIndex>9</{$x}PaperSizeIndex>" !!} <!-- A4 -->
                            {!! "<{$x}HorizontalResolution>600</{$x}HorizontalResolution>" !!}
                            {!! "<{$x}VerticalResolution>600</{$x}VerticalResolution>" !!}
                        {!! "</{$x}Print>" !!}
                        {!! "<{$x}Selected/>" !!}
                        {!! "<{$x}DoNotDisplayGridlines/>" !!}
                    {!! "</{$x}WorksheetOptions>" !!}
                {!! "</{$x}ExcelWorksheet>" !!}
            {!! "</{$x}ExcelWorksheets>" !!}
        {!! "</{$x}ExcelWorkbook>" !!}
    </xml>
    <![endif]-->
    <style>
        @page { size: A4 portrait; margin: 8mm 10mm; }
        body { font-family: 'DejaVu Sans', 'Arial', sans-serif; font-size: 9px; line-height: 1.35; color: #000; margin: 0; padding: 0; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        
        .main-table { width: 100%; border-collapse: collapse; margin-top: 15px; margin-bottom: 15px; }
        .main-table th { border: 1.5px solid #000; padding: 6px 4px; font-size: 9px; text-align: center; font-weight: bold; }
        .main-table td { border: 1px solid #000; padding: 5px 4px; font-size: 8.5px; }
    </style>
</head>
<body>

    @php
        if (!function_exists('getShortName')) {
            function getShortName($fullName) {
                $parts = explode(' ', trim($fullName));
                if (count($parts) >= 2) {
                    $lastName = $parts[0];
                    $firstName = $parts[1];
                    $firstChar = mb_substr($firstName, 0, 1);
                    return $firstChar . '. ' . $lastName;
                }
                return $fullName;
            }
        }

        $settings = \App\Models\SystemSetting::first();
        $korxonaNomi = $settings->company_name ?? '“SHER-DOR” xususiy korxonasi';
        $korxonaManzili = $settings->company_address ?? 'Samarqand viloyati, Urgut tumani, Do`stlik MFY, Navoiy ko`chasi 83-uy';
        $korxonaHr = $settings->company_bank_account ?? '2020 8000 6005 5796 1001';
        $korxonaBank = $settings->company_bank_name ?? 'ATB “Agrobank” Urgut tuman filiali';
        $korxonaMfo = $settings->company_mfo ?? '00300';
        $korxonaInn = $settings->company_inn ?? '202 162 496';
        $korxonaDirector = $settings->company_director ?? 'J. Raxmonov';
        $korxonaTelefon = $settings->company_phone ?? '91 708-44-44, 93 573-29-29';

        $isZamon = (strpos(strtolower($korxonaNomi), 'zamon') !== false);
        $buyerLabel = $isZamon ? '«БУЮРТМАЧИ»' : '«HARIDOR»';
        $companyLabel = $isZamon ? '«БАЖАРУВЧИ»' : '«KORXONA»';

        // Filter and sort schedule
        $installments = $contract->paymentSchedules->filter(function($s) {
            return $s->payment_type !== 'down_payment';
        })->sortBy('due_date');

        $contractDate = $contract->created_at ? \Carbon\Carbon::parse($contract->created_at)->format('d/m/Y') : date('d/m/Y');
        $contractNum = $contract->contract_number ?: ('#' . str_pad($contract->id, 5, '0', STR_PAD_LEFT));
        
        $buildingType = 'uy-joy';
        if ($contract->contract_type === 'commercial' || ($contract->unit && $contract->unit->type === 'commercial')) {
            $buildingType = 'savdo do\'koni';
        }
    @endphp

    @if(!$isZamon)
        <!-- Sher-Dor Header -->
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 5px;">
            <tr>
                <td style="width: 45%; font-size: 8.5px; text-align: center; border: none; padding: 0; line-height: 1.3; font-weight: bold; font-family: 'DejaVu Sans', sans-serif;">
                    O`zbekiston Respublikasi<br>
                    Samarqand viloyati<br>
                    Urgut tumani<br>
                    “Sher-dor”<br>
                    xususiy korxonasi
                </td>
                <td style="width: 10%; border: none; padding: 0;"></td>
                <td style="width: 45%; font-size: 8.5px; text-align: center; border: none; padding: 0; line-height: 1.3; font-weight: bold; font-family: 'DejaVu Sans', sans-serif;">
                    Частное предприятие<br>
                    “ШЕР-ДОР”<br>
                    Ургутского района<br>
                    Самаркандской области<br>
                    Республики Узбекистан
                </td>
            </tr>
            <tr>
                <td colspan="3" style="text-align: center; font-size: 8px; border: none; border-bottom: 1.5px solid #000; padding: 6px 0; font-family: 'DejaVu Sans', sans-serif;">
                    Manzil: Samarqand viloyati, Urgut shahar, Do`stlik MFY, Navoiy ko`chasi 83-uy; e-mail: sherdor.xk@mail.ru
                </td>
            </tr>
        </table>
    @else
        <!-- Zamon Sifat Header -->
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 5px;">
            <tr>
                <td style="font-size: 11px; text-align: center; border: none; padding: 0; line-height: 1.4; font-weight: bold;">
                    {{ $korxonaNomi }}
                </td>
            </tr>
            <tr>
                <td style="text-align: center; font-size: 8.5px; border: none; border-bottom: 1.5px solid #000; padding: 6px 0;">
                    Manzil: {{ $korxonaManzili }} | Tel: {{ $korxonaTelefon }}
                </td>
            </tr>
        </table>
    @endif

    <div class="text-center font-bold" style="font-size: 9.5px; margin-top: 10px; line-height: 1.4;">
        {{ $contractDate }}-yildagi №{{ $contractNum }}-sonli<br>
        hissadorlik shartlari asosida<br>
        {{ $buildingType }} qurish shartnomasi<br>
        2-ilova
    </div>
    <div class="text-center" style="font-size: 9px; margin-top: 4px;">
        Urgut shahri
    </div>
    <div class="text-center font-bold" style="font-size: 11px; margin-top: 8px; margin-bottom: 12px; letter-spacing: 0.5px;">
        TO`LOV GRAFIGI
    </div>

    <!-- Main Payment Ledger Table -->
    <table class="main-table">
        <colgroup>
            <col style="width: 8%;">
            <col style="width: 25%;">
            <col style="width: 23%;">
            <col style="width: 22%;">
            <col style="width: 22%;">
        </colgroup>
        <thead>
            <tr>
                <th rowspan="2" style="background-color: #f5f5f5; border: 1.5px solid #000; padding: 5px; font-size: 9px;">№</th>
                <th rowspan="2" style="background-color: #f5f5f5; border: 1.5px solid #000; padding: 5px; font-size: 9px;">To'lov sanasi</th>
                <th colspan="3" style="background-color: #f5f5f5; border: 1.5px solid #000; padding: 5px; font-size: 9px;">To'lov</th>
            </tr>
            <tr>
                <th style="background-color: #fafafa; border: 1px solid #000; padding: 4px; font-size: 8px;">
                    {{ $contract->contract_type === 'commercial' ? "Savdo do'konining umumiy so'mmasi" : "Xonadonning umumiy so'mmasi" }}
                </th>
                <th style="background-color: #fafafa; border: 1px solid #000; padding: 4px; font-size: 8px;">Boshlang'ich to'lov</th>
                <th style="background-color: #fafafa; border: 1px solid #000; padding: 4px; font-size: 8px;">Qoldiq so'mma</th>
            </tr>
            <tr style="font-weight: bold; text-align: center; background-color: #ffffff;">
                <td style="border: 1px solid #000; padding: 5px;"></td>
                <td style="border: 1px solid #000; padding: 5px;"></td>
                <td style="border: 1px solid #000; padding: 5px; text-align: right; font-size: 8.5px;">{{ number_format($contract->total_amount, 0, ',', ' ') }}</td>
                <td style="border: 1px solid #000; padding: 5px; text-align: right; font-size: 8.5px;">{{ number_format($contract->down_payment, 0, ',', ' ') }}</td>
                <td style="border: 1px solid #000; padding: 5px; text-align: right; font-size: 8.5px;">{{ number_format($contract->total_amount - $contract->down_payment, 0, ',', ' ') }}</td>
            </tr>
        </thead>
        <tbody>
            @php $index = 1; $sum = 0; @endphp
            @foreach($installments as $inst)
                @php $sum += $inst->expected_amount; @endphp
                <tr>
                    <td style="border: 1px solid #000; padding: 4px; text-align: center; font-size: 8.5px;">{{ $index++ }}</td>
                    <td style="border: 1px solid #000; padding: 4px; text-align: center; font-size: 8.5px;">{{ \Carbon\Carbon::parse($inst->due_date)->format('d/m/Y') }}-yil</td>
                    <td style="border: 1px solid #000; padding: 4px; background-color: #fafafa;"></td>
                    <td style="border: 1px solid #000; padding: 4px; background-color: #fafafa;"></td>
                    <td style="border: 1px solid #000; padding: 4px; text-align: right; font-weight: bold; font-size: 8.5px;">{{ number_format($inst->expected_amount, 0, ',', ' ') }}</td>
                </tr>
            @endforeach
            
            @if($installments->isEmpty())
                <tr>
                    <td colspan="5" style="border: 1px solid #000; padding: 12px; text-align: center; color: #777; font-size: 9px; font-style: italic;">
                        To'lov grafigi hali shakllantirilmagan
                    </td>
                </tr>
            @endif

            <tr style="font-weight: bold; background-color: #f5f5f5;">
                <td style="border: 1.5px solid #000; padding: 5px;"></td>
                <td style="border: 1.5px solid #000; padding: 5px; text-align: center; font-size: 9px;">Жами</td>
                <td style="border: 1px solid #000; padding: 5px; background-color: #fafafa;"></td>
                <td style="border: 1px solid #000; padding: 5px; background-color: #fafafa;"></td>
                <td style="border: 1.5px solid #000; padding: 5px; text-align: right; font-size: 9px;">{{ number_format($sum, 0, ',', ' ') }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Footer Signatures -->
    <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
        <tr>
            <td style="width: 48%; vertical-align: top; font-size: 8.5px; line-height: 1.45; border: none; padding: 0;">
                <div class="font-bold" style="text-decoration: underline; margin-bottom: 5px;">{{ $buyerLabel }}</div>
                <div><strong>F.I.SH:</strong> {{ $contract->client->full_name }}</div>
                <div><strong>Pasport berilgan sana:</strong> {{ $contract->client->passport_date ? \Carbon\Carbon::parse($contract->client->passport_date)->format('d/m/Y') : '___' }}-yil</div>
                <div><strong>Pasport berilgan joyi:</strong> {{ $contract->client->passport_issued_by ?? 'Urgut tuman IIB' }}</div>
                <div><strong>Pasport seriyasi:</strong> {{ $contract->client->passport_series ?? 'AD' }} № {{ $contract->client->passport_number ?? '_______' }}</div>
                <div><strong>Manzil:</strong> {{ $contract->client->address ?? 'Samarqand viloyati Urgut tumani' }}</div>
                <div><strong>ЖШШИР:</strong> {{ $contract->client->pinfl ?? '_____________' }}</div>
                <div><strong>Телефон:</strong> {{ $contract->client->phone ?? '_____________' }}</div>
                <div style="margin-top: 15px;"><strong>{{ $isZamon ? 'Буюртмачи' : 'Haridor' }}:</strong> {{ getShortName($contract->client->full_name) }} ___________________</div>
            </td>
            <td style="width: 4%; border: none; padding: 0;"></td>
            <td style="width: 48%; vertical-align: top; font-size: 8.5px; line-height: 1.45; border: none; padding: 0;">
                <div class="font-bold" style="text-decoration: underline; margin-bottom: 5px;">{{ $companyLabel }}</div>
                <div><strong>{{ $korxonaNomi }}</strong></div>
                <div><strong>Manzil:</strong> {{ $korxonaManzili }}</div>
                <div><strong>H/R:</strong> {{ $korxonaHr }}</div>
                <div><strong>Bank:</strong> {{ $korxonaBank }}</div>
                <div><strong>MFO:</strong> {{ $korxonaMfo }}</div>
                <div><strong>INN:</strong> {{ $korxonaInn }}</div>
                <div><strong>Telefon:</strong> {{ $korxonaTelefon }}</div>
                <div style="margin-top: 15px;"><strong>Direktor:</strong> {{ $korxonaDirector }} ___________________</div>
            </td>
        </tr>
    </table>

</body>
</html>
