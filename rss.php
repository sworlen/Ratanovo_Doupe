<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/common.php';

header('Content-Type: application/rss+xml; charset=UTF-8');

$items = [];
if (hasTable($pdo, 'recenze')) {
    try {
        $columns = tableColumns($pdo, 'recenze');
        $select = ['id', 'nazev'];
        if (in_array('kratky_popis', $columns, true)) {
            $select[] = 'kratky_popis';
        }
        if (in_array('text_recenze', $columns, true)) {
            $select[] = 'text_recenze';
        }

        $stmt = $pdo->query('SELECT ' . implode(', ', $select) . ' FROM recenze ORDER BY id DESC LIMIT 10');
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $items = [];
    }
}

$base = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
?>
<rss version="2.0">
<channel>
    <title>Ratanovo Doupě - RSS</title>
    <link><?= e($base . '/index.php'); ?></link>
    <description>Posledních 10 recenzí</description>
    <language>cs-cz</language>
    <?php foreach ($items as $item): ?>
        <item>
            <title><?= e((string) ($item['nazev'] ?? 'Bez názvu')); ?></title>
            <link><?= e($base . '/detail.php?id=' . (int) ($item['id'] ?? 0)); ?></link>
            <guid><?= e($base . '/detail.php?id=' . (int) ($item['id'] ?? 0)); ?></guid>
            <description><?= e((string) ((($item['kratky_popis'] ?? '') !== '') ? $item['kratky_popis'] : snippet((string) ($item['text_recenze'] ?? ''), 180))); ?></description>
        </item>
    <?php endforeach; ?>
</channel>
</rss>
