<?php
$pdo = new PDO('sqlite:database/database.sqlite');
$stmt = $pdo->query('
    SELECT b.name, b.block_name, f.floor_number, f.id as floor_id, u.unit_number, u.type, u.id as unit_id
    FROM buildings b
    JOIN floors f ON f.building_id = b.id
    JOIN units u ON u.floor_id = f.id
    ORDER BY b.name, b.block_name, f.floor_number, u.unit_number
');
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Count of units: " . count($results) . "\n";
foreach (array_slice($results, 0, 50) as $row) {
    echo "{$row['name']} ({$row['block_name']}) - Floor {$row['floor_number']}: {$row['unit_number']} ({$row['type']})\n";
}
