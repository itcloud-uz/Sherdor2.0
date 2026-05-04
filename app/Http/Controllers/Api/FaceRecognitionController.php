<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\User;
use App\Models\AttendanceLog;

class FaceRecognitionController extends Controller
{
    public function log(Request $request)
    {
        $validated = $request->validate([
            'face_id' => 'required|string',
            'camera_id' => 'required|string',
            'timestamp' => 'required|date',
            'type' => 'required|in:entry,exit',
            'confidence_score' => 'numeric|min:0|max:100',
        ]);

        $user = User::where('face_encoding_id', $validated['face_id'])->first();

        if (!$user) {
            return response()->json(['error' => 'User not found for given face_id'], 404);
        }

        $log = AttendanceLog::create([
            'user_id' => $user->id,
            'camera_id' => $validated['camera_id'],
            'type' => $validated['type'],
            'timestamp' => $validated['timestamp'],
            'confidence_score' => $validated['confidence_score'] ?? null,
        ]);

        if ($validated['type'] === 'entry') {
            $user->update(['last_entry' => $validated['timestamp']]);
        } else {
            $user->update(['last_exit' => $validated['timestamp']]);
        }

        return response()->json([
            'message' => 'Attendance logged successfully',
            'log' => $log,
            'user' => $user->name,
        ], 201);
    }
}
