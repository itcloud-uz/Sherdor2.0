<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AttendanceLog;
use App\Models\User;
use App\Models\Worker;
use App\Models\Machinery;
use App\Models\VisitorDelivery;
use Illuminate\Support\Facades\Storage;

class HardwareEventController extends Controller
{
    /**
     * Handle incoming events from the Python Edge AI service
     * 
     * Request payload:
     * {
     *   "camera_id": "gate_1",
     *   "event_type": "entry", // entry, exit, start_work, stop_work
     *   "subject_class": "worker", // user, worker, machinery, visitor
     *   "subject_id": 45, // ID if known (user/worker/machinery). For visitor, can be null or string plate
     *   "confidence_score": 98.5,
     *   "timestamp": "2026-05-03 08:00:00",
     *   "image_base64": "..." // Snapshot of the event
     * }
     */
    public function logEvent(Request $request)
    {
        $apiKey = $request->bearerToken();
        if (!$apiKey) {
            return response()->json(['success' => false, 'message' => 'API Key required'], 401);
        }

        $keyRecord = \App\Models\HardwareApiKey::where('api_key', $apiKey)->where('is_active', true)->first();
        if (!$keyRecord) {
            return response()->json(['success' => false, 'message' => 'Invalid or inactive API Key'], 401);
        }

        $keyRecord->update(['last_used_at' => now()]);

        $validated = $request->validate([
            'camera_id' => 'required|string',
            'event_type' => 'required|string',
            'subject_class' => 'required|in:user,worker,machinery,visitor',
            'subject_id' => 'nullable',
            'confidence_score' => 'nullable|numeric',
            'timestamp' => 'required|date',
            'image_base64' => 'nullable|string'
        ]);

        $subjectType = null;
        $subjectId = $validated['subject_id'];

        if ($validated['subject_class'] === 'user') {
            $subjectType = User::class;
        } elseif ($validated['subject_class'] === 'worker') {
            $subjectType = Worker::class;
        } elseif ($validated['subject_class'] === 'machinery') {
            // Can lookup by license plate or ID
            if (is_numeric($subjectId)) {
                $subjectType = Machinery::class;
            } else {
                $machinery = Machinery::where('license_plate', $subjectId)->first();
                if ($machinery) {
                    $subjectType = Machinery::class;
                    $subjectId = $machinery->id;
                }
            }
        } elseif ($validated['subject_class'] === 'visitor') {
            // Let's create a visitor record if it doesn't exist
            $plate = $subjectId;
            $visitor = VisitorDelivery::firstOrCreate(
                ['license_plate' => $plate],
                ['type' => 'visitor', 'company_or_name' => 'Unknown']
            );
            $subjectType = VisitorDelivery::class;
            $subjectId = $visitor->id;
        }

        // Save image if provided
        $photoPath = null;
        if (!empty($validated['image_base64'])) {
            $image = str_replace('data:image/jpeg;base64,', '', $validated['image_base64']);
            $image = str_replace('data:image/png;base64,', '', $image);
            $image = str_replace(' ', '+', $image);
            $imageName = 'events/' . time() . '_' . uniqid() . '.jpg';
            Storage::disk('public')->put($imageName, base64_decode($image));
            $photoPath = '/storage/' . $imageName;
        }

        $log = AttendanceLog::create([
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'camera_id' => $validated['camera_id'],
            'type' => $validated['event_type'],
            'timestamp' => $validated['timestamp'],
            'confidence_score' => $validated['confidence_score'] ?? 0,
            'proof_image' => $photoPath,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Event logged successfully',
            'data' => $log
        ]);
    }
}
