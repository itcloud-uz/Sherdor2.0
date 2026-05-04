<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConsensusRequest;
use App\Models\ConsensusVote;
use App\Models\Transaction;
use App\Models\Partner;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PartnerController extends Controller
{
    public function index()
    {
        $partner = Partner::where('user_id', auth()->id())->first();

        // If the user is an admin but not a partner, maybe just show all requests for debugging
        $partnerId = $partner ? $partner->id : null;

        $requests = ConsensusRequest::with([
            'transaction.creator',
            'votes.partner.user'
        ])->where('status', 'pending')->get();
        
        $transactions = Transaction::with('creator')->latest()->take(50)->get();

        return Inertia::render('PartnerDashboard', [
            'partner' => $partner,
            'requests' => $requests,
            'transactions' => $transactions,
            'companyNetWorth' => \App\Models\CompanyBalance::first()->total_balance ?? 0
        ]);
    }
    
    public function storeTransaction(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'type' => 'required|in:income,expense',
            'category' => 'required|string',
            'notes' => 'nullable|string',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240'
        ]);
        
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('receipts', 'public');
        }

        $partner = Partner::where('user_id', auth()->id())->first();

        // Create the transaction as pending (requires consensus)
        $transaction = Transaction::create([
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'currency' => 'UZS',
            'category' => $validated['category'],
            'status' => 'pending',
            'notes' => $validated['notes'],
            'attachment_path' => $attachmentPath,
            'created_by' => auth()->id(),
            'partner_id' => $partner ? $partner->id : null,
            'transaction_date' => now(),
        ]);
        
        // Create a consensus request
        $consensusRequest = ConsensusRequest::create([
            'transaction_id' => $transaction->id,
            'status' => 'pending',
            'required_votes' => Partner::count(),
        ]);
        
        // Generate votes for all partners
        $partners = Partner::all();
        foreach ($partners as $p) {
            ConsensusVote::create([
                'consensus_request_id' => $consensusRequest->id,
                'partner_id' => $p->id,
                'vote' => ($partner && $p->id === $partner->id) ? 'approved' : 'pending',
            ]);
        }
        
        // Check if consensus is immediately reached (if there is only 1 partner)
        if (Partner::count() <= 1 && $partner) {
            $consensusRequest->status = 'resolved_approved';
            $consensusRequest->save();

            $transaction->status = 'approved';
            $transaction->receipt_hash = 'MULTI-SIG-' . strtoupper(uniqid()) . '-' . $transaction->id;
            $transaction->save();

            if ($transaction->type === 'income') {
                \App\Models\CompanyBalance::addIncome($transaction->amount);
            } else {
                \App\Models\CompanyBalance::addExpense($transaction->amount);
            }
        }

        return redirect()->back()->with('message', 'Tranzaksiya muvaffaqiyatli yuklandi va tasdiqlash uchun yuborildi.');
    }

    public function vote(Request $request, ConsensusVote $vote)
    {
        $validated = $request->validate([
            'vote' => 'required|in:approved,rejected'
        ]);

        $partner = Partner::where('user_id', auth()->id())->first();

        // Ensure the vote belongs to the logged-in partner
        if (!$partner || $vote->partner_id !== $partner->id) {
            return response()->json(['error' => 'Unauthorized vote.'], 403);
        }

        if ($vote->vote !== 'pending') {
            return response()->json(['error' => 'Vote already cast.'], 400);
        }

        $vote->vote = $validated['vote'];
        $vote->save();

        $consensusRequest = $vote->request;

        // If rejected, the whole request immediately fails
        if ($validated['vote'] === 'rejected') {
            $consensusRequest->status = 'resolved_rejected';
            $consensusRequest->save();

            $transaction = $consensusRequest->transaction;
            $transaction->status = 'rejected';
            $transaction->save();

            return redirect()->back()->with('message', 'Consensus rejected.');
        }

        // If approved, check if ALL votes are approved
        $allVotes = $consensusRequest->votes;
        $allApproved = true;

        foreach ($allVotes as $v) {
            if ($v->vote !== 'approved') {
                $allApproved = false;
                break;
            }
        }

        if ($allApproved) {
            $consensusRequest->status = 'resolved_approved';
            $consensusRequest->save();

            $transaction = $consensusRequest->transaction;
            $transaction->status = 'approved';
            $transaction->receipt_hash = 'MULTI-SIG-' . strtoupper(uniqid()) . '-' . $transaction->id;
            $transaction->save();

            if ($transaction->type === 'income') {
                \App\Models\CompanyBalance::addIncome($transaction->amount);
            } else {
                \App\Models\CompanyBalance::addExpense($transaction->amount);
            }
            
            return redirect()->back()->with('message', '100% Consensus Reached. Transaction executed.');
        }

        return redirect()->back()->with('message', 'Vote registered. Awaiting other partners.');
    }
}
