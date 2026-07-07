<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', 'Arial', sans-serif; font-size: 13px; line-height: 1.5; color: #000; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .mt-4 { margin-top: 1rem; }
        .mb-4 { margin-bottom: 1rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        td, th { padding: 8px; vertical-align: top; }
        .border td { border: 1px solid #000; }
    </style>
</head>
<body>
    @php
        // Fetch the parent residential contract for this client
        $parentContract = \App\Models\Contract::where('client_id', $client->id)
            ->whereHas('unit', function($q) {
                $q->where('type', 'residential');
            })->orderBy('created_at', 'asc')->first();

        $parentDate = $parentContract ? \Carbon\Carbon::parse($parentContract->created_at)->format('d.m.Y') : '___.___.202__';
        $parentNumber = $parentContract ? ($parentContract->contract_number ?? $parentContract->id) : '______';
        
        $months = [
            1 => 'январ', 2 => 'феврал', 3 => 'март', 4 => 'апрел',
            5 => 'май', 6 => 'июн', 7 => 'июл', 8 => 'август',
            9 => 'сентябр', 10 => 'октябр', 11 => 'ноябр', 12 => 'декабр'
        ];
        $created_day = $contract->created_at ? $contract->created_at->format('d') : date('d');
        $created_month = $contract->created_at ? $months[(int)$contract->created_at->format('m')] : $months[(int)date('m')];
        $created_year = $contract->created_at ? $contract->created_at->format('Y') : date('Y');
        
        $b_day = $client->birth_date ? \Carbon\Carbon::parse($client->birth_date)->format('d') : '__';
        $b_month = $client->birth_date ? $months[(int)\Carbon\Carbon::parse($client->birth_date)->format('m')] : '_____';
        $b_year = $client->birth_date ? \Carbon\Carbon::parse($client->birth_date)->format('Y') : '19__';
        
        $p_day = $client->passport_issued_date ? \Carbon\Carbon::parse($client->passport_issued_date)->format('d') : '__';
        $p_month = $client->passport_issued_date ? $months[(int)\Carbon\Carbon::parse($client->passport_issued_date)->format('m')] : '_____';
        $p_year = $client->passport_issued_date ? \Carbon\Carbon::parse($client->passport_issued_date)->format('Y') : '202_';
    @endphp

    <div class="text-center font-bold mb-4">
        Янги курилаётган куп каватли уй-жойдан хонадон сотиб олиш учун олдиндан тулов киритиш хакида {{ $parentDate }} йилдаги {{ $parentNumber }} сонли шартномага
        <br><br>
        ҚЎШИМЧА КЕЛИШУВ №1
    </div>

    <table style="margin-bottom: 20px; border: none;">
        <tr>
            <td style="width: 50%; border: none;">«{{ $created_day }}» {{ $created_month }} {{ $created_year }} йил</td>
            <td style="width: 50%; text-align: right; border: none;">Самарканд ш.</td>
        </tr>
    </table>

    <p style="text-indent: 40px; text-align: justify;">
        Устав асосида иш олиб борувчи “ZAMON SIFAT SERVIS” масъулияти чекланган жамияти (СТИР:302455208) номидан жамият директори Э.В.Шарипов, матнда кейинги ўринларда “Жамият” деб юритилувчи, бир тарафдан ҳамда Ўзбекистон Республикаси фуқароси {{ $client->full_name }} {{ $client->passport_series }} {{ $client->passport_number }} паспорт серия рақамли {{ $client->passport_issued_by }} томонидан {{ $client->passport_issued_date ? \Carbon\Carbon::parse($client->passport_issued_date)->format('d.m.Y') : '___.___.202_' }} йил берилган, биргаликда “Тарафлар” деб номланиб,Ўзбекистон Республикаси Фуқаролик кодекси ва бошқа қонунчилик ҳужжатларига мувофиқ ўз манфаатлари йўлида иш юритиб, келгуси матнда “Келишув” деб юритилувчи ушбу Турар-жой биноси қурилишига инвестицион бадал тўғрисидаги қўшимча келишувни қуйидагилар тўғрисида туздилар:
    </p>

    <div style="margin-top: 20px;">
        1. Шартноманинг 1.4-бандига К,Л,М бандлари кушимча равишда кушилади:<br>
        К) Автотураржой тартиб раками № {{ $unit->unit_number ?? '___' }}<br>
        Л) Автотураржойнинг кавати № {{ $unit->floor->floor_number ?? '___' }}<br>
        М) Автотураржойнинг умумий нархи {{ number_format($contract->total_amount, 0, '', ' ') }} сум<br>
        <br>
        2. Шартномага кушимча равишда 2-илова кушилади, шартноманинг бошқа шартлари ўзгаришсиз қолдирилади ва мазкур ўзгартириш мажбурий бўлиб ҳисобланади.
    </div>

    <div class="font-bold text-center mt-4 mb-4">Тарафларнинг реквизитлари:</div>

    <table class="border">
        <tr>
            <th style="width: 50%; text-align: center;">«КУРУВЧИ КОРХОНА»</th>
            <th style="width: 50%; text-align: center;">«БУЮРТМАЧИ»</th>
        </tr>
        <tr>
            <td>
                <strong>МЧЖ «Замон Сифат Сервис»</strong><br>
                Самарканд шахар Фирдавсий кучаси 81 уй<br>
                «Асака банк» Самарканд шахар ф-л.<br>
                х/р: 20208000205015872001<br>
                МФО: 00873<br>
                СТИР: 302455208<br><br><br>
                Директор _______________ Э.В.Шарипов<br>
                <div style="text-align: center; margin-top: 20px;">М.У</div>
            </td>
            <td>
                <strong>{{ $client->full_name }}</strong><br>
                {{ $b_year }} йил {{ $b_day }}- {{ $b_month }} куни тугилган<br>
                Паспорт серияси {{ $client->passport_series }} раками {{ $client->passport_number }} {{ $client->passport_issued_by }} томонидан “{{ $p_day }}” {{ $p_month }} {{ $p_year }} йилда берилган.<br>
                Яшаш манзили: {{ $client->address }}<br><br><br>
                Буюртмачи _________________<br>
                <br>
            </td>
        </tr>
    </table>
</body>
</html>
