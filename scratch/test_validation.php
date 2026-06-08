<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rules = [
    'name' => 'required|string|max:255',
    'city' => 'nullable|string|max:255', // Tuman/Shahar
    'district' => 'nullable|string|max:255', // Viloyat
    'mahalla' => 'nullable|string|max:255',
    'street' => 'nullable|string|max:255',
    'block_name' => 'nullable|string|max:255',
    'corpus_name' => 'nullable|string|max:255',
    'length_m' => 'nullable|numeric|min:0',
    'width_m' => 'nullable|numeric|min:0',
    'height_m' => 'nullable|numeric|min:0',
    'total_floors' => 'nullable|integer|min:0',
    'blocks_count' => 'nullable|integer|min:1',
    'entrances_count' => 'nullable|integer|min:1',
    'total_apartments' => 'nullable|integer|min:0',
    'total_shops' => 'nullable|integer|min:0',
    'estimated_budget' => 'nullable|numeric|min:0',
    'blueprint' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
    'photo' => 'nullable|file|mimes:jpg,jpeg,png|max:10240',
    'model_3d' => 'nullable|file|max:51200',
    'basement_floors' => 'nullable|integer|min:0',
    'is_object_only' => 'nullable|boolean',
];

$payload = [
    'name' => 'Sherdor TJM',
    'city' => 'Urgut',
    'district' => 'Samarqand',
    'mahalla' => 'Do\'stlik',
    'street' => 'Navoiy Ko\'chasi 83',
    'total_floors' => 1,
    'blocks_count' => 1,
    'entrances_count' => 1,
    'total_apartments' => 0,
    'total_shops' => 0,
    'estimated_budget' => 0,
    'blueprint' => null,
    'model_3d' => null,
    'basement_floors' => 0,
    'photo' => null,
    'is_object_only' => 1,
];

$validator = \Illuminate\Support\Facades\Validator::make($payload, $rules);
if ($validator->passes()) {
    echo "SUCCESS: Validation passed.\n";
} else {
    echo "ERROR: Validation failed.\n";
    print_r($validator->errors()->all());
}
