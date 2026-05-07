<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Lead;
use App\Models\CallLog;
use App\Services\AsteriskAmiService;

class TelephonyController extends Controller
{
    public function triggerCall(Request $request, AsteriskAmiService $amiService)
    {
        $request->validate([
            'lead_id' => 'required|exists:leads,id',
            'phone_number' => 'required|string',
            'worker_extension' => 'nullable|string'
        ]);

        $lead = Lead::findOrFail($request->lead_id);
        
        // Use provided worker extension or fallback to a default (e.g. 100) or user's extension
        $workerExtension = $request->worker_extension ?? '100'; 
        
        $success = $amiService->originateCall($request->phone_number, $workerExtension);

        if ($success) {
            // Log the call attempt
            CallLog::create([
                'client_id' => null, // Assuming lead doesn't map directly to client yet, or handle accordingly
                'worker_id' => auth()->id() ?? 1,
                'phone_number' => $request->phone_number,
                'type' => 'outbound',
                'status' => 'initiated',
                'duration_seconds' => 0,
            ]);

            // Update lead status
            if ($lead->status === 'new') {
                $lead->status = 'contacted';
                $lead->save();
            }

            return response()->json(['message' => 'Call initiated successfully.', 'status' => 'success']);
        }

        return response()->json(['message' => 'Failed to initiate call with Asterisk.', 'status' => 'error'], 500);
    }
}
