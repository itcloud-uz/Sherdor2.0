<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

// Create a dummy image with valid PNG signature
$tempFile = tempnam(sys_get_temp_dir(), 'test_img');
file_put_contents($tempFile, "\x89PNG\r\n\x1a\n\x00\x00\x00\x0dIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\nIDATx\x9cc\x00\x01\x00\x00\x05\x00\x01\r\n-\xb4\x00\x00\x00\x00IEND\xaeB`\x82");

$uploadedFile = new UploadedFile(
    $tempFile,
    'FASAD-1.png',
    'image/png',
    null,
    true // test mode
);

$rules = [
    'photo' => 'nullable|file|mimes:jpg,jpeg,png|max:10240',
];

$payload = [
    'photo' => $uploadedFile,
];

$validator = Validator::make($payload, $rules);

if ($validator->passes()) {
    echo "SUCCESS: File validation passed.\n";
} else {
    echo "ERROR: File validation failed.\n";
    print_r($validator->errors()->all());
}

@unlink($tempFile);
