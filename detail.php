<?php
declare(strict_types=1);

session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/common.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('Neplatné ID recenze.');
}

$newsletter = handleNewsletterSubmit($pdo);

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

$videoSource = trim((string) ($recenze['youtube_id'] ?? $recenze['youtube_url'] ?? ''));
$embedUrl = str_contains($videoSource, 'http') ? $videoSource : 'https://www.youtube.com/embed/' . rawurlencode($videoSource);
$plusy = array_filter(array_map('trim', explode("\n", (string) ($recenze['plusy'] ?? ''))));
$minusy = array_filter(array_map('trim', explode("\n", (string) ($recenze['minusy'] ?? ''))));

$pdo->exec('CREATE TABLE IF NOT EXISTS hodnoceni_uzivatelu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recenze_id INT NOT NULL,
    uzivatel_id INT NOT NULL,
    body TINYINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_rating (recenze_id, uzivatel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

$pdo->exec('CREATE TABLE IF NOT EXISTS giveaway_prihlasky (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recenze_id INT NOT NULL,
    uzivatel_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_giveaway (recenze_id, uzivatel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

$userId = currentUserId();
$verified = $userId ? isUserEmailVerified($pdo, $userId) : false;
$ratingMessage = '';
$giveawayMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rate_submit'])) {
    if (!$userId) {
        $ratingMessage = 'Pro hodnocení se musíte přihlásit.';
    } else {
        $body = (int) ($_POST['body'] ?? 0);
        $body = max(1, min(10, $body));
        $rateStmt = $pdo->prepare('INSERT INTO hodnoceni_uzivatelu (recenze_id, uzivatel_id, body) VALUES (:rid, :uid, :body)
            ON DUPLICATE KEY UPDATE body = VALUES(body)');
        $rateStmt->execute(['rid' => $id, 'uid' => $userId, 'body' => $body]);
        $ratingMessage = 'Hodnocení bylo uloženo.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['giveaway_submit'])) {
    if (!$userId || !$verified) {
        $giveawayMessage = 'Do giveaway se může zapojit pouze přihlášený uživatel s ověřeným e-mailem.';
    } else {
        $gStmt = $pdo->prepare('INSERT IGNORE INTO giveaway_prihlasky (recenze_id, uzivatel_id) VALUES (:rid, :uid)');
        $gStmt->execute(['rid' => $id, 'uid' => $userId]);
        $giveawayMessage = 'Jste přihlášen do giveaway.';
    }
}

$avgStmt = $pdo->prepare('SELECT AVG(body) AS avg_body FROM hodnoceni_uzivatelu WHERE recenze_id = :id');
$avgStmt->execute(['id' => $id]);
$avgScore = (float) ($avgStmt->fetchColumn() ?: 0);
$ctenariPct = $avgScore > 0 ? (int) round($avgScore * 10) : null;
$ratanPct = (int) ($recenze['hodnoceni'] ?? $recenze['skore'] ?? 0);
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e((string) $recenze['nazev']); ?> | Ratanovo Doupě</title>
    <style>
        :root { --bg:#fff; --text:#111; --line:#222; --accent:#8B4513; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif; background:var(--bg); color:var(--text); }
        .wrap { width:min(980px,92vw); margin:0 auto; }
        header, nav, footer { border-bottom:2px solid var(--line); padding:1rem 0; }
        nav a { color:var(--text); text-decoration:none; border-bottom:2px solid var(--accent); }
        .panel { border:2px solid var(--line); padding:1rem; margin:1rem 0; }
        .video { aspect-ratio:16/9; border:2px solid var(--line); }
        iframe { width:100%; height:100%; border:0; }
        .lists { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
        input, select, button, footer input, footer button { border:2px solid var(--line); border-radius:0; padding:.5rem .6rem; font:inherit; background:#fff; }
        button, footer button { background:var(--accent); color:#fff; font-weight:700; }
        .rating { font-size:2rem; color:var(--accent); font-weight:800; }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <h1><?= e((string) $recenze['nazev']); ?></h1>
        <p><strong><?= e((string) $recenze['typ']); ?></strong> · <?= (int) ($recenze['skore'] ?? 0); ?>%</p>
    </header>
    <nav><a href="index.php">← Zpět</a></nav>

    <main>
        <section class="video panel"><iframe src="<?= e($embedUrl); ?>" title="YouTube" allowfullscreen loading="lazy"></iframe></section>
        <article class="panel"><?= nl2br(e((string) ($recenze['text_recenze'] ?? ''))); ?></article>

        <section class="panel">
            <h2>Ratanův Box</h2>
            <div class="lists">
                <section><h3>Plusy</h3><ul><?php foreach ($plusy as $plus): ?><li><?= e($plus); ?></li><?php endforeach; ?></ul></section>
                <section><h3>Mínusy</h3><ul><?php foreach ($minusy as $minus): ?><li><?= e($minus); ?></li><?php endforeach; ?></ul></section>
            </div>
            <p><strong>Verdikt:</strong> <?= e((string) ($recenze['verdikt'] ?? '')); ?></p>
            <p class="rating"><?= $ratanPct; ?>%</p>
        </section>

        <section class="panel">
            <h2>Komunita</h2>
            <p><strong>Ratan:</strong> <?= $ratanPct; ?>% | <strong>Čtenáři:</strong> <?= $ctenariPct !== null ? $ctenariPct . '%' : 'zatím bez hodnocení'; ?></p>
            <?php if ($ratingMessage): ?><p><?= e($ratingMessage); ?></p><?php endif; ?>
            <?php if ($userId): ?>
                <form method="post">
                    <label for="body">Vaše hodnocení (1–10)</label>
                    <input type="number" id="body" name="body" min="1" max="10" required>
                    <button type="submit" name="rate_submit" value="1">Uložit hodnocení</button>
                </form>
            <?php else: ?>
                <p>Pro hodnocení se přihlaste.</p>
            <?php endif; ?>
        </section>

        <section class="panel">
            <h2>Giveaway</h2>
            <?php if ($giveawayMessage): ?><p><?= e($giveawayMessage); ?></p><?php endif; ?>
            <?php if ($userId && $verified): ?>
                <form method="post">
                    <button type="submit" name="giveaway_submit" value="1">Zapojit se do soutěže</button>
                </form>
            <?php else: ?>
                <p>Pouze přihlášený uživatel s ověřeným e-mailem se může zapojit.</p>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        <?php if ($newsletter['ok']): ?><p><?= e($newsletter['ok']); ?></p><?php endif; ?>
        <?php if ($newsletter['error']): ?><p><?= e($newsletter['error']); ?></p><?php endif; ?>
        <form method="post">
            <input type="email" name="newsletter_email" required placeholder="tvuj@email.cz">
            <button type="submit" name="newsletter_submit" value="1">Newsletter</button>
        </form>
    </footer>
</div>
</body>
</html>
