<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Contract;
use App\Models\CallLog;
use App\Services\EskizService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CrmController extends Controller
{
    public function index()
    {
        $leads = Lead::latest()->limit(500)->get();
        $clients = \App\Models\Client::with('contracts.unit.floor.building')->orderBy('id', 'asc')->limit(500)->get();
        $latestClientId = \App\Models\Client::orderBy('id', 'desc')->first()?->id;
        $contracts = Contract::with(['client', 'partner.user', 'unit.floor.building', 'paymentSchedules', 'transactions.category'])->latest()->limit(500)->get();
        $units = \App\Models\Unit::with('floor.building')->get();
        $partners = \App\Models\Partner::with('user')->get();
        $allOverdueSchedules = \App\Models\PaymentSchedule::with('contract.client')
                            ->where('status', 'overdue')
                            ->orderBy('due_date', 'asc')
                            ->limit(500)
                            ->get();

        $overdueSchedules = [];
        $strictCollectionSchedules = [];
        $now = now();

        foreach ($allOverdueSchedules as $schedule) {
            if (!$schedule->contract) {
                $overdueSchedules[] = $schedule;
                continue;
            }

            $contractCreatedAt = \Carbon\Carbon::parse($schedule->contract->created_at);
            $contractEndDate = $contractCreatedAt->copy()->addMonths($schedule->contract->term_months);
            $daysOverdue = \Carbon\Carbon::parse($schedule->due_date)->diffInDays($now, false);

            if ($now->greaterThan($contractEndDate) || $daysOverdue >= 90) {
                $strictCollectionSchedules[] = $schedule;
            } else {
                $overdueSchedules[] = $schedule;
            }
        }

        return Inertia::render('Admin/CRM/Index', [
            'leads' => $leads,
            'clients' => $clients,
            'latestClientId' => $latestClientId,
            'contracts' => $contracts,
            'units' => $units,
            'partners' => $partners,
            'overdueSchedules' => $overdueSchedules,
            'strictCollectionSchedules' => $strictCollectionSchedules,
        ]);
    }

    public function storeClient(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'middle_name' => 'nullable|string',
            'passport_series' => 'nullable|string',
            'pinfl' => 'nullable|string',
            'phone' => 'required|string', // SQLite column is NOT NULL
            'address' => 'nullable|string',
            'passport_issued_date' => 'nullable|date',
            'passport_issued_by' => 'nullable|string',
            'created_at' => 'nullable|date'
        ]);

        if (array_key_exists('passport_series', $validated)) {
            $validated['passport_number'] = $validated['passport_series'];
            unset($validated['passport_series']);
        }

        try {
            $client = new \App\Models\Client($validated);
            if (!empty($validated['created_at'])) {
                $client->created_at = $validated['created_at'];
            }
            $client->save();
            if ($request->wantsJson()) {
                return response()->json(['status' => 'success', 'client' => $client]);
            }
            return redirect()->back()->with('message', 'Client created successfully.');
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
            }
            return back()->withErrors(['server_error' => $e->getMessage()]);
        }
    }

    public function updateClient(\Illuminate\Http\Request $request, \App\Models\Client $client)
    {
        $validated = $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'middle_name' => 'nullable|string',
            'passport_series' => 'nullable|string',
            'pinfl' => 'nullable|string',
            'phone' => 'required|string',
            'address' => 'nullable|string',
            'passport_issued_date' => 'nullable|date',
            'passport_issued_by' => 'nullable|string',
            'created_at' => 'nullable|date'
        ]);

        if (array_key_exists('passport_series', $validated)) {
            $validated['passport_number'] = $validated['passport_series'];
            unset($validated['passport_series']);
        }

        try {
            $client->fill($validated);
            if (!empty($validated['created_at'])) {
                $client->created_at = $validated['created_at'];
            }
            $client->save();
            return redirect()->back()->with('message', 'Client updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['server_error' => $e->getMessage()]);
        }
    }

    public function deleteClient(\App\Models\Client $client)
    {
        $client->delete();
        return redirect()->back()->with('message', 'Client deleted successfully.');
    }

    public function logContact(Request $request, Lead $lead)
    {
        // Mock a CallLog entry
        CallLog::create([
            'client_id' => null,
            'worker_id' => auth()->id(),
            'phone_number' => $lead->phone,
            'type' => 'outbound',
            'status' => 'answered',
            'duration_seconds' => rand(30, 120),
        ]);

        $lead->status = 'contacted';
        $lead->save();

        return redirect()->back()->with('message', 'Bog\'lanildi va status yangilandi.');
    }

    public function updateLeadStatus(Request $request, Lead $lead)
    {
        $validated = $request->validate([
            'status' => 'required|in:new,contacted,qualified,lost',
            'reason' => 'nullable|string'
        ]);

        $lead->status = $validated['status'];
        if ($validated['status'] === 'lost' && !empty($validated['reason'])) {
            $lead->reason = $validated['reason'];
        }
        $lead->save();

        return redirect()->back()->with('message', 'Status yangilandi.');
    }

    public function quickSms(Request $request, Lead $lead, EskizService $eskiz)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:1000'
        ]);

        try {
            $eskiz->sendSms($lead->phone, $validated['message'], 'quick_sms');
            $lead->status = 'contacted';
            $lead->save();
            return redirect()->back()->with('message', 'SMS yuborildi va status yangilandi.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['message' => 'SMS yuborishda xatolik: ' . $e->getMessage()]);
        }
    }

    public function updateLead(Request $request, Lead $lead)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
        ]);

        $lead->full_name = $validated['full_name'];
        $lead->phone = $validated['phone'];
        $lead->save();

        return redirect()->back()->with('message', 'Lid ma\'lumotlari yangilandi.');
    }

    public function destroyLead(Lead $lead)
    {
        $lead->delete();
        return redirect()->back()->with('message', 'Lid muvaffaqiyatli o\'chirildi.');
    }

    public function paySchedule(Request $request, \App\Models\PaymentSchedule $schedule)
    {
        $validated = $request->validate([
            'paid_amount' => 'required|numeric|min:0',
            'payment_method' => 'nullable|string|in:naqd,bank,karta',
            'actual_payment_date' => 'nullable|date'
        ]);

        $contract = $schedule->contract;
        if (!$contract) {
            return redirect()->back()->withErrors(['message' => 'Shartnoma topilmadi.']);
        }

        $newPaymentAmount = $validated['paid_amount'];
        $remainingAmount = $newPaymentAmount;

        if ($remainingAmount <= 0) {
            return redirect()->back()->with('message', 'To\'lov summasi 0 dan katta bo\'lishi kerak.');
        }

        // Get all unpaid schedules for this contract ordered by due_date
        $schedules = \App\Models\PaymentSchedule::where('contract_id', $contract->id)
            ->where('status', '!=', 'paid')
            ->orderBy('due_date', 'asc')
            ->get();

        foreach ($schedules as $s) {
            if ($remainingAmount <= 0) break;
            
            $needed = $s->expected_amount - $s->paid_amount;
            if ($needed <= 0) continue;

            if ($remainingAmount >= $needed) {
                $s->paid_amount += $needed;
                $s->status = 'paid';
                $remainingAmount -= $needed;
            } else {
                $s->paid_amount += $remainingAmount;
                $s->status = 'partial';
                $remainingAmount = 0;
            }

            $s->actual_payment_date = $validated['actual_payment_date'] ?? now()->format('Y-m-d');
            if (isset($validated['payment_method'])) {
                $s->payment_method = $validated['payment_method'];
            }
            if ($s->id === $schedule->id) {
                $s->original_payment_amount = $newPaymentAmount;
            }
            
            $s->remaining_debt = max(0, $s->expected_amount - $s->paid_amount);
            
            if ($s->status === 'paid') {
                $s->penalty_for_this_month = 0;
            } else {
                // Re-evaluate penalty if overdue
                if (new \DateTime($s->due_date) < new \DateTime()) {
                    $daysOverdue = floor((time() - strtotime($s->due_date)) / (60 * 60 * 24));
                    if ($daysOverdue > 0) {
                        $s->penalty_for_this_month = $daysOverdue * $s->remaining_debt * ($contract->daily_penalty_rate / 100);
                    }
                } else {
                    $s->penalty_for_this_month = 0;
                }
            }
            
            $s->save();
        }

        // Update total accumulated penalty
        $totalPenalty = $contract->paymentSchedules()->sum('penalty_for_this_month');
        $contract->total_penalty_accumulated = $totalPenalty;
        $contract->save();

        return redirect()->back()->with('message', 'To\'lov muvaffaqiyatli yoyildi va saqlandi.');
    }

    public function recalculatePenalty(Request $request, \App\Models\Contract $contract)
    {
        $validated = $request->validate([
            'daily_penalty_rate' => 'required|numeric|min:0|max:100'
        ]);

        $contract->daily_penalty_rate = $validated['daily_penalty_rate'];
        $contract->save();

        $totalPenalty = 0;
        foreach ($contract->paymentSchedules as $schedule) {
            $unpaidBalance = max(0, $schedule->expected_amount - $schedule->paid_amount);
            $schedule->remaining_debt = $unpaidBalance;

            if ($schedule->status !== 'paid' && new \DateTime($schedule->due_date) < new \DateTime()) {
                $daysOverdue = floor((time() - strtotime($schedule->due_date)) / (60 * 60 * 24));
                if ($daysOverdue > 0) {
                    $penalty = $daysOverdue * $unpaidBalance * ($contract->daily_penalty_rate / 100);
                    $schedule->penalty_for_this_month = $penalty;
                    $schedule->status = 'overdue';
                    $schedule->save();
                    $totalPenalty += $penalty;
                } else {
                    $schedule->penalty_for_this_month = 0;
                    $schedule->save();
                }
            } else {
                $schedule->penalty_for_this_month = 0;
                $schedule->save();
            }
        }

        $contract->total_penalty_accumulated = $totalPenalty;
        $contract->save();

        return redirect()->back()->with('message', 'Penya qayta hisoblandi.');
    }

    public function sendWarningSms(Request $request, \App\Models\PaymentSchedule $schedule, EskizService $eskiz)
    {
        $contract = $schedule->contract;
        if (!$contract || !$contract->client || !$contract->client->phone) {
            return redirect()->back()->withErrors(['message' => 'Mijoz telefon raqami topilmadi.']);
        }

        $amount = number_format($schedule->expected_amount - $schedule->paid_amount, 2, ',', ' ');
        $message = "Hurmatli mijoz, sizning shartnomangiz bo'yicha {$amount} so'm to'lov muddati o'tgan. Iltimos to'lovni amalga oshiring.";

        try {
            $eskiz->sendSms($contract->client->phone, $message, 'warning_sms');
            $schedule->last_sms_sent_at = now();
            $schedule->save();
            return redirect()->back()->with('message', 'Ogohlantiruvchi SMS yuborildi.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['message' => 'SMS yuborishda xatolik: ' . $e->getMessage()]);
        }
    }
}
