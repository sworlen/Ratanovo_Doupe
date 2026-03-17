<?php
declare(strict_types=1);

require __DIR__ . '/db.php';

$stmt = $pdo->query('SELECT id, typ, nazev, fotka, skore FROM recenze ORDER BY id DESC');
$recenze = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ratanovo Doupě | Recenze</title>
    <meta name="description" content="Přehled recenzí na hry a anime na webu Ratanovo Doupě.">
    <style>
        :root {
            --bg: #fff;
            --text: #111;
            --line: #222;
            --accent: #8B4513;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
        }
        header, nav, main, footer { width: min(1200px, 92vw); margin: 0 auto; }
        header { padding: 2rem 0 1rem; border-bottom: 2px solid var(--line); }
        nav { padding: 1rem 0; border-bottom: 2px solid var(--line); }
        nav a { color: var(--text); text-decoration: none; border-bottom: 2px solid var(--accent); font-weight: 600; }
        h1 { margin: 0 0 .5rem; font-size: clamp(1.8rem, 4vw, 2.6rem); }
        .lead { margin: 0; max-width: 60ch; }
        main { padding: 1.5rem 0 2rem; }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1rem;
        }
        article.card {
            border: 2px solid var(--line);
            border-radius: 0;
            overflow: hidden;
            background: #fff;
        }
        .card-link { text-decoration: none; color: inherit; display: block; }
        .thumb-wrap {
            position: relative;
            border-bottom: 2px solid var(--line);
            aspect-ratio: 16/10;
            overflow: hidden;
            background: #f5f5f5;
        }
        .thumb-wrap img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .score {
            position: absolute;
            top: .5rem;
            right: .5rem;
            background: #fff;
            border: 2px solid var(--line);
            color: var(--accent);
            font-weight: 700;
            padding: .2rem .5rem;
            font-size: .95rem;
        }
        .card-body { padding: .9rem; }
        .type {
            display: inline-block;
            margin: 0 0 .4rem;
            padding: .1rem .45rem;
            border: 2px solid var(--line);
            font-size: .8rem;
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        h2 { margin: 0; font-size: 1.2rem; }
        .empty { border: 2px solid var(--line); padding: 1rem; }
        footer { border-top: 2px solid var(--line); padding: 1rem 0 2rem; font-size: .95rem; }
    </style>
</head>
<body>
    <header>
        <h1>Ratanovo Doupě</h1>
        <p class="lead">Modern raw recenze her a anime. Bez omáčky, jen čistý verdikt.</p>
    </header>

    <nav aria-label="Hlavní navigace">
        <a href="index.php">Recenze</a>
    </nav>

    <main>
        <?php if (!$recenze): ?>
            <p class="empty">Zatím tu nejsou žádné recenze.</p>
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

    <footer>
        <small>&copy; <?= date('Y'); ?> Ratanovo Doupě</small>
    </footer>
</body>
</html>
