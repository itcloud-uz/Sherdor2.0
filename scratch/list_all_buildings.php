<?php
$pdo = new PDO('sqlite:database/database.sqlite');
$stmt = $pdo->query('SELECT id, name, block_name, corpus_name, total_floors, basement_floors FROM buildings');
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($results, JSON_PRETTY_PRINT) . "\n";
