<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Inertia\Inertia;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    public function index()
    {
        // Get all transactions that have an attachment
        $receipts = Transaction::with(['creator', 'approver'])
            ->whereNotNull('attachment_path')
            ->orderBy('created_at', 'desc')
            ->get();

        // Categorize receipts for the frontend
        $groupedReceipts = [
            'partners' => [],
            'finance' => [],
            'others' => []
        ];

        foreach ($receipts as $receipt) {
            if ($receipt->partner_id) {
                $groupedReceipts['partners'][] = $receipt;
            } elseif ($receipt->account_type === 'main' || $receipt->account_type === 'credit') {
                $groupedReceipts['finance'][] = $receipt;
            } else {
                $groupedReceipts['others'][] = $receipt;
            }
        }

        return Inertia::render('Admin/Receipts/Index', [
            'groupedReceipts' => $groupedReceipts
        ]);
    }
}
