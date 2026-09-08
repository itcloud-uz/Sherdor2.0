<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReportExportController extends Controller
{
    public function exportExpensesReport(Request $request)
    {
        try {
            $expenses = Transaction::with(['category', 'creator', 'paymentSchedule.contract.client'])
                ->where('type', 'expense')
                ->where('status', 'approved')
                ->orderBy('created_at', 'desc')
                ->get();

            $grouped = $expenses->groupBy('category_id')->map(function ($txns) {
                $cat = $txns->first()->category;
                $catName = 'Noma\'lum';
                if ($cat) {
                    $catName = is_object($cat) ? ($cat->name ?? 'Noma\'lum') : (string)$cat;
                }
                return [
                    'category_name' => $catName,
                    'total' => $txns->sum('amount'),
                    'transactions' => $txns
                ];
            })->values();

            $totalExpenses = $expenses->sum('amount');
            $date = Carbon::now()->format('d/m/Y H:i');

            $html = '<!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <title>Chiqimlar Hisoboti</title>
                <style>
                    body { font-family: DejaVu Sans, sans-serif; font-size: 14px; color: #333; }
                    .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 20px; }
                    .title { font-size: 24px; font-weight: bold; color: #111; text-transform: uppercase; }
                    .subtitle { font-size: 12px; color: #666; }
                    .summary-table, .details-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
                    .summary-table th, .summary-table td, .details-table th, .details-table td { padding: 8px; text-align: left; border: 1px solid #ccc; }
                    .summary-table th, .details-table th { background-color: #f0f0f0; color: #333; font-weight: bold; }
                    .total-row td { font-weight: bold; font-size: 16px; background-color: #e0e0e0; }
                    .category-header { background-color: #d9edf7; font-weight: bold; padding: 10px; margin-top: 20px; border-radius: 5px; }
                    .footer { text-align: center; font-size: 10px; color: #888; margin-top: 40px; border-top: 1px solid #eee; padding-top: 10px; }
                </style>
            </head>
            <body>
                <div class="header">
                    <div class="title">SHER-DOR ERP</div>
                    <div class="subtitle">Chiqimlar bo\'yicha Umumiy Hisobot</div>
                    <div class="subtitle">Sana: ' . $date . '</div>
                </div>

                <h3>1. Kategoriya bo\'yicha Qisqacha Hisobot</h3>
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th>№</th>
                            <th>Chiqim Kategoriyasi</th>
                            <th style="text-align: right;">Umumiy Summa (UZS)</th>
                        </tr>
                    </thead>
                    <tbody>';
            
            $i = 1;
            foreach ($grouped as $group) {
                $html .= '<tr>
                    <td>' . $i++ . '</td>
                    <td>' . htmlspecialchars($group['category_name']) . '</td>
                    <td style="text-align: right;">' . number_format($group['total'], 0, '.', ' ') . '</td>
                </tr>';
            }

            $html .= '<tr class="total-row">
                        <td colspan="2" style="text-align: right;">JAMI CHIQIMLAR:</td>
                        <td style="text-align: right;">' . number_format($totalExpenses, 0, '.', ' ') . '</td>
                    </tr>
                    </tbody>
                </table>

                <h3>2. Barcha Chiqimlar Ro\'yxati (Detallashgan)</h3>';

            foreach ($grouped as $group) {
                $html .= '<div class="category-header">' . htmlspecialchars($group['category_name']) . ' - Jami: ' . number_format($group['total'], 0, '.', ' ') . ' UZS</div>
                <table class="details-table">
                    <thead>
                        <tr>
                            <th style="width: 15%;">Sana</th>
                            <th style="width: 45%;">Izoh</th>
                            <th style="width: 20%;">Kassir</th>
                            <th style="width: 20%; text-align: right;">Summa (UZS)</th>
                        </tr>
                    </thead>
                    <tbody>';
                foreach ($group['transactions'] as $txn) {
                    $creatorName = 'N/A';
                    if (is_object($txn->creator)) {
                        $creatorName = $txn->creator->name ?? 'N/A';
                    } elseif (is_string($txn->creator)) {
                        $creatorName = $txn->creator;
                    }

                    $html .= '<tr>
                        <td>' . Carbon::parse($txn->transaction_date ?: $txn->updated_at)->format('d/m/Y') . '</td>
                        <td>' . htmlspecialchars($txn->notes ?: 'Izohsiz') . '</td>
                        <td>' . htmlspecialchars($creatorName) . '</td>
                        <td style="text-align: right;">' . number_format($txn->amount, 0, '.', ' ') . '</td>
                    </tr>';
                }
                $html .= '</tbody></table>';
            }

            $html .= '<div class="footer">
                    Sher-Dor Moliya Boshqarmasi <br>
                    Ushbu hisobot avtomatik tarzda tizim tomonidan shakllantirildi.
                </div>
            </body>
            </html>';

            $pdf = Pdf::loadHTML($html);
            return $pdf->download('Chiqimlar_Hisoboti_' . Carbon::now()->format('d_m_Y') . '.pdf');
        } catch (\Throwable $e) {
            \Log::error('PDF Export Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return response($e->getMessage() . '<br><pre>' . $e->getTraceAsString() . '</pre>', 500);
        }
    }
}
