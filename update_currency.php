<?php
$dir = new RecursiveDirectoryIterator('resources/js/Pages');
$ite = new RecursiveIteratorIterator($dir);
foreach($ite as $file) {
    if ($file->getExtension() === 'vue') {
        $content = file_get_contents($file->getPathname());
        
        // Match ${ ... } where it's used for currency like ${{ Number(x).toLocaleString() }}
        // The regex captures the variable inside
        $updated = preg_replace('/\\$\\{\\{\\s*(?:Number\\()?([^}]+?)(?:\\)?\\.toLocaleString\\(\\))?\\s*\\}\\}/', '{{ $currency($1) }}', $content);
        
        if ($content !== $updated) {
            file_put_contents($file->getPathname(), $updated);
            echo 'Updated ' . $file->getPathname() . PHP_EOL;
        }
    }
}
echo "Done\n";
