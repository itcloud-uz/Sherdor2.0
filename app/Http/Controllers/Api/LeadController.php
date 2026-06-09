<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LeadController extends Controller
{
    /**
     * Store a newly created lead in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|string|email|max:255',
            'social_url' => 'nullable|string|max:1000',
            'source' => 'nullable|string|max:255',
            'user_id' => 'nullable|exists:users,id',
        ]);

        try {
            $source = $validated['source'] ?? 'landing_page';
            
            // Override source if authenticated user is submitting it manually
            if ((auth()->check() && $request->is('admin/*') || $request->has('is_manual')) && empty($validated['source'])) {
                $source = 'manual';
            }

            $lead = Lead::create([
                'full_name' => $validated['full_name'],
                'phone' => $validated['phone'],
                'email' => $validated['email'] ?? null,
                'social_url' => $validated['social_url'] ?? null,
                'source' => $source,
                'status' => 'new',
                'user_id' => $validated['user_id'] ?? null,
            ]);

            return response()->json([
                'message' => 'Rahmat! Menejerimiz tez orada aloqaga chiqadi',
                'lead' => $lead
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Lead generation error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Xatolik yuz berdi. Iltimos keyinroq qayta urinib ko\'ring.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
