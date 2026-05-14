<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Moliyaviy Hisobot</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #00F0FF;
            padding-bottom: 10px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #111;
            margin-bottom: 5px;
        }
        .report-title {
            font-size: 18px;
            color: #555;
            margin-bottom: 15px;
            text-transform: uppercase;
        }
        .info-table {
            width: 100%;
            margin-bottom: 20px;
        }
        .info-table td {
            padding: 5px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        table.data-table th {
            background-color: #f4f4f4;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 11px;
        }
        .text-right {
            text-align: right !important;
        }
        .nowrap {
            white-space: nowrap;
        }
        .income {
            color: #008000;
            font-weight: bold;
        }
        .expense {
            color: #FF0000;
            font-weight: bold;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #777;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        /* Column Widths */
        .col-index { width: 3%; }
        .col-date { width: 12%; }
        .col-category { width: 15%; }
        .col-type { width: 10%; }
        .col-desc { width: 40%; }
        .col-amount { width: 20%; text-align: right; white-space: nowrap; }
    </style>
</head>
<body>

    <div class="header">
        <div class="company-name">{{ $companyName }}</div>
        <div class="report-title">Moliyaviy Hisobot</div>
    </div>

    <table class="info-table">
        <tr>
            <td><strong>Kategoriya:</strong> {{ strtoupper(str_replace('_', ' ', $category)) }}</td>
            <td class="text-right"><strong>Yaratilgan sana:</strong> {{ $generatedAt }}</td>
        </tr>
        <tr>
            <td>
                <strong>Oraliq:</strong> 
                @if($startDate && $endDate)
                    {{ $startDate }} dan {{ $endDate }} gacha
                @elseif($startDate)
                    {{ $startDate }} dan boshlab
                @elseif($endDate)
                    {{ $endDate }} gacha
                @else
                    Barcha vaqt
                @endif
            </td>
            <td class="text-right"><strong>Jami yozuvlar:</strong> {{ count($data) }} ta</td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th class="col-index">#</th>
                <th class="col-date">Sana</th>
                <th class="col-category">Kategoriya</th>
                <th class="col-type">Tur</th>
                <th class="col-desc">Tafsilot (Sabab)</th>
                <th class="col-amount">Miqdor (UZS)</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $totalIncome = 0;
                $totalExpense = 0;
            @endphp

            @forelse ($data as $index => $item)
                @php
                    if($item['type'] == 'income') $totalIncome += $item['amount'];
                    if($item['type'] == 'expense') $totalExpense += $item['amount'];
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item['date'] }}</td>
                    <td>{{ $item['category'] }}</td>
                    <td>
                        @if($item['type'] == 'income')
                            <span class="income">Tushum</span>
                        @else
                            <span class="expense">Chiqim</span>
                        @endif
                    </td>
                    <td>{{ $item['description'] }}</td>
                    <td class="col-amount">
                        @if($item['type'] == 'income')
                            <span class="income">+{{ number_format($item['amount'], 0, '.', ' ') }}</span>
                        @else
                            <span class="expense">-{{ number_format($item['amount'], 0, '.', ' ') }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; padding: 20px;">Ushbu oraliqda ma'lumot topilmadi.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top: 20px;">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="width: 50%;"></td>
                <td style="width: 50%;">
                    <table class="data-table">
                        <tr>
                            <th>Jami Tushum:</th>
                            <td class="nowrap text-right income">+{{ number_format($totalIncome, 0, '.', ' ') }} UZS</td>
                        </tr>
                        <tr>
                            <th>Jami Chiqim:</th>
                            <td class="nowrap text-right expense">-{{ number_format($totalExpense, 0, '.', ' ') }} UZS</td>
                        </tr>
                        <tr>
                            <th>Qoldiq (Balans):</th>
                            <td class="nowrap text-right" style="font-weight: bold; font-size: 14px;">
                                {{ number_format($totalIncome - $totalExpense, 0, '.', ' ') }} UZS
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Mazkur hisobot {{ $companyName }} ERP tizimi tomonidan avtomatik ravishda shakllantirildi.<br>
        Barcha huquqlar himoyalangan &copy; {{ date('Y') }}
    </div>

</body>
</html>
