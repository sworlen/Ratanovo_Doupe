<?php
declare(strict_types=1);

require __DIR__ . '/db.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('Neplatné ID recenze.');
}

$stmt = $pdo->prepare('SELECT id, typ, nazev, skore, youtube_url, youtube_id, kratky_popis, text_recenze, plusy, minusy, verdikt, hodnoceni FROM recenze WHERE id = :id LIMIT 1');
try {
    $stmt->execute(['id' => $id]);
} catch (PDOException $e) {
    $fallback = $pdo->prepare('SELECT id, typ, nazev, skore, youtube_url, text_recenze, plusy, minusy, verdikt, hodnoceni FROM recenze WHERE id = :id LIMIT 1');
    $fallback->execute(['id' => $id]);
    $stmt = $fallback;
}
$recenze = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$recenze) {
    http_response_code(404);
    exit('Recenze nebyla nalezena.');
}

$videoSource = (string)($recenze['youtube_id'] ?? '');
if ($videoSource === '') {
    $videoSource = (string)($recenze['youtube_url'] ?? '');
}
$videoSource = trim($videoSource);
$embedUrl = str_contains($videoSource, 'http') ? $videoSource : 'https://www.youtube.com/embed/' . rawurlencode($videoSource);

$plusy = array_filter(array_map('trim', explode("\n", (string) ($recenze['plusy'] ?? ''))));
$minusy = array_filter(array_map('trim', explode("\n", (string) ($recenze['minusy'] ?? ''))));
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars((string) $recenze['nazev']); ?> | Ratanovo Doupě</title>
    <meta name="description" content="<?= htmlspecialchars((string) ($recenze['kratky_popis'] ?? 'Detail recenze')); ?>">
    <style>
        :root { --bg:#fff; --text:#111; --line:#222; --accent:#8B4513; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif; background:var(--bg); color:var(--text); line-height:1.6; }
        .wrap { width:min(980px,92vw); margin:0 auto; }
        header, nav, footer { border-bottom:2px solid var(--line); padding:1rem 0; }
        nav a { color:var(--text); text-decoration:none; border-bottom:2px solid var(--accent); font-weight:600; }
        .video-wrap, article.content, .box { border:2px solid var(--line); margin:1rem 0; }
        .video-wrap { aspect-ratio:16/9; }
        iframe { width:100%; height:100%; border:0; }
        article.content, .box { padding:1rem; }
        .lists { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:1rem; }
        .rating { font-size:3rem; font-weight:900; color:var(--accent); margin:.3rem 0 0; }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <h1><?= htmlspecialchars((string) $recenze['nazev']); ?></h1>
        <p><strong><?= htmlspecialchars((string) $recenze['typ']); ?></strong> · <?= (int) $recenze['skore']; ?>%</p>
    </header>

    <nav aria-label="Drobečková navigace"><a href="index.php">← Zpět na recenze</a></nav>

    <main>
        <section class="video-wrap" aria-label="YouTube video recenze">
            <iframe src="<?= htmlspecialchars($embedUrl); ?>" title="YouTube video: <?= htmlspecialchars((string) $recenze['nazev']); ?>" allowfullscreen loading="lazy"></iframe>
        </section>

        <article class="content"><?= nl2br(htmlspecialchars((string) ($recenze['text_recenze'] ?? ''))); ?></article>

        <section class="box" aria-label="Ratanův Box">
            <h2>Ratanův Box</h2>
            <div class="lists">
                <section><h3>Plusy</h3><ul><?php foreach ($plusy as $plus): ?><li><?= htmlspecialchars($plus); ?></li><?php endforeach; ?></ul></section>
                <section><h3>Mínusy</h3><ul><?php foreach ($minusy as $minus): ?><li><?= htmlspecialchars($minus); ?></li><?php endforeach; ?></ul></section>
            </div>
            <div>
                <p><strong>Verdikt:</strong> <?= htmlspecialchars((string) ($recenze['verdikt'] ?? '')); ?></p>
                <p class="rating"><?= (int) ($recenze['hodnoceni'] ?? 0); ?>%</p>
            </div>
        </section>
    </main>

    <footer><small>&copy; <?= date('Y'); ?> Ratanovo Doupě</small></footer>
</div>
</body>
</html>
