<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Client;
use App\Models\Unit;
use App\Models\Contract;
use App\Models\Worker;
use App\Models\InventoryItem;
use App\Models\Transaction;

class OperatorController extends Controller
{
    public function index()
    {
        $clients = Client::all();
        $units = Unit::all();
        $contracts = Contract::with(['client', 'unit', 'paymentSchedules'])->get();
        $workers = Worker::with('workLogs')->get();
        $inventory = InventoryItem::with('transactions')->get();
        $transactions = Transaction::latest()->take(100)->get(); // for dashboard stats
        $machineries = \App\Models\Machinery::all();
        $pendingRequests = \App\Models\ApprovalRequest::where('user_id', auth()->id())
                                ->where('status', 'pending')
                                ->get();

        $buildings = \App\Models\Building::with('floors.units')->get();

        return Inertia::render('OperatorDashboard', [
            'clients' => $clients,
            'units' => $units,
            'contracts' => $contracts,
            'workers' => $workers,
            'inventory' => $inventory,
            'transactions' => $transactions,
            'machineries' => $machineries,
            'pendingRequests' => $pendingRequests,
            'buildings' => $buildings,
        ]);
    }

    public function submitRequest(Request $request)
    {
        $request->validate([
            'type' => 'required|string',
            'payload' => 'required',
            'invoice_photo' => 'nullable|image|max:5120' // Max 5MB
        ]);

        $payload = is_string($request->payload) ? json_decode($request->payload, true) : $request->payload;

        if ($request->hasFile('invoice_photo')) {
            $path = $request->file('invoice_photo')->store('invoices', 'public');
            $payload['invoice_photo_path'] = $path;
        }

        if ($request->type === 'hr_biometric') {
            // Avtomat davomat logini yozamiz
            $imageName = null;
            if (!empty($payload['image'])) {
                $image = str_replace('data:image/jpeg;base64,', '', $payload['image']);
                $image = str_replace('data:image/png;base64,', '', $image);
                $image = str_replace(' ', '+', $image);
                $imageName = 'events/' . time() . '_faceid_' . uniqid() . '.jpg';
                \Illuminate\Support\Facades\Storage::disk('public')->put($imageName, base64_decode($image));
            }

            \App\Models\AttendanceLog::create([
                'subject_type' => '\\App\\Models\\Worker',
                'subject_id' => $payload['worker_id'] ?? null,
                'camera_id' => 'Operator Face-ID',
                'type' => 'entry',
                'timestamp' => now(),
                'confidence_score' => 99,
                'proof_image' => $imageName ? '/storage/' . $imageName : null,
            ]);

            return redirect()->back()->with('success', 'Xodim davomati muvaffaqiyatli saqlandi (Keldi).');
        }

        \App\Models\ApprovalRequest::create([
            'user_id' => auth()->id(),
            'type' => $request->type,
            'payload' => $payload,
            'status' => 'pending'
        ]);

        return redirect()->back()->with('success', 'So\'rov muvaffaqiyatli yuborildi!');
    }
}
