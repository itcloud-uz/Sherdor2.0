<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Contract Document #{{ $contract->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1, h2 { text-align: center; }
        .contract-header { margin-bottom: 20px; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-table td { padding: 5px; border-bottom: 1px solid #ccc; }
        .signatures { margin-top: 50px; width: 100%; }
        .signatures td { width: 50%; padding-top: 50px; text-align: center; border-top: 1px solid #000; }
        .spacer { margin: 0 10px; border-top: none !important; }
    </style>
</head>
<body>
    <div class="contract-header">
        <h1>SHER-DOR ERP - OFFICIAL CONTRACT</h1>
        <h2>Contract ID: #{{ str_pad($contract->id, 5, '0', STR_PAD_LEFT) }}</h2>
        <p style="text-align: right;">Date: {{ \Carbon\Carbon::parse($contract->created_at)->format('d.m.Y') }}</p>
    </div>

    <h3>1. Parties</h3>
    <p>This contract is made between <strong>ITCloud Sher-Dor</strong> (hereinafter referred to as the "Seller") and <strong>{{ $contract->client->full_name }}</strong> (hereinafter referred to as the "Buyer").</p>

    <h3>2. Object Details</h3>
    <table class="info-table">
        <tr>
            <td><strong>Client PINFL:</strong></td>
            <td>{{ $contract->client->pinfl }}</td>
            <td><strong>Passport:</strong></td>
            <td>{{ $contract->client->passport_series }}</td>
        </tr>
        <tr>
            <td><strong>Unit / Type:</strong></td>
            <td>{{ $contract->unit ? $contract->unit->type : 'N/A' }}</td>
            <td><strong>Area (m2):</strong></td>
            <td>{{ $contract->unit ? $contract->unit->area_sqm : 'N/A' }}</td>
        </tr>
    </table>

    <h3>3. Financial Terms</h3>
    <table class="info-table">
        <tr>
            <td><strong>Total Amount:</strong></td>
            <td>${{ number_format($contract->total_amount, 2) }}</td>
        </tr>
        <tr>
            <td><strong>Down Payment:</strong></td>
            <td>${{ number_format($contract->down_payment, 2) }}</td>
        </tr>
        <tr>
            <td><strong>Remaining Balance:</strong></td>
            <td>${{ number_format($contract->total_amount - $contract->down_payment, 2) }}</td>
        </tr>
        <tr>
            <td><strong>Term Months:</strong></td>
            <td>{{ $contract->term_months }} months</td>
        </tr>
    </table>

    <h3>4. Payment Schedule</h3>
    <p>The Buyer agrees to pay the remaining balance according to the monthly schedule generated. A penalty of {{ $contract->daily_penalty_rate }}% per day will apply for overdue payments.</p>

    <table class="signatures">
        <tr>
            <td><strong>Seller: ITCloud Sher-Dor</strong><br><br><br>_____________________</td>
            <td class="spacer"></td>
            <td><strong>Buyer: {{ $contract->client->full_name }}</strong><br><br><br>_____________________</td>
        </tr>
    </table>
</body>
</html>
