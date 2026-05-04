<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Contract;
use Inertia\Inertia;

class CrmController extends Controller
{
    public function index()
    {
        $leads = Lead::all();
        $clients = \App\Models\Client::all();
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
        ]);

        \App\Models\Client::create($validated);
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
        ]);

        $client->update($validated);
        return redirect()->back()->with('message', 'Client updated successfully.');
    }

    public function deleteClient(\App\Models\Client $client)
    {
        $client->delete();
        return redirect()->back()->with('message', 'Client deleted successfully.');
    }
}
