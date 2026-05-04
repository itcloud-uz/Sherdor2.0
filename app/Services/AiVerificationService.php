<?php

namespace App\Services;

class AiVerificationService
{
    /**
     * Simulate an OpenCV/Python Microservice processing a PDF for signatures/seals.
     *
     * @param string $filePath
     * @return array
     */
    public function verifyDocument(string $filePath): array
    {
        $pythonPath = 'python'; // or full path to python executable if needed
        $scriptPath = base_path('scripts/ai_verify_signature.py');
        
        $escapedFilePath = escapeshellarg($filePath);
        $command = "{$pythonPath} \"{$scriptPath}\" {$escapedFilePath}";
        
        $output = shell_exec($command);
        
        if ($output) {
            try {
                $result = json_decode($output, true);
                if (isset($result['status'])) {
                    return $result;
                }
            } catch (\Exception $e) {
                // fallback
            }
        }
        
        return [
            'status' => 'failed',
            'logs' => [
                'scan_timestamp' => now()->toIso8601String(),
                'file_processed' => basename($filePath),
                'seal_detection' => [
                    'detected' => false,
                    'confidence' => 0,
                    'error' => 'Tizim xatoligi yoki Python skript ishlamadi.'
                ],
                'signature_detection' => [
                    'buyer_signature' => false,
                    'confidence' => 0,
                    'error' => 'Tizim xatoligi yoki Python skript ishlamadi.'
                ],
                'final_decision' => 'REJECTED'
            ]
        ];
    }
}
