<?php
require dirname(__DIR__) . '/vendor/autoload.php';
$db = new Application\Database\Database('/var/www/html/data/app.db');

echo "=== Indexes on reports ===\n";
$rows = $db->fetchAll("SELECT name, sql FROM sqlite_master WHERE type='index' AND tbl_name='reports'");
foreach ($rows as $r) {
    echo $r['name'] . ": " . $r['sql'] . "\n";
}

echo "\n=== Duplicate dates in reports ===\n";
$rows = $db->fetchAll("SELECT date, COUNT(*) as cnt FROM reports GROUP BY date HAVING cnt > 1");
foreach ($rows as $r) {
    echo "date=" . $r['date'] . " count=" . $r['cnt'] . "\n";
}
echo "Total rows: " . $db->fetchScalar("SELECT COUNT(*) FROM reports") . "\n";
