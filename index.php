<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/common.php';

$newsletter = handleNewsletterSubmit($pdo);
$kat = (string) (filter_input(INPUT_GET, 'kat', FILTER_UNSAFE_RAW) ?? '');
$q = trim((string) (filter_input(INPUT_GET, 'q', FILTER_UNSAFE_RAW) ?? ''));

$recenze = [];
$status = false;
$pageError = '';

if (!hasTable($pdo, 'recenze')) {
    $pageError = 'Chybí tabulka recenze.';
} else {
    try {
        $columns = tableColumns($pdo, 'recenze');
        $searchColumn = in_array('kratky_popis', $columns, true) ? 'kratky_popis' : 'text_recenze';

        $params = [];
        $where = [];

        if ($kat === 'hry') {
            $where[] = 'typ = :typ_hra';
            $params['typ_hra'] = 'Hra';
        } elseif ($kat === 'anime') {
            $where[] = 'typ = :typ_anime';
            $params['typ_anime'] = 'Anime';
        }

        if ($q !== '' && in_array($searchColumn, $columns, true)) {
            $where[] = "(nazev LIKE :q OR {$searchColumn} LIKE :q)";
            $params['q'] = '%' . $q . '%';
        }

        $sql = 'SELECT id, typ, nazev, fotka, skore FROM recenze';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY id DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $recenze = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $pageError = appError('Nepodařilo se načíst recenze.');
    }
}

if (hasTable($pdo, 'statusy')) {
    try {
        $statusStmt = $pdo->query('SELECT aktivita, nazev_dila, typ FROM statusy ORDER BY id DESC LIMIT 1');
        $status = $statusStmt ? $statusStmt->fetch(PDO::FETCH_ASSOC) : false;
    } catch (Throwable $e) {
        $status = false;
    }
}
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ratanovo Doupě | Recenze</title>
    <style>
        :root { --bg:#fff; --text:#111; --line:#222; --accent:#8B4513; }
        * { box-sizing: border-box; }
        body { margin:0; font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif; background:var(--bg); color:var(--text); line-height:1.5; }
        .wrap { width:min(1200px,92vw); margin:0 auto; }
        header, nav, footer { border-bottom:2px solid var(--line); padding:1rem 0; }
        nav { display:flex; gap:1rem; flex-wrap:wrap; }
        nav a { color:var(--text); text-decoration:none; border-bottom:2px solid var(--accent); font-weight:600; }
        .statusbar, .filters, .msg { border:2px solid var(--line); padding:.8rem; margin:1rem 0; }
        .filters { display:grid; gap:.8rem; grid-template-columns:1fr auto auto; }
        .filters input, .filters select, .filters button, footer input, footer button { border:2px solid var(--line); border-radius:0; background:#fff; padding:.55rem .65rem; font:inherit; }
        .filters button, footer button { background:var(--accent); color:#fff; font-weight:700; cursor:pointer; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:1rem; }
        article.card { border:2px solid var(--line); overflow:hidden; }
        .card-link { text-decoration:none; color:inherit; display:block; }
        .thumb-wrap { position:relative; border-bottom:2px solid var(--line); aspect-ratio:16/10; overflow:hidden; }
        .thumb-wrap img { width:100%; height:100%; object-fit:cover; display:block; }
        .score { position:absolute; top:.5rem; right:.5rem; background:#fff; border:2px solid var(--line); color:var(--accent); font-weight:700; padding:.2rem .5rem; }
        .card-body { padding:.9rem; }
        .type { display:inline-block; margin:0 0 .4rem; padding:.1rem .45rem; border:2px solid var(--line); font-size:.8rem; text-transform:uppercase; }
        footer form { display:flex; gap:.6rem; flex-wrap:wrap; align-items:center; }
    </style>
</head>
<body>
<div class="wrap">
    <header><h1>Ratanovo Doupě</h1></header>
    <nav>
        <a href="index.php">Recenze</a>
        <a href="archiv.php">Archiv</a>
        <a href="rss.php">RSS</a>
        <a href="admin.php">Administrace</a>
    </nav>

    <?php if ($status): ?>
        <aside class="statusbar"><strong><?= e((string) $status['aktivita']); ?>:</strong> <?= e((string) $status['nazev_dila']); ?> (<?= e((string) $status['typ']); ?>)</aside>
    <?php endif; ?>

    <?php if ($pageError): ?><p class="msg"><?= e($pageError); ?></p><?php endif; ?>

    <main>
        <form class="filters" method="get" action="index.php">
            <input type="search" name="q" value="<?= e($q); ?>" placeholder="Hledat recenzi...">
            <select name="kat">
                <option value="">Všechny kategorie</option>
                <option value="hry" <?= $kat === 'hry' ? 'selected' : ''; ?>>Hry</option>
                <option value="anime" <?= $kat === 'anime' ? 'selected' : ''; ?>>Anime</option>
            </select>
            <button type="submit">Filtrovat</button>
        </form>

        <section class="grid">
            <?php foreach ($recenze as $item): ?>
                <article class="card">
                    <a class="card-link" href="detail.php?id=<?= (int) ($item['id'] ?? 0); ?>">
                        <div class="thumb-wrap">
                            <img src="<?= e((string) ($item['fotka'] ?? '')); ?>" alt="<?= e((string) ($item['nazev'] ?? '')); ?>">
                            <span class="score"><?= (int) ($item['skore'] ?? 0); ?>%</span>
                        </div>
                        <div class="card-body">
                            <p class="type"><?= e((string) ($item['typ'] ?? '')); ?></p>
                            <h2><?= e((string) ($item['nazev'] ?? '')); ?></h2>
                        </div>
                    </a>
                </article>
            <?php endforeach; ?>
        </section>
    </main>

    <footer>
        <?php if ($newsletter['ok']): ?><p class="msg"><?= e($newsletter['ok']); ?></p><?php endif; ?>
        <?php if ($newsletter['error']): ?><p class="msg"><?= e($newsletter['error']); ?></p><?php endif; ?>
        <form method="post">
            <label for="newsletter_email">Newsletter:</label>
            <input id="newsletter_email" type="email" name="newsletter_email" required placeholder="tvuj@email.cz">
            <button type="submit" name="newsletter_submit" value="1">Odebírat</button>
        </form>
    </footer>
</div>
</body>
</html>
