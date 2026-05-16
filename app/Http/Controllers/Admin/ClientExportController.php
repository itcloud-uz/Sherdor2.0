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
        $clients = Client::with('contracts.unit')->latest()->get();

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
            fputs($file, $bom =(chr(0xEF) . chr(0xBB) . chr(0xBF)));

            fputcsv($file, ['ID', 'Familiya', 'Ism', 'Sharif', 'Telefon', 'Pasport', 'PINFL', 'Shartnomalar', 'Ro\'yxatga olingan sana']);

            foreach ($clients as $client) {
                $contractsList = $client->contracts->map(function($c) {
                    return $c->contract_number . " (" . ($c->unit ? 'Unit: ' . $c->unit->unit_number : '') . ")";
                })->join(', ');

                fputcsv($file, [
                    $client->id,
                    $client->last_name,
                    $client->first_name,
                    $client->middle_name,
                    $client->phone,
                    $client->passport_number ?? $client->passport_series,
                    $client->pinfl,
                    $contractsList ?: 'Shartnoma yo\'q',
                    $client->created_at->format('Y-m-d H:i')
                ]);
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
}
