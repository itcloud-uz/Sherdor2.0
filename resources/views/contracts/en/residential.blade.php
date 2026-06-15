<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Residential Property Construction Contract № 4B/{{ $contract->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1, h2, h3 { text-align: center; }
        p { text-align: justify; line-height: 1.4; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-table td, .info-table th { padding: 5px; border: 1px solid #000; text-align: center; }
        .signatures { margin-top: 50px; width: 100%; }
        .signatures td { width: 45%; vertical-align: top; }
        .center { text-align: center; }
    </style>
</head>
<body>
    <div class="contract-header">
        <h2>RESIDENTIAL PROPERTY CONSTRUCTION CONTRACT № 4B/{{ $contract->id }}</h2>
        <table width="100%">
            <tr>
                <td width="50%">"{{ \Carbon\Carbon::parse($contract->created_at)->format('d') }}" {{ \Carbon\Carbon::parse($contract->created_at)->translatedFormat('F') }} {{ \Carbon\Carbon::parse($contract->created_at)->format('Y') }}</td>
                <td width="50%" style="text-align: right;">Urgut city</td>
            </tr>
        </table>
    </div>

    <p>Private Enterprise «Sher-Dor», represented by the Director J. Raxmonov acting on the basis of the Charter, hereinafter referred to as the «SELLER» on the one part, and the citizen of the Republic of Uzbekistan, registered at <strong>{{ $contract->client->address ?? '__________________' }}</strong>, <strong>{{ $contract->client->full_name }}</strong> (PINFL: <strong>{{ $contract->client->pinfl }}</strong>, Passport: <strong>{{ $contract->client->passport_series }}</strong>), hereinafter referred to as the «BUYER» on the other part, have concluded this contract as follows:</p>

    <h3>1. SUBJECT OF THE CONTRACT</h3>
    <p>1.1 The «SELLER» assumes the obligation to build a newly constructed residential apartment located at Navoi street #83, Dustlik neighborhood, Urgut city (hereinafter referred to as the «OBJECT») at the expense of its own funds or the funds of the «BUYER».</p>
    <p>1.2 According to the project estimates and agreement of the parties, the apartment consists of <strong>{{ $contract->unit ? $contract->unit->area_sqm : '____' }}</strong> sq.meters, and is located at block <strong>{{ $contract->unit && $contract->unit->floor && $contract->unit->floor->building ? ($contract->unit->floor->building->block_name ?: ($contract->unit->floor->building->corpus_name ?: '___')) : '___' }}</strong>, floor <strong>{{ $contract->unit && $contract->unit->floor ? $contract->unit->floor->floor_number : '___' }}</strong>, apartment № <strong>{{ $contract->unit ? $contract->unit->unit_number : '___' }}</strong>.</p>
    
    <h3>2. FINANCING AMOUNT AND PROCEDURE</h3>
    <table class="info-table">
        <tr>
            <th>Block</th>
            <th>Floor</th>
            <th>Rooms</th>
            <th>Total Area (sq.m.)</th>
            <th>Total Price with VAT ($)</th>
        </tr>
        <tr>
            <td>{{ $contract->unit && $contract->unit->floor && $contract->unit->floor->building ? ($contract->unit->floor->building->block_name ?: ($contract->unit->floor->building->corpus_name ?: '___')) : '___' }}</td>
            <td>{{ $contract->unit && $contract->unit->floor ? $contract->unit->floor->floor_number : '___' }}</td>
            <td>___</td>
            <td>{{ $contract->unit ? $contract->unit->area_sqm : '___' }}</td>
            <td>{{ number_format($contract->total_amount, 2) }}</td>
        </tr>
    </table>
    <p>Total contract amount with VAT is equivalent to <strong>{{ number_format($contract->total_amount, 2) }} USD</strong>.</p>
    <p>
        @if(($contract->payment_type ?? 'installment') === 'full' || $contract->term_months <= 1 || $contract->down_payment >= $contract->total_amount)
            2.2 Contract amount (100% payment): <strong>{{ number_format($contract->total_amount, 2) }} USD</strong> is fully paid by the «BUYER» within 6 (six) bank business days from the date of signing the contract.
        @else
            2.2 Down payment amount: <strong>{{ number_format($contract->down_payment, 2) }} USD</strong> is paid by the «BUYER» from the date of signing the contract, the remaining balance is paid within <strong>{{ $contract->term_months }}</strong> months according to the payment schedule.
        @endif
    </p>
    
    <table class="signatures">
        <tr>
            <td>
                <strong>«SELLER»</strong><br><br>
                «Sher-Dor» Private Enterprise<br>
                Address: Urgut, Dustlik, Navoi street #3<br>
                Account: 2020 8000 6005 5796 1001<br>
                Bank: "Agrobank" Urgut branch<br>
                Phone: (97) 708-44-44<br><br><br>
                Director: J. Raxmonov<br>
                Signature: _____________________
            </td>
            <td>
                <strong>«BUYER»</strong><br><br>
                Full Name: {{ $contract->client->full_name }}<br>
                Address: {{ $contract->client->address ?? '__________________' }}<br>
                Passport: {{ $contract->client->passport_series }}<br>
                PINFL: {{ $contract->client->pinfl }}<br>
                Phone: {{ $contract->client->phone }}<br><br><br>
                Buyer: {{ $contract->client->first_name }}<br>
                Signature: _____________________
            </td>
        </tr>
    </table>
</body>
</html>
