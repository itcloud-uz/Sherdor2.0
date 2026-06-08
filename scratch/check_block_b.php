<?php
$pdo = new PDO('sqlite:database/database.sqlite');
$stmt = $pdo->query('
    SELECT b.id as building_id, b.name, b.block_name, b.corpus_name, f.floor_number, f.id as floor_id, f.total_units, f.total_commercial_units
    FROM buildings b
    JOIN floors f ON f.building_id = b.id
    ORDER BY b.name, b.block_name, b.corpus_name, f.floor_number
');
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($results as $row) {
    // Get actual unit counts in database
    $resCount = $pdo->query("SELECT COUNT(*) FROM units WHERE floor_id = {$row['floor_id']} AND type = 'residential'")->fetchColumn();
    $commCount = $pdo->query("SELECT COUNT(*) FROM units WHERE floor_id = {$row['floor_id']} AND type = 'commercial'")->fetchColumn();
    
    // Get unit names
    $unitNames = $pdo->query("SELECT unit_number FROM units WHERE floor_id = {$row['floor_id']} ORDER BY unit_number")->fetchAll(PDO::FETCH_COLUMN);
    $unitNamesStr = implode(', ', $unitNames);

    echo "Building ID {$row['building_id']}: {$row['name']} (Block: {$row['block_name']}, Corpus: {$row['corpus_name']}) - Floor {$row['floor_number']}:\n";
    echo "  Metadata: total_units={$row['total_units']}, total_commercial_units={$row['total_commercial_units']}\n";
    echo "  Actual DB units: res={$resCount}, comm={$commCount}\n";
    echo "  Units list: [{$unitNamesStr}]\n\n";
}
