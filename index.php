<?php
declare(strict_types=1);

require __DIR__ . '/db.php';

/** @return array<int, string> */
function getRecenzeColumns(PDO $pdo): array
{
    static $cols = null;
    if ($cols !== null) {
        return $cols;
    }

    $stmt = $pdo->query('SHOW COLUMNS FROM recenze');
    $cols = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $cols[] = (string) $column['Field'];
    }

    return $cols;
}

function hasRecenzeColumn(PDO $pdo, string $column): bool
{
    return in_array($column, getRecenzeColumns($pdo), true);
}

function normalizeTyp(string $typ): string
{
    return mb_strtolower(trim($typ), 'UTF-8') === 'hra' ? 'Hra' : 'Anime';
}

$kat = filter_input(INPUT_GET, 'kat', FILTER_UNSAFE_RAW) ?? '';
$q = trim((string) (filter_input(INPUT_GET, 'q', FILTER_UNSAFE_RAW) ?? ''));

$params = [];
$where = [];

if ($kat === 'hry') {
    $where[] = 'typ = :typ_hra';
    $params['typ_hra'] = 'Hra';
} elseif ($kat === 'anime') {
    $where[] = 'typ = :typ_anime';
    $params['typ_anime'] = 'Anime';
}

if ($q !== '') {
    $where[] = '(nazev LIKE :q OR ' . (hasRecenzeColumn($pdo, 'kratky_popis') ? 'kratky_popis' : 'text_recenze') . ' LIKE :q)';
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

$statusStmt = $pdo->query('SELECT aktivita, nazev_dila, typ FROM statusy ORDER BY id DESC LIMIT 1');
$status = $statusStmt ? $statusStmt->fetch(PDO::FETCH_ASSOC) : false;
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ratanovo Doupě | Recenze</title>
    <meta name="description" content="Přehled recenzí na hry a anime na webu Ratanovo Doupě.">
    <style>
        :root { --bg:#fff; --text:#111; --line:#222; --accent:#8B4513; }
        * { box-sizing: border-box; }
        body { margin:0; font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif; background:var(--bg); color:var(--text); line-height:1.5; }
        .wrap { width:min(1200px,92vw); margin:0 auto; }
        header { padding:2rem 0 1rem; border-bottom:2px solid var(--line); }
        nav { padding:1rem 0; border-bottom:2px solid var(--line); display:flex; gap:1rem; flex-wrap:wrap; align-items:center; }
        nav a { color:var(--text); text-decoration:none; border-bottom:2px solid var(--accent); font-weight:600; }
        .statusbar { border:2px solid var(--line); padding:.7rem .9rem; margin:1rem 0; }
        .statusbar strong { color:var(--accent); }
        .filters { border:2px solid var(--line); padding:1rem; display:grid; gap:.8rem; grid-template-columns:1fr auto; margin-bottom:1rem; }
        .filters input, .filters select, .filters button { border:2px solid var(--line); border-radius:0; background:#fff; padding:.55rem .65rem; font:inherit; }
        .filters button { background:var(--accent); color:#fff; font-weight:700; cursor:pointer; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:1rem; }
        article.card { border:2px solid var(--line); overflow:hidden; }
        .card-link { text-decoration:none; color:inherit; display:block; }
        .thumb-wrap { position:relative; border-bottom:2px solid var(--line); aspect-ratio:16/10; overflow:hidden; background:#f5f5f5; }
        .thumb-wrap img { width:100%; height:100%; object-fit:cover; display:block; }
        .score { position:absolute; top:.5rem; right:.5rem; background:#fff; border:2px solid var(--line); color:var(--accent); font-weight:700; padding:.2rem .5rem; }
        .card-body { padding:.9rem; }
        .type { display:inline-block; margin:0 0 .4rem; padding:.1rem .45rem; border:2px solid var(--line); font-size:.8rem; text-transform:uppercase; letter-spacing:.05em; }
        .empty, footer { border-top:2px solid var(--line); padding:1rem 0; margin-top:1rem; }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <h1>Ratanovo Doupě</h1>
        <p>Modern raw recenze her a anime. Bez omáčky, jen čistý verdikt.</p>
    </header>

    <nav aria-label="Hlavní navigace">
        <a href="index.php">Recenze</a>
        <a href="?kat=hry">Hry</a>
        <a href="?kat=anime">Anime</a>
        <a href="admin.php">Administrace</a>
    </nav>

    <?php if ($status): ?>
        <aside class="statusbar" aria-label="Právě hraju nebo sleduju">
            <strong><?= htmlspecialchars((string) $status['aktivita']); ?>:</strong>
            <?= htmlspecialchars((string) $status['nazev_dila']); ?> (<?= normalizeTyp((string) $status['typ']); ?>)
        </aside>
    <?php endif; ?>

    <main>
        <form class="filters" method="get" action="index.php">
            <input type="search" name="q" value="<?= htmlspecialchars($q); ?>" placeholder="Hledat recenzi...">
            <button type="submit">Hledat</button>
            <select name="kat" aria-label="Kategorie">
                <option value="">Všechny kategorie</option>
                <option value="hry" <?= $kat === 'hry' ? 'selected' : ''; ?>>Hry</option>
                <option value="anime" <?= $kat === 'anime' ? 'selected' : ''; ?>>Anime</option>
            </select>
        </form>

        <?php if (!$recenze): ?>
            <p class="empty">Žádné výsledky.</p>
        <?php else: ?>
            <section class="grid" aria-label="Seznam recenzí">
                <?php foreach ($recenze as $item): ?>
                    <article class="card">
                        <a class="card-link" href="detail.php?id=<?= (int) $item['id']; ?>">
                            <div class="thumb-wrap">
                                <img src="<?= htmlspecialchars((string) $item['fotka']); ?>" alt="Náhled recenze <?= htmlspecialchars((string) $item['nazev']); ?>">
                                <span class="score"><?= (int) $item['skore']; ?>%</span>
                            </div>
                            <div class="card-body">
                                <p class="type"><?= htmlspecialchars((string) $item['typ']); ?></p>
                                <h2><?= htmlspecialchars((string) $item['nazev']); ?></h2>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>

    <footer><small>&copy; <?= date('Y'); ?> Ratanovo Doupě</small></footer>
</div>
</body>
</html>
