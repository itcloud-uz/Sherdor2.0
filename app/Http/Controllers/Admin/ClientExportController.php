<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Client;
use Barryvdh\DomPDF\Facade\Pdf;

class ClientExportController extends Controller
{
    public function exportExcel(Request $request)
    {
        $clients = Client::doesntHave('contracts')->latest()->get();

        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=clients_" . date('Y-m-d_H-i') . ".csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use($clients) {
            $file = fopen('php://output', 'w');
            
            // Add BOM to fix UTF-8 in Excel
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, ['ID', 'Familiya', 'Ism', 'Sharif', 'Telefon', 'Pasport', 'PINFL', 'Ro\'yxatga olingan sana'], ';');

            foreach ($clients as $client) {
                fputcsv($file, [
                    $client->id,
                    $client->last_name,
                    $client->first_name,
                    $client->middle_name,
                    $client->phone ? '="' . $client->phone . '"' : '',
                    $client->passport_number ?? $client->passport_series,
                    $client->pinfl ? '="' . $client->pinfl . '"' : '',
                    $client->created_at->format('Y-m-d H:i')
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportPdf(Request $request)
    {
        $clients = Client::with('contracts.unit')->latest()->get();

        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
            <title>Mijozlar Bazasi Hisoboti</title>
            <style>
                body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #333; }
                h2 { text-align: center; color: #111; margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
                th { background-color: #f5f5f5; font-weight: bold; }
                .date { text-align: right; font-size: 10px; color: #666; margin-bottom: 20px; }
            </style>
        </head>
        <body>
            <h2>Mijozlar Ma\'lumotlar Bazasi</h2>
            <div class="date">Sana: ' . date('d.m.Y H:i') . '</div>
            <table>
                <thead>
                    <tr>
                        <th width="5%">#ID</th>
                        <th width="20%">F.I.Sh</th>
                        <th width="15%">Telefon</th>
                        <th width="20%">Pasport / PINFL</th>
                        <th width="25%">Shartnomalar</th>
                        <th width="15%">Sana</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($clients as $client) {
            $contractsList = $client->contracts->map(function($c) {
                return $c->contract_number . " (" . ($c->unit ? 'U:' . $c->unit->unit_number : '') . ")";
            })->join('<br>');

            $html .= '<tr>
                <td>' . $client->id . '</td>
                <td>' . htmlspecialchars(trim("{$client->last_name} {$client->first_name} {$client->middle_name}")) . '</td>
                <td>' . htmlspecialchars($client->phone ?? '') . '</td>
                <td>' . htmlspecialchars($client->passport_number ?? $client->passport_series ?? '') . '<br><small>PINFL: ' . htmlspecialchars($client->pinfl ?? '') . '</small></td>
                <td>' . ($contractsList ?: 'Yo\'q') . '</td>
                <td>' . $client->created_at->format('Y-m-d') . '</td>
            </tr>';
        }

        $html .= '</tbody></table></body></html>';

        $pdf = Pdf::loadHTML($html)->setPaper('A4', 'landscape');
        return $pdf->download('clients_' . date('Y-m-d_H-i') . '.pdf');
    }

    public function exportOverdueExcel(Request $request)
    {
        $allOverdueSchedules = \App\Models\PaymentSchedule::with('contract.client')
                            ->where('status', 'overdue')
                            ->orderBy('due_date', 'asc')
                            ->get();
        $now = now();
        $strictCollectionSchedules = [];
        
        foreach ($allOverdueSchedules as $schedule) {
            if (!$schedule->contract) {
                continue;
            }

            $contractCreatedAt = \Carbon\Carbon::parse($schedule->contract->created_at);
            $contractEndDate = $contractCreatedAt->copy()->addMonths($schedule->contract->term_months);
            $daysOverdue = \Carbon\Carbon::parse($schedule->due_date)->diffInDays($now, false);

            if ($now->greaterThan($contractEndDate) || $daysOverdue >= 90) {
                $strictCollectionSchedules[] = $schedule;
            }
        }

        // Group by client
        $groups = [];
        foreach ($strictCollectionSchedules as $schedule) {
            $client = $schedule->contract->client;
            if (!$client) continue;
            $clientId = $client->id;
            if (!isset($groups[$clientId])) {
                $groups[$clientId] = [
                    'name' => trim("{$client->last_name} {$client->first_name} {$client->middle_name}"),
                    'phone' => $client->phone,
                    'total_amount' => 0,
                    'schedules_count' => 0,
                    'max_days' => 0,
                ];
            }
            $groups[$clientId]['total_amount'] += $schedule->expected_amount;
            $groups[$clientId]['schedules_count']++;
            $days = \Carbon\Carbon::parse($schedule->due_date)->diffInDays($now);
            if ($days > $groups[$clientId]['max_days']) {
                $groups[$clientId]['max_days'] = $days;
            }
        }

        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=overdue_debtors_" . date('Y-m-d_H-i') . ".csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use($groups) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");
            fputcsv($file, ['ID', 'Mijoz F.I.Sh', 'Telefon', 'Kechikkan to\'lovlar soni', 'Kechikkan Summa', 'Maksimal kechikish muddati (kun)'], ';');

            $idx = 1;
            foreach ($groups as $clientId => $data) {
                fputcsv($file, [
                    $idx++,
                    $data['name'],
                    $data['phone'] ? $data['phone'] : '',
                    $data['schedules_count'],
                    $data['total_amount'] . " so'm",
                    $data['max_days'] . " kun",
                ], ';');
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportContractsExcel(Request $request)
    {
        $contracts = \App\Models\Contract::with(['client', 'unit.floor.building'])->latest()->get();

        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=contracts_" . date('Y-m-d_H-i') . ".csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use($contracts) {
            $file = fopen('php://output', 'w');
            
            // Add BOM to fix UTF-8 in Excel
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'Shartnoma kodi', 
                'Sana', 
                'Xaridor F.I.Sh', 
                'Telefon', 
                'Bino', 
                'Xonadon', 
                'Maydoni (m²)', 
                'Kvadrat metr narxi', 
                'Boshlang\'ich to\'lov', 
                'Jami Summa', 
                'Holat'
            ], ';');

            foreach ($contracts as $contract) {
                $clientName = $contract->client ? trim("{$contract->client->last_name} {$contract->client->first_name} {$contract->client->middle_name}") : '';
                $clientPhone = $contract->client ? $contract->client->phone : '';
                $buildingName = $contract->unit?->floor?->building?->name ?? '';
                $unitNumber = $contract->unit?->unit_number ?? '';
                $area = $contract->unit?->area_sqm ?? '';
                
                fputcsv($file, [
                    $contract->contract_number ?? ('Contract #' . str_pad($contract->id, 5, '0', STR_PAD_LEFT)),
                    $contract->created_at->format('Y-m-d'),
                    $clientName,
                    $clientPhone ? '="' . $clientPhone . '"' : '',
                    $buildingName,
                    $unitNumber,
                    $area,
                    $contract->price_per_sqm,
                    $contract->down_payment,
                    $contract->total_amount,
                    $contract->status === 'active' || $contract->status === 'approved' ? 'Faol' : ($contract->status === 'cancelled' || $contract->status === 'canceled' ? 'Bekor qilingan' : $contract->status)
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
