<?php
declare(strict_types=1);

session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/common.php';

$newsletter = handleNewsletterSubmit($pdo);
$sort = (string) (filter_input(INPUT_GET, 'sort', FILTER_UNSAFE_RAW) ?? 'date');
$orderBy = $sort === 'az' ? 'nazev ASC' : 'id DESC';

$stmt = $pdo->query('SELECT id, nazev, typ, skore FROM recenze ORDER BY ' . $orderBy);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Archiv | Ratanovo Doupě</title>
<style>
:root { --line:#222; --accent:#8B4513; }
body { margin:0; font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif; }
.wrap { width:min(1000px,92vw); margin:0 auto; }
header, nav, footer { border-bottom:2px solid var(--line); padding:1rem 0; }
a { color:#111; text-decoration:none; border-bottom:2px solid var(--accent); }
table { width:100%; border-collapse:collapse; margin:1rem 0; }
th,td { border:2px solid var(--line); padding:.6rem; text-align:left; }
footer input,footer button{ border:2px solid var(--line); border-radius:0; padding:.55rem; }
footer button{ background:var(--accent); color:#fff; }
</style>
</head>
<body>
<div class="wrap">
<header><h1>Ratanův Archiv</h1></header>
<nav><a href="index.php">Recenze</a> | <a href="archiv.php?sort=az">A-Z</a> | <a href="archiv.php?sort=date">Nejnovější</a></nav>
<main>
<table>
<thead><tr><th>Název</th><th>Typ</th><th>Skóre</th></tr></thead>
<tbody>
<?php foreach ($rows as $row): ?>
<tr>
<td><a href="detail.php?id=<?= (int) $row['id']; ?>"><?= e((string) $row['nazev']); ?></a></td>
<td><?= e((string) $row['typ']); ?></td>
<td><?= (int) $row['skore']; ?>%</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</main>
<footer>
<?php if ($newsletter['ok']): ?><p><?= e($newsletter['ok']); ?></p><?php endif; ?>
<?php if ($newsletter['error']): ?><p><?= e($newsletter['error']); ?></p><?php endif; ?>
<form method="post"><input type="email" name="newsletter_email" required><button type="submit" name="newsletter_submit" value="1">Newsletter</button></form>
</footer>
</div>
</body>
</html>
