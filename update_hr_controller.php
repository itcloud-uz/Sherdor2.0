<?php
$file = 'd:/Sherdor 2.0/app/Http/Controllers/Admin/HrController.php';
$content = file_get_contents($file);

// Replace target_type and target_id with worker_id, machinery_id, etc.
// But first, let's just do a string replacement for the specific methods.
// Actually, it's easier to use a regex or do it manually if there are only a few.

// Let's output the exact lines to see where they are.
$lines = explode("\n", $content);
foreach ($lines as $i => $line) {
    if (strpos($line, 'target_type') !== false) {
        echo "Line " . ($i+1) . ": " . $line . "\n";
    }
}
