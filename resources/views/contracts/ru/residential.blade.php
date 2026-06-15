<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Договор Долевого Строительства Жилья № 4B/{{ $contract->id }}</title>
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
        <h2>ДОГОВОР ДОЛЕВОГО СТРОИТЕЛЬСТВА ЖИЛЬЯ № 4B/{{ $contract->id }}</h2>
        <table width="100%">
            <tr>
                <td width="50%">"{{ \Carbon\Carbon::parse($contract->created_at)->format('d') }}" {{ \Carbon\Carbon::parse($contract->created_at)->translatedFormat('F') }} {{ \Carbon\Carbon::parse($contract->created_at)->format('Y') }} г.</td>
                <td width="50%" style="text-align: right;">г. Ургут</td>
            </tr>
        </table>
    </div>

    <p>Частное Предприятие «Sher-Dor», в лице Директора Ж. Рахмонова, действующего на основании Устава, именуемое в дальнейшем «ПРОДАВЕЦ» с одной стороны, и гражданин Республики Узбекистан, зарегистрированный по адресу <strong>{{ $contract->client->address ?? '__________________' }}</strong>, <strong>{{ $contract->client->full_name }}</strong> (ПИНФЛ: <strong>{{ $contract->client->pinfl }}</strong>, Паспорт: <strong>{{ $contract->client->passport_series }}</strong>), именуемый в дальнейшем «ПОКУПАТЕЛЬ» с другой стороны, заключили настоящий договор о нижеследующем:</p>

    <h3>1. ПРЕДМЕТ ДОГОВОРА</h3>
    <p>1.1 «ПРОДАВЕЦ» обязуется построить многоквартирный жилой дом, расположенный по адресу г. Ургут, махалля Дустлик, улица Навои, д. 83 (в дальнейшем именуемый «ОБЪЕКТ») за счет собственных средств или средств «ПОКУПАТЕЛЯ».</p>
    <p>1.2 Согласно проектно-сметной документации и соглашению сторон, квартира состоит из <strong>{{ $contract->unit ? $contract->unit->area_sqm : '____' }}</strong> кв.м, и расположена в блоке <strong>{{ $contract->unit && $contract->unit->floor && $contract->unit->floor->building ? ($contract->unit->floor->building->block_name ?: ($contract->unit->floor->building->corpus_name ?: '___')) : '___' }}</strong>, на <strong>{{ $contract->unit && $contract->unit->floor ? $contract->unit->floor->floor_number : '___' }}</strong> этаже, квартира № <strong>{{ $contract->unit ? $contract->unit->unit_number : '___' }}</strong>.</p>
    
    <h3>2. РАЗМЕР И ПОРЯДОК ФИНАНСИРОВАНИЯ</h3>
    <table class="info-table">
        <tr>
            <th>Блок</th>
            <th>Этаж</th>
            <th>Количество комнат</th>
            <th>Общая площадь (кв.м.)</th>
            <th>Общая стоимость с НДС ($)</th>
        </tr>
        <tr>
            <td>{{ $contract->unit && $contract->unit->floor && $contract->unit->floor->building ? ($contract->unit->floor->building->block_name ?: ($contract->unit->floor->building->corpus_name ?: '___')) : '___' }}</td>
            <td>{{ $contract->unit && $contract->unit->floor ? $contract->unit->floor->floor_number : '___' }}</td>
            <td>___</td>
            <td>{{ $contract->unit ? $contract->unit->area_sqm : '___' }}</td>
            <td>{{ number_format($contract->total_amount, 2) }}</td>
        </tr>
    </table>
    <p>Общая сумма договора с учетом НДС эквивалентна <strong>{{ number_format($contract->total_amount, 2) }} Долларов США</strong>.</p>
    <p>
        @if(($contract->payment_type ?? 'installment') === 'full' || $contract->term_months <= 1 || $contract->down_payment >= $contract->total_amount)
            2.2 Сумма договора (100% оплата): <strong>{{ number_format($contract->total_amount, 2) }} Долларов США</strong> оплачивается «ПОКУПАТЕЛЕМ» в течение 6 (шести) банковских дней со дня подписания договора.
        @else
            2.2 Сумма первоначального взноса: <strong>{{ number_format($contract->down_payment, 2) }} Долларов США</strong> оплачивается «ПОКУПАТЕЛЕМ» со дня подписания договора, оставшаяся сумма оплачивается в течение <strong>{{ $contract->term_months }}</strong> месяцев согласно графику платежей.
        @endif
    </p>
    
    <table class="signatures">
        <tr>
            <td>
                <strong>«ПРОДАВЕЦ»</strong><br><br>
                ЧП «Sher-Dor»<br>
                Адрес: Ургут, Дустлик, ул. Навои №3<br>
                Р/С: 2020 8000 6005 5796 1001<br>
                Банк: АКБ "Агробанк" Ургутский филиал<br>
                Телефон: (97) 708-44-44<br><br><br>
                Директор: Ж. Рахмонов<br>
                Подпись: _____________________
            </td>
            <td>
                <strong>«ПОКУПАТЕЛЬ»</strong><br><br>
                Ф.И.О: {{ $contract->client->full_name }}<br>
                Адрес: {{ $contract->client->address ?? '__________________' }}<br>
                Паспорт: {{ $contract->client->passport_series }}<br>
                ПИНФЛ: {{ $contract->client->pinfl }}<br>
                Телефон: {{ $contract->client->phone }}<br><br><br>
                Покупатель: {{ $contract->client->first_name }}<br>
                Подпись: _____________________
            </td>
        </tr>
    </table>
</body>
</html>
