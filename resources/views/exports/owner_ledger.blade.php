<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<table>
    <thead>
        <tr>
            <th colspan="9" style="font-weight: bold; font-size: 16px; text-align: center;">{{ strtoupper($owner->name) }} (UMUMIY XISOBOT)</th>
        </tr>
        <tr>
            <th rowspan="2" style="font-weight: bold; border: 1px solid #000000; text-align: center; vertical-align: middle;">№</th>
            <th rowspan="2" style="font-weight: bold; border: 1px solid #000000; text-align: center; vertical-align: middle;">Sana</th>
            <th colspan="4" style="font-weight: bold; border: 1px solid #000000; text-align: center;">BAJARILGAN ISHLAR</th>
            <th colspan="2" style="font-weight: bold; border: 1px solid #000000; text-align: center;">TO'LANDI</th>
            <th rowspan="2" style="font-weight: bold; border: 1px solid #000000; text-align: center; vertical-align: middle;">Qoldiq</th>
        </tr>
        <tr>
            <th style="font-weight: bold; border: 1px solid #000000; text-align: center;">Nomi</th>
            <th style="font-weight: bold; border: 1px solid #000000; text-align: center;">Soni (soat,reysi)</th>
            <th style="font-weight: bold; border: 1px solid #000000; text-align: center;">Narxi (soat,reysi)</th>
            <th style="font-weight: bold; border: 1px solid #000000; text-align: center;">Jami narxi</th>
            <th style="font-weight: bold; border: 1px solid #000000; text-align: center;">To'landi</th>
            <th style="font-weight: bold; border: 1px solid #000000; text-align: center;">Salarka olib berdik</th>
        </tr>
    </thead>
    <tbody>
        @php
            $totalNarx = 0;
            $totalJami = 0;
            $totalTlandi = 0;
            $totalSalarka = 0;
            $qoldiqRunning = 0;
        @endphp
        @foreach($items as $index => $row)
            @php
                if ($row['type'] === 'log') {
                    $item = $row['item'];
                    $soni = $item->hours_worked ?: ($item->volume_done ?: 1);
                    $narxi = $item->rate ?: 0;
                    $salarka = $item->deductions ?: 0;
                    $jami = $item->calculated_cost + $salarka;
                    
                    $totalNarx += $narxi;
                    $totalJami += $jami;
                    $totalSalarka += $salarka;
                    $qoldiqRow = -$item->calculated_cost;
                } else {
                    $item = $row['item'];
                    $tlandi = $item->amount ?: 0;
                    
                    $totalTlandi += $tlandi;
                    $qoldiqRow = $tlandi;
                }
            @endphp
            <tr>
                <td style="border: 1px solid #000000; text-align: center;">{{ $index + 1 }}</td>
                <td style="border: 1px solid #000000; text-align: center;">{{ \Carbon\Carbon::parse($row['date'])->format('d.m.Y') }}</td>
                
                @if($row['type'] === 'log')
                    <td style="border: 1px solid #000000;">{{ optional($item->machinery)->name }} ({{ optional($item->building)->name }})</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $soni }}</td>
                    <td style="border: 1px solid #000000; text-align: right;">{{ number_format($narxi, 0, '.', ' ') }}</td>
                    <td style="border: 1px solid #000000; text-align: right;">{{ number_format($jami, 0, '.', ' ') }}</td>
                    <td style="border: 1px solid #000000; text-align: right;"></td>
                    <td style="border: 1px solid #000000; text-align: right;">{{ $salarka > 0 ? number_format($salarka, 0, '.', ' ') : '' }}</td>
                    <td style="border: 1px solid #000000; text-align: right;">
                        {{ $qoldiqRow < 0 ? '- ' : '+ ' }}{{ number_format(abs($qoldiqRow), 0, '.', ' ') }}
                    </td>
                @else
                    <td style="border: 1px solid #000000;">Pul ko'chirmasi / To'lov</td>
                    <td style="border: 1px solid #000000; text-align: center;"></td>
                    <td style="border: 1px solid #000000; text-align: right;"></td>
                    <td style="border: 1px solid #000000; text-align: right;"></td>
                    <td style="border: 1px solid #000000; text-align: right;">{{ number_format($tlandi, 0, '.', ' ') }}</td>
                    <td style="border: 1px solid #000000; text-align: right;"></td>
                    <td style="border: 1px solid #000000; text-align: right;">
                        {{ $qoldiqRow < 0 ? '- ' : '+ ' }}{{ number_format(abs($qoldiqRow), 0, '.', ' ') }}
                    </td>
                @endif
            </tr>
        @endforeach
        <tr>
            <td colspan="3" style="font-weight: bold; border: 1px solid #000000; text-align: center;">JAMI</td>
            <td style="border: 1px solid #000000;"></td>
            <td style="font-weight: bold; border: 1px solid #000000; text-align: right;">{{ number_format($totalNarx, 0, '.', ' ') }}</td>
            <td style="font-weight: bold; border: 1px solid #000000; text-align: right;">{{ number_format($totalJami, 0, '.', ' ') }}</td>
            <td style="font-weight: bold; border: 1px solid #000000; text-align: right;">{{ number_format($totalTlandi, 0, '.', ' ') }}</td>
            <td style="font-weight: bold; border: 1px solid #000000; text-align: right;">{{ number_format($totalSalarka, 0, '.', ' ') }}</td>
            <td style="font-weight: bold; border: 1px solid #000000; text-align: right;">
                {{ $owner->balance < 0 ? '+ ' : '- ' }}{{ number_format(abs($owner->balance), 0, '.', ' ') }}
            </td>
        </tr>
    </tbody>
</table>
