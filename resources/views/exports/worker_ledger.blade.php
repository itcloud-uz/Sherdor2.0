<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<table>
    <thead>
        <tr>
            <th colspan="2" style="font-weight: bold; border: 1px solid #000000; text-align: center;">Hisoblangan ish haqi</th>
            <th colspan="2" style="font-weight: bold; border: 1px solid #000000; text-align: center;">Berilgan ish haqi</th>
            <th rowspan="2" style="font-weight: bold; border: 1px solid #000000; text-align: center; vertical-align: middle;">Qoldiq</th>
            <th rowspan="2" style="font-weight: bold; border: 1px solid #000000; text-align: center; vertical-align: middle;">Izoh</th>
        </tr>
        <tr>
            <th style="font-weight: bold; border: 1px solid #000000; text-align: center;">Sana</th>
            <th style="font-weight: bold; border: 1px solid #000000; text-align: center;">Hisoblangan so`mma</th>
            <th style="font-weight: bold; border: 1px solid #000000; text-align: center;">Sana</th>
            <th style="font-weight: bold; border: 1px solid #000000; text-align: center;">Berilgan so`mma</th>
        </tr>
    </thead>
    <tbody>
        @php
            $totalAccrued = 0;
            $totalPaid = 0;
            $totalQoldiq = 0;
        @endphp
        @foreach($rows as $row)
            @php
                $totalAccrued += $row['accrued_amount'];
                $totalPaid += $row['paid_amount'];
                $totalQoldiq += $row['qoldiq'];
            @endphp
            <tr>
                <td style="border: 1px solid #000000; text-align: center;">{{ $row['accrued_date'] ? \Carbon\Carbon::parse($row['accrued_date'])->format('d.m.y') : '' }}</td>
                <td style="border: 1px solid #000000; text-align: right;">{{ $row['accrued_amount'] ? number_format($row['accrued_amount'], 0, '.', ' ') : '' }}</td>
                <td style="border: 1px solid #000000; text-align: center;">{{ $row['paid_date'] ? \Carbon\Carbon::parse($row['paid_date'])->format('d.m.Y') : '' }}</td>
                <td style="border: 1px solid #000000; text-align: right;">{{ $row['paid_amount'] ? number_format($row['paid_amount'], 0, '.', ' ') : '' }}</td>
                <td style="border: 1px solid #000000; text-align: right;">{{ number_format($row['qoldiq'], 0, '.', ' ') }}</td>
                <td style="border: 1px solid #000000;">{{ $row['izoh'] }}</td>
            </tr>
        @endforeach
        <tr>
            <td style="font-weight: bold; border: 1px solid #000000; text-align: center;">ЖАМИ</td>
            <td style="font-weight: bold; border: 1px solid #000000; text-align: right;">{{ number_format($totalAccrued, 0, '.', ' ') }}</td>
            <td style="border: 1px solid #000000;"></td>
            <td style="font-weight: bold; border: 1px solid #000000; text-align: right;">{{ number_format($totalPaid, 0, '.', ' ') }}</td>
            <td style="font-weight: bold; border: 1px solid #000000; text-align: right;">{{ number_format($totalQoldiq, 0, '.', ' ') }}</td>
            <td style="border: 1px solid #000000;"></td>
        </tr>
    </tbody>
</table>
