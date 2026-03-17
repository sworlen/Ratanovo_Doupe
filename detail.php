<?php
declare(strict_types=1);

require __DIR__ . '/db.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    http_response_code(400);
    exit('Neplatné ID recenze.');
}

$stmt = $pdo->prepare('SELECT id, typ, nazev, fotka, skore, youtube_url, text_recenze, plusy, minusy, verdikt, hodnoceni FROM recenze WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$recenze = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$recenze) {
    http_response_code(404);
    exit('Recenze nebyla nalezena.');
}

$plusy = array_filter(array_map('trim', explode("\n", (string) $recenze['plusy'])));
$minusy = array_filter(array_map('trim', explode("\n", (string) $recenze['minusy'])));
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars((string) $recenze['nazev']); ?> | Ratanovo Doupě</title>
    <meta name="description" content="Detail recenze: <?= htmlspecialchars((string) $recenze['nazev']); ?>.">
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
            line-height: 1.6;
        }
        header, nav, main, footer { width: min(980px, 92vw); margin: 0 auto; }
        header { padding: 2rem 0 1rem; border-bottom: 2px solid var(--line); }
        nav { padding: 1rem 0; border-bottom: 2px solid var(--line); }
        nav a {
            color: var(--text);
            text-decoration: none;
            border-bottom: 2px solid var(--accent);
            font-weight: 600;
        }
        h1 { margin: 0; font-size: clamp(2rem, 4.6vw, 3rem); }
        .meta { margin-top: .8rem; display: flex; gap: .8rem; align-items: center; }
        .pill, .score {
            border: 2px solid var(--line);
            padding: .2rem .55rem;
        }
        .pill { text-transform: uppercase; font-size: .8rem; letter-spacing: .05em; }
        .score { color: var(--accent); font-weight: 800; }
        main { padding: 1.5rem 0 2rem; }
        .video-wrap {
            border: 2px solid var(--line);
            margin-bottom: 1.2rem;
            aspect-ratio: 16 / 9;
        }
        iframe { width: 100%; height: 100%; border: 0; }
        article.content {
            border: 2px solid var(--line);
            padding: 1rem;
            margin-bottom: 1.2rem;
            white-space: pre-line;
        }
        section.box {
            border: 2px solid var(--line);
            padding: 1rem;
        }
        .box h2 { margin-top: 0; }
        .lists {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        h3 { margin: 0 0 .6rem; }
        ul { margin: 0; padding-left: 1.2rem; }
        li + li { margin-top: .45rem; }
        .verdict {
            border-top: 2px solid var(--line);
            padding-top: 1rem;
        }
        .verdict strong { color: var(--accent); }
        .rating {
            margin: .4rem 0 0;
            font-size: clamp(2rem, 7vw, 3rem);
            font-weight: 900;
            color: var(--accent);
            line-height: 1;
        }
        footer { border-top: 2px solid var(--line); padding: 1rem 0 2rem; font-size: .95rem; }
    </style>
</head>
<body>
    <header>
        <h1><?= htmlspecialchars((string) $recenze['nazev']); ?></h1>
        <div class="meta">
            <span class="pill"><?= htmlspecialchars((string) $recenze['typ']); ?></span>
            <span class="score"><?= (int) $recenze['skore']; ?>%</span>
        </div>
    </header>

    <nav aria-label="Drobečková navigace">
        <a href="index.php">← Zpět na recenze</a>
    </nav>

    <main>
        <section class="video-wrap" aria-label="YouTube video recenze">
            <iframe src="<?= htmlspecialchars((string) $recenze['youtube_url']); ?>" title="YouTube video: <?= htmlspecialchars((string) $recenze['nazev']); ?>" allowfullscreen loading="lazy"></iframe>
        </section>

        <article class="content">
            <?= nl2br(htmlspecialchars((string) $recenze['text_recenze'])); ?>
        </article>

        <section class="box" aria-label="Ratanův Box">
            <h2>Ratanův Box</h2>
            <div class="lists">
                <section>
                    <h3>Plusy</h3>
                    <ul>
                        <?php foreach ($plusy as $plus): ?>
                            <li><?= htmlspecialchars($plus); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <section>
                    <h3>Minusy</h3>
                    <ul>
                        <?php foreach ($minusy as $minus): ?>
                            <li><?= htmlspecialchars($minus); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            </div>

            <div class="verdict">
                <p><strong>Verdikt:</strong> <?= htmlspecialchars((string) $recenze['verdikt']); ?></p>
                <p class="rating"><?= (int) $recenze['hodnoceni']; ?>%</p>
            </div>
        </section>
    </main>

    <footer>
        <small>&copy; <?= date('Y'); ?> Ratanovo Doupě</small>
    </footer>
</body>
</html>
