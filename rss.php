<?php
declare(strict_types=1);

require __DIR__ . '/db.php';

header('Content-Type: application/rss+xml; charset=UTF-8');

$stmt = $pdo->query('SELECT id, nazev, kratky_popis, text_recenze FROM recenze ORDER BY id DESC LIMIT 10');
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$base = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
?>
<rss version="2.0">
<channel>
    <title>Ratanovo Doupě - RSS</title>
    <link><?= htmlspecialchars($base . '/index.php', ENT_QUOTES, 'UTF-8'); ?></link>
    <description>Posledních 10 recenzí</description>
    <language>cs-cz</language>
    <?php foreach ($items as $item): ?>
        <item>
            <title><?= htmlspecialchars((string) $item['nazev'], ENT_QUOTES, 'UTF-8'); ?></title>
            <link><?= htmlspecialchars($base . '/detail.php?id=' . (int) $item['id'], ENT_QUOTES, 'UTF-8'); ?></link>
            <guid><?= htmlspecialchars($base . '/detail.php?id=' . (int) $item['id'], ENT_QUOTES, 'UTF-8'); ?></guid>
            <description><?= htmlspecialchars((string) (($item['kratky_popis'] ?? '') !== '' ? $item['kratky_popis'] : mb_substr((string) ($item['text_recenze'] ?? ''), 0, 180)), ENT_QUOTES, 'UTF-8'); ?></description>
        </item>
    <?php endforeach; ?>
</channel>
</rss>
