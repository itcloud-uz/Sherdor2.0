<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class TransactionExportController extends Controller
{
    public function print(Transaction $transaction)
    {
        $transaction->load(['creator', 'approver', 'paymentSchedule.contract.client', 'paymentSchedule.contract.unit']);

        // Fetch QR Code via API
        $qrCodeUrl = config('app.url') . '/receipt/verify/' . $transaction->receipt_hash;
        $qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($qrCodeUrl);

        $clientName = 'N/A';
        $clientPhone = 'N/A';
        $apartmentInfo = 'N/A';

        if ($transaction->payment_schedule_id && $transaction->paymentSchedule && $transaction->paymentSchedule->contract) {
            $client = $transaction->paymentSchedule->contract->client;
            if ($client) {
                $clientName = $client->first_name . ' ' . $client->last_name;
                $clientPhone = $client->phone;
            }
            $unit = $transaction->paymentSchedule->contract->unit;
            if ($unit) {
                $apartmentInfo = $unit->number . ' - kvartira';
            }
        }

        return view('print.receipt', compact('transaction', 'qrApiUrl', 'clientName', 'clientPhone', 'apartmentInfo'));
    }

    public function exportPdf(Transaction $transaction)
    {
        $transaction->load(['creator', 'approver', 'paymentSchedule.contract.client', 'paymentSchedule.contract.unit']);

        // Fetch QR Code via API as SVG and encode to base64 to avoid PHP GD dependency
        $qrCodeUrl = config('app.url') . '/receipt/verify/' . $transaction->receipt_hash;
        $qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=130x130&format=svg&data=' . urlencode($qrCodeUrl);
        $qrSvg = @file_get_contents($qrApiUrl); // Fetch raw SVG string
        
        $qrImageSrc = '';
        if ($qrSvg && strpos($qrSvg, '<svg') !== false) {
            $qrImageSrc = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);
        }

        $clientName = 'N/A';
        $clientPhone = 'N/A';
        $apartmentInfo = 'N/A';

        if ($transaction->payment_schedule_id && $transaction->paymentSchedule && $transaction->paymentSchedule->contract) {
            $client = $transaction->paymentSchedule->contract->client;
            if ($client) {
                $clientName = $client->first_name . ' ' . $client->last_name;
                $clientPhone = $client->phone;
            }
            $unit = $transaction->paymentSchedule->contract->unit;
            if ($unit) {
                $apartmentInfo = $unit->number . ' - kvartira';
            }
        }

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Tranzaksiya Kvitansiyasi - ' . $transaction->receipt_hash . '</title>
            <style>
                body { font-family: DejaVu Sans, sans-serif; font-size: 14px; color: #333; }
                .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 20px; }
                .title { font-size: 24px; font-weight: bold; color: #111; text-transform: uppercase; }
                .subtitle { font-size: 12px; color: #666; }
                .content-wrapper { width: 100%; display: table; }
                .details-col { display: table-cell; width: 70%; vertical-align: top; }
                .qr-col { display: table-cell; width: 30%; vertical-align: top; text-align: right; padding-top: 10px; }
                .details-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                .details-table th, .details-table td { padding: 10px; text-align: left; border-bottom: 1px dashed #ccc; }
                .details-table th { width: 45%; color: #555; font-size: 13px; }
                .total-row { font-size: 18px; font-weight: bold; background-color: #f9f9f9; }
                .qr-box { text-align: center; padding: 10px; border: 1px solid #eee; background: #fafafa; border-radius: 8px; display: inline-block; }
                .qr-text { font-size: 10px; color: #555; margin-top: 5px; }
                .footer { text-align: center; font-size: 10px; color: #888; margin-top: 40px; border-top: 1px solid #eee; padding-top: 10px; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="title">SHER-DOR ERP</div>
                <div class="subtitle">Rasmiy To\'lov Kvitansiyasi</div>
            </div>

            <div class="content-wrapper">
                <div class="details-col">
                    <table class="details-table">
                        <tr>
                            <th>Kvitansiya Raqami:</th>
                            <td>' . ($transaction->receipt_hash ?? 'N/A') . '</td>
                        </tr>
                        <tr>
                            <th>Sana:</th>
                            <td>' . Carbon::parse($transaction->updated_at)->format('d.m.Y H:i') . '</td>
                        </tr>
                        <tr>
                            <th>Mijoz:</th>
                            <td><b>' . $clientName . '</b></td>
                        </tr>
                        <tr>
                            <th>Kvartira / Obyekt:</th>
                            <td>' . $apartmentInfo . '</td>
                        </tr>
                        <tr>
                            <th>Mijoz Telefoni:</th>
                            <td>' . $clientPhone . '</td>
                        </tr>
                        <tr>
                            <th>Turi:</th>
                            <td>' . strtoupper($transaction->type === 'income' ? 'Kirim' : 'Chiqim') . '</td>
                        </tr>
                        <tr>
                            <th>To\'lov Usuli:</th>
                            <td>' . strtoupper($transaction->payment_method) . '</td>
                        </tr>
                        <tr>
                            <th>Qabul Qildi (Kassir):</th>
                            <td>' . ($transaction->creator->name ?? 'N/A') . '</td>
                        </tr>
                        <tr>
                            <th>Tasdiqladi (Admin):</th>
                            <td>' . ($transaction->approver->name ?? 'Kutmoqda') . '</td>
                        </tr>
                        <tr>
                            <th>Izoh:</th>
                            <td>' . ($transaction->notes ?? 'Izoh kiritilmagan') . '</td>
                        </tr>
                        <tr class="total-row">
                            <th>Umumiy Summa:</th>
                            <td>' . number_format($transaction->amount, 0, '.', ' ') . ' UZS</td>
                        </tr>
                    </table>
                </div>
                <div class="qr-col">
                    <div class="qr-box">
                        <img src="' . $qrImageSrc . '" width="130" height="130" alt="QR Code">
                        <div class="qr-text">Holatni tekshirish<br>uchun skaner qiling</div>
                    </div>
                </div>
            </div>

            <div class="footer">
                Ushbu hujjat elektron shaklda yaratilgan. Tizim raqami: ' . $transaction->id . '<br>
                Sher-Dor Moliya Boshqarmasi
            </div>
        </body>
        </html>';

        $pdf = Pdf::loadHTML($html);
        return $pdf->download('tranzaksiya_' . $transaction->receipt_hash . '.pdf');
    }
}
