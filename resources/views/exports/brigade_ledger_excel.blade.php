<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<table>
    <thead>
        <tr>
            <th colspan="9" style="font-weight: bold; font-size: 16px; text-align: center;">{{ strtoupper($brigade->name) }} (UMUMIY XISOBOT)</th>
        </tr>
        <tr>
            <th rowspan="2" style="font-weight: bold; border: 1px solid #000000; text-align: center; vertical-align: middle;">№</th>
            <th rowspan="2" style="font-weight: bold; border: 1px solid #000000; text-align: center; vertical-align: middle;">Sana</th>
            <th colspan="4" style="font-weight: bold; border: 1px solid #000000; text-align: center;">BAJARILGAN ISHLAR</th>
            <th colspan="2" style="font-weight: bold; border: 1px solid #000000; text-align: center;">TO'LANDI / USHLANDI</th>
            <th rowspan="2" style="font-weight: bold; border: 1px solid #000000; text-align: center; vertical-align: middle;">Qoldiq</th>
        </tr>
        <tr>
            <th style="font-weight: bold; border: 1px solid #000000; text-align: center;">Obyekt / Ishchi / Izoh</th>
            <th style="font-weight: bold; border: 1px solid #000000; text-align: center;">Hajm (soat/dona)</th>
            <th style="font-weight: bold; border: 1px solid #000000; text-align: center;">Narxi</th>
            <th style="font-weight: bold; border: 1px solid #000000; text-align: center;">Jami yozildi (Kirim)</th>
            <th style="font-weight: bold; border: 1px solid #000000; text-align: center;">To'landi / Avans</th>
            <th style="font-weight: bold; border: 1px solid #000000; text-align: center;">Ushlanma (Jarima)</th>
        </tr>
    </thead>
    <tbody>
        @php
            $totalJami = 0;
            $totalTlandi = 0;
            $totalUshlanma = 0;
            $qoldiqRunning = 0;
        @endphp
        @foreach($items as $index => $row)
            @php
                if ($row['type'] === 'log') {
                    $item = $row['item'];
                    $soni = $item->hours_worked ?: ($item->volume_done ?: 1);
                    $narxi = $item->rate ?: 0;
                    $ushlanma = $item->deductions ?: 0;
                    $jami = $item->calculated_cost + $ushlanma;
                    
                    $totalJami += $jami;
                    $totalUshlanma += $ushlanma;
                    $qoldiqRow = $item->calculated_cost; // Positive because company owes brigade for work
                } else {
                    $item = $row['item'];
                    
                    if ($item->type === 'salary_accrual') {
                        $totalJami += $item->amount;
                        $qoldiqRow = $item->amount;
                    } else if ($item->type === 'penalty') {
                        $totalUshlanma += $item->amount;
                        $qoldiqRow = -$item->amount;
                    } else {
                        $totalTlandi += $item->amount;
                        $qoldiqRow = -$item->amount;
                    }
                }
            @endphp
            <tr>
                <td style="border: 1px solid #000000; text-align: center;">{{ $index + 1 }}</td>
                <td style="border: 1px solid #000000; text-align: center;">{{ \Carbon\Carbon::parse($row['date'])->format('d.m.Y') }}</td>
                
                @if($row['type'] === 'log')
                    <td style="border: 1px solid #000000;">{{ optional($item->worker)->name }} - {{ optional($item->building)->name }} ({{ $item->description }})</td>
                    <td style="border: 1px solid #000000; text-align: center;">{{ $soni }}</td>
                    <td style="border: 1px solid #000000; text-align: right;">{{ number_format($narxi, 0, '.', ' ') }}</td>
                    <td style="border: 1px solid #000000; text-align: right;">{{ number_format($jami, 0, '.', ' ') }}</td>
                    <td style="border: 1px solid #000000; text-align: right;"></td>
                    <td style="border: 1px solid #000000; text-align: right;">{{ $ushlanma > 0 ? number_format($ushlanma, 0, '.', ' ') : '' }}</td>
                    <td style="border: 1px solid #000000; text-align: right; color: {{ $qoldiqRow > 0 ? 'green' : 'red' }}">
                        {{ $qoldiqRow > 0 ? '+ ' : '- ' }}{{ number_format(abs($qoldiqRow), 0, '.', ' ') }}
                    </td>
                @else
                    <td style="border: 1px solid #000000;">{{ ucfirst(str_replace('_', ' ', $item->type)) }} ({{ $item->description }})</td>
                    <td style="border: 1px solid #000000; text-align: center;"></td>
                    <td style="border: 1px solid #000000; text-align: right;"></td>
                    <td style="border: 1px solid #000000; text-align: right;">{{ $item->type === 'salary_accrual' ? number_format($item->amount, 0, '.', ' ') : '' }}</td>
                    <td style="border: 1px solid #000000; text-align: right;">{{ in_array($item->type, ['payment', 'advance_payment']) ? number_format($item->amount, 0, '.', ' ') : '' }}</td>
                    <td style="border: 1px solid #000000; text-align: right;">{{ $item->type === 'penalty' ? number_format($item->amount, 0, '.', ' ') : '' }}</td>
                    <td style="border: 1px solid #000000; text-align: right; color: {{ $qoldiqRow > 0 ? 'green' : 'red' }}">
                        {{ $qoldiqRow > 0 ? '+ ' : '- ' }}{{ number_format(abs($qoldiqRow), 0, '.', ' ') }}
                    </td>
                @endif
            </tr>
        @endforeach
        <tr>
            <td colspan="3" style="font-weight: bold; border: 1px solid #000000; text-align: center;">JAMI</td>
            <td style="border: 1px solid #000000;"></td>
            <td style="border: 1px solid #000000;"></td>
            <td style="font-weight: bold; border: 1px solid #000000; text-align: right;">{{ number_format($totalJami, 0, '.', ' ') }}</td>
            <td style="font-weight: bold; border: 1px solid #000000; text-align: right;">{{ number_format($totalTlandi, 0, '.', ' ') }}</td>
            <td style="font-weight: bold; border: 1px solid #000000; text-align: right;">{{ number_format($totalUshlanma, 0, '.', ' ') }}</td>
            <td style="font-weight: bold; border: 1px solid #000000; text-align: right; color: {{ $brigade->balance > 0 ? 'green' : 'red' }}">
                {{ $brigade->balance > 0 ? '+ ' : '- ' }}{{ number_format(abs($brigade->balance), 0, '.', ' ') }}
            </td>
        </tr>
    </tbody>
</table>
