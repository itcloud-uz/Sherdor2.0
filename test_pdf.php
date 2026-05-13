<?php
try { 
    $res = app(App\Http\Controllers\Admin\ReportExportController::class)->exportExpensesReport(request()); 
    $content = $res->getContent(); 
    echo strlen($content) > 0 ? 'Success' : 'Empty'; 
} catch (\Exception $e) { 
    echo $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine(); 
}
