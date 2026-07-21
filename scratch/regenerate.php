<?php
use App\Models\ContractTemplate;
use Illuminate\Support\Facades\Storage;

foreach (ContractTemplate::all() as $t) {
    if ($t->docx_path && Storage::disk('local')->exists($t->docx_path)) {
        $abs = Storage::disk('local')->path($t->docx_path);
        try {
            $previewData = json_decode(ContractTemplate::runPython("preview \"{$abs}\""), true);
            if (isset($previewData['html'])) {
                $t->update(['preview_html' => $previewData['html']]);
                echo "Updated ID {$t->id}\n";
            } else {
                echo "No HTML generated for ID {$t->id}\n";
            }
        } catch (\Exception $e) {
            echo "Error ID {$t->id}: {$e->getMessage()}\n";
        }
    }
}