<?php
// export_firebase.php — regenerates firebase_export.json with all tables
// Run via: http://localhost/expedia/export_firebase.php
// Then run: node firebase_import.js
require_once __DIR__ . '/includes/config.php';

// Ensure support_tickets table exists before exporting
db()->exec("CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `name` varchar(160) NOT NULL,
  `email` varchar(180) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `category` enum('booking','payment','account','technical','partnership','other') NOT NULL DEFAULT 'other',
  `message` text NOT NULL,
  `status` enum('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `admin_reply` text DEFAULT NULL,
  `replied_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$tables = ['users','locations','hotels','rooms','bookings','reviews','hotel_applications','support_tickets'];
$export = [];

foreach ($tables as $table) {
    try {
        $rows = db()->query("SELECT * FROM `$table`")->fetchAll();
        $export[$table] = [];
        foreach ($rows as $row) {
            $id = $row['id'] ?? null;
            if ($id !== null) {
                // Sanitize: remove null bytes, cast ints/floats properly
                $clean = [];
                foreach ($row as $k => $v) {
                    if ($v === null) { $clean[$k] = null; }
                    elseif (is_numeric($v) && !str_contains((string)$v, '.')) { $clean[$k] = (int)$v; }
                    elseif (is_numeric($v)) { $clean[$k] = (float)$v; }
                    else { $clean[$k] = $v; }
                }
                $export[$table][(string)$id] = $clean;
            }
        }
    } catch (Exception $e) {
        $export[$table] = [];
    }
}

$json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
file_put_contents(__DIR__ . '/firebase_export.json', $json);

echo '<pre style="font-family:monospace;padding:20px;background:#0d1117;color:#e6edf3;border-radius:8px;margin:20px">';
echo "<strong style='color:#58a6ff'>Firebase Export Complete ✅</strong>\n\n";
foreach ($tables as $t) {
    $cnt = count($export[$t]);
    $icon = $cnt > 0 ? '✓' : '⚠';
    $color = $cnt > 0 ? '#3fb950' : '#d29922';
    echo "<span style='color:$color'>$icon</span>  <strong>$t</strong>: $cnt record" . ($cnt !== 1 ? 's' : '') . "\n";
}
echo "\n<strong style='color:#58a6ff'>firebase_export.json updated!</strong>\n";
echo "\nNext step — run in your terminal:\n";
echo "  <span style='color:#f0883e'>node firebase_import.js</span>\n";
echo '</pre>';
