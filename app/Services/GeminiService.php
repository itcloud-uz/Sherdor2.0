<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY', '');
    }

    public function chat(string $prompt): string
    {
        if (empty($this->apiKey)) {
            return "Error: Gemini API Key is missing. Please configure GEMINI_API_KEY in .env.";
        }

        $systemInstruction = "You are the 'Sher-Dor ERP System Assistant'. You help the CEO/Admin manage their construction and real estate business. Be concise, professional, and slightly futuristic in tone. Respond in the language the user speaks.";

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $systemInstruction . "\n\nUser: " . $prompt]
                    ]
                ]
            ]
        ];

        try {
            $response = Http::withoutVerifying()->post($this->baseUrl . '?key=' . $this->apiKey, $payload);

            if ($response->successful()) {
                $data = $response->json();
                return $data['candidates'][0]['content']['parts'][0]['text'] ?? "I could not generate a response.";
            }

            Log::error('Gemini API Error: ' . $response->body());
            return "Error communicating with AI Service. " . $response->status();

        } catch (\Exception $e) {
            Log::error('Gemini Exception: ' . $e->getMessage());
            return "Internal error while contacting AI Service.";
        }
    }
}
