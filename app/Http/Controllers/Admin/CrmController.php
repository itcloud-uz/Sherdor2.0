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
        $leads = Lead::all();
        $clients = \App\Models\Client::with('contracts.unit.floor.building')->get();
        $contracts = Contract::with(['client', 'unit', 'paymentSchedules'])->get();
        $units = \App\Models\Unit::with('floor.building')->get();
        $overdueSchedules = \App\Models\PaymentSchedule::with('contract.client')
                            ->where('status', 'overdue')
                            ->orderBy('due_date', 'asc')
                            ->get();

        return Inertia::render('Admin/CRM/Index', [
            'leads' => $leads,
            'clients' => $clients,
            'contracts' => $contracts,
            'units' => $units,
            'overdueSchedules' => $overdueSchedules,
        ]);
    }

    public function storeClient(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'passport_series' => 'nullable|string',
            'pinfl' => 'nullable|string',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'passport_issued_date' => 'nullable|date',
            'passport_issued_by' => 'nullable|string',
            'created_at' => 'nullable|date'
        ]);

        $client = new \App\Models\Client($validated);
        if (!empty($validated['created_at'])) {
            $client->created_at = $validated['created_at'];
        }
        $client->save();

        return redirect()->back()->with('message', 'Client created successfully.');
    }

    public function updateClient(\Illuminate\Http\Request $request, \App\Models\Client $client)
    {
        $validated = $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'passport_series' => 'nullable|string',
            'pinfl' => 'nullable|string',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'passport_issued_date' => 'nullable|date',
            'passport_issued_by' => 'nullable|string',
            'created_at' => 'nullable|date'
        ]);

        $client->fill($validated);
        if (!empty($validated['created_at'])) {
            $client->created_at = $validated['created_at'];
        }
        $client->save();

        return redirect()->back()->with('message', 'Client updated successfully.');
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
            'paid_amount' => 'required|numeric|min:0'
        ]);

        $contract = $schedule->contract;
        
        $schedule->paid_amount = $validated['paid_amount'];
        $schedule->remaining_debt = max(0, $schedule->expected_amount - $schedule->paid_amount);
        
        if ($schedule->paid_amount >= $schedule->expected_amount) {
            $schedule->status = 'paid';
            $schedule->penalty_for_this_month = 0; // Clear penalty if paid
        } elseif ($schedule->paid_amount > 0) {
            $schedule->status = 'partial';
            // Calculate penalty if partial and overdue
            if (new \DateTime($schedule->due_date) < new \DateTime()) {
                $daysOverdue = floor((time() - strtotime($schedule->due_date)) / (60 * 60 * 24));
                if ($daysOverdue > 0 && $contract) {
                    $schedule->penalty_for_this_month = $daysOverdue * $schedule->expected_amount * ($contract->daily_penalty_rate / 100);
                }
            } else {
                $schedule->penalty_for_this_month = 0;
            }
        } else {
            $schedule->status = 'pending';
            // Re-evaluate if it's overdue
            if (new \DateTime($schedule->due_date) < new \DateTime()) {
                $schedule->status = 'overdue';
                $daysOverdue = floor((time() - strtotime($schedule->due_date)) / (60 * 60 * 24));
                if ($daysOverdue > 0 && $contract) {
                    $schedule->penalty_for_this_month = $daysOverdue * $schedule->expected_amount * ($contract->daily_penalty_rate / 100);
                }
            } else {
                $schedule->penalty_for_this_month = 0;
            }
        }
        $schedule->save();

        if ($contract) {
            // Update total accumulated penalty
            $totalPenalty = $contract->paymentSchedules()->sum('penalty_for_this_month');
            $contract->total_penalty_accumulated = $totalPenalty;
            $contract->save();
        }
        if ($contract) {
            $totalPaid = $contract->paymentSchedules()->sum('paid_amount');
            // Assuming we only need to reflect changes on the schedule, but if contract has total_paid field, update it.
            // Currently, contract doesn't have total_paid field based on schema, but we can return success anyway.
        }

        return redirect()->back()->with('message', 'To\'lov muvaffaqiyatli saqlandi va ma\'lumotlar yangilandi.');
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
            if ($schedule->status !== 'paid' && new \DateTime($schedule->due_date) < new \DateTime()) {
                $daysOverdue = floor((time() - strtotime($schedule->due_date)) / (60 * 60 * 24));
                if ($daysOverdue > 0) {
                    $penalty = $daysOverdue * $schedule->expected_amount * ($contract->daily_penalty_rate / 100);
                    $schedule->penalty_for_this_month = $penalty;
                    $schedule->status = 'overdue';
                    $schedule->save();
                    $totalPenalty += $penalty;
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
}
