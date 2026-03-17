<?php
declare(strict_types=1);

session_start();
require __DIR__ . '/db.php';

const ADMIN_PASSWORD = 'Ratan123!';

function isAdminLoggedIn(): bool
{
    return isset($_SESSION['ratan_admin']) && $_SESSION['ratan_admin'] === true;
}

/** @return array<int, string> */
function getColumns(PDO $pdo, string $table): array
{
    $stmt = $pdo->query('SHOW COLUMNS FROM ' . $table);
    $columns = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        $columns[] = (string) $col['Field'];
    }
    return $columns;
}

function saveReview(PDO $pdo, array $input): void
{
    $columns = getColumns($pdo, 'recenze');
    $map = [
        'typ' => $input['typ'],
        'nazev' => $input['nazev'],
        'fotka' => $input['fotka'],
        'youtube_id' => $input['youtube_id'],
        'youtube_url' => $input['youtube_id'],
        'kratky_popis' => $input['kratky_popis'],
        'text_recenze' => $input['text_recenze'],
        'plusy' => $input['plusy'],
        'minusy' => $input['minusy'],
        'verdikt' => $input['verdikt'],
        'hodnoceni' => $input['rating'],
        'skore' => $input['rating'],
    ];

    $insert = [];
    foreach ($map as $column => $value) {
        if (in_array($column, $columns, true)) {
            $insert[$column] = $value;
        }
    }

    if (!$insert) {
        throw new RuntimeException('Nelze vložit recenzi – tabulka nemá očekávané sloupce.');
    }

    $names = array_keys($insert);
    $placeholders = array_map(static fn(string $key): string => ':' . $key, $names);

    $sql = 'INSERT INTO recenze (' . implode(', ', $names) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($insert);
}

function saveStatus(PDO $pdo, string $aktivita, string $nazevDila, string $typ): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS statusy (
        id INT AUTO_INCREMENT PRIMARY KEY,
        aktivita VARCHAR(100) NOT NULL,
        nazev_dila VARCHAR(255) NOT NULL,
        typ VARCHAR(20) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $stmt = $pdo->prepare('INSERT INTO statusy (aktivita, nazev_dila, typ) VALUES (:aktivita, :nazev_dila, :typ)');
    $stmt->execute([
        'aktivita' => $aktivita,
        'nazev_dila' => $nazevDila,
        'typ' => $typ,
    ]);
}

$message = '';
$error = '';

if (isset($_POST['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: admin.php');
    exit;
}

if (!isAdminLoggedIn() && isset($_POST['login_password'])) {
    if (hash_equals(ADMIN_PASSWORD, (string) $_POST['login_password'])) {
        $_SESSION['ratan_admin'] = true;
        header('Location: admin.php');
        exit;
    }
    $error = 'Nesprávné heslo.';
}

if (isAdminLoggedIn() && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'new_review') {
            $typ = (string) ($_POST['typ'] ?? 'Hra');
            $typ = $typ === 'Anime' ? 'Anime' : 'Hra';
            $rating = (int) ($_POST['rating'] ?? 0);
            $rating = max(0, min(100, $rating));

            saveReview($pdo, [
                'typ' => $typ,
                'nazev' => trim((string) ($_POST['nazev'] ?? '')),
                'fotka' => trim((string) ($_POST['fotka'] ?? '')),
                'youtube_id' => trim((string) ($_POST['youtube_id'] ?? '')),
                'kratky_popis' => trim((string) ($_POST['kratky_popis'] ?? '')),
                'text_recenze' => trim((string) ($_POST['text_recenze'] ?? '')),
                'plusy' => trim((string) ($_POST['plusy'] ?? '')),
                'minusy' => trim((string) ($_POST['minusy'] ?? '')),
                'verdikt' => trim((string) ($_POST['verdikt'] ?? '')),
                'rating' => $rating,
            ]);
            $message = 'Recenze byla uložena.';
        }

        if ($_POST['action'] === 'update_status') {
            $typ = (string) ($_POST['status_typ'] ?? 'Hra');
            $typ = $typ === 'Anime' ? 'Anime' : 'Hra';

            saveStatus(
                $pdo,
                trim((string) ($_POST['aktivita'] ?? 'Aktuálně hraju')),
                trim((string) ($_POST['nazev_dila'] ?? '')),
                $typ
            );

            $message = 'Status byl aktualizován.';
        }
    } catch (Throwable $e) {
        $error = 'Chyba: ' . $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administrace | Ratanovo Doupě</title>
    <style>
        :root { --bg:#fff; --text:#111; --line:#222; --accent:#8B4513; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif; background:var(--bg); color:var(--text); }
        .wrap { width:min(940px,92vw); margin:2rem auto; }
        .panel { border:2px solid var(--line); padding:1rem; margin-bottom:1rem; }
        h1, h2 { margin-top:0; }
        label { display:block; margin:.7rem 0 .3rem; font-weight:600; }
        input, textarea, select, button { width:100%; border:2px solid var(--line); border-radius:0; background:#fff; padding:.55rem .6rem; font:inherit; }
        textarea { min-height:110px; }
        button { width:auto; cursor:pointer; background:var(--accent); color:#fff; font-weight:700; }
        .grid { display:grid; gap:1rem; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); }
        .msg { border:2px solid var(--line); padding:.7rem; margin-bottom:1rem; }
        .err { border-color:#900; color:#900; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Administrace</h1>
    <p><a href="index.php">← zpět na web</a></p>

    <?php if ($message): ?><p class="msg"><?= htmlspecialchars($message); ?></p><?php endif; ?>
    <?php if ($error): ?><p class="msg err"><?= htmlspecialchars($error); ?></p><?php endif; ?>

    <?php if (!isAdminLoggedIn()): ?>
        <section class="panel">
            <h2>Přihlášení</h2>
            <form method="post">
                <label for="login_password">Heslo</label>
                <input type="password" id="login_password" name="login_password" required>
                <p><button type="submit">Přihlásit</button></p>
            </form>
        </section>
    <?php else: ?>
        <section class="panel">
            <h2>Nová recenze</h2>
            <form method="post">
                <input type="hidden" name="action" value="new_review">
                <div class="grid">
                    <div>
                        <label for="typ">Typ</label>
                        <select id="typ" name="typ">
                            <option value="Hra">Hra</option>
                            <option value="Anime">Anime</option>
                        </select>
                    </div>
                    <div>
                        <label for="rating">Rating (0–100)</label>
                        <input type="number" id="rating" name="rating" min="0" max="100" required>
                    </div>
                </div>

                <label for="nazev">Název</label>
                <input id="nazev" name="nazev" required>

                <label for="fotka">IMG URL</label>
                <input id="fotka" name="fotka" required>

                <label for="youtube_id">YouTube ID</label>
                <input id="youtube_id" name="youtube_id" required>

                <label for="kratky_popis">Krátký popis</label>
                <textarea id="kratky_popis" name="kratky_popis" required></textarea>

                <label for="text_recenze">Hlavní text</label>
                <textarea id="text_recenze" name="text_recenze" required></textarea>

                <div class="grid">
                    <div>
                        <label for="plusy">Plusy (každý bod na nový řádek)</label>
                        <textarea id="plusy" name="plusy" required></textarea>
                    </div>
                    <div>
                        <label for="minusy">Mínusy (každý bod na nový řádek)</label>
                        <textarea id="minusy" name="minusy" required></textarea>
                    </div>
                </div>

                <label for="verdikt">Verdikt</label>
                <textarea id="verdikt" name="verdikt" required></textarea>

                <p><button type="submit">Uložit recenzi</button></p>
            </form>
        </section>

        <section class="panel">
            <h2>Status: Právě hraju / sleduju</h2>
            <form method="post">
                <input type="hidden" name="action" value="update_status">
                <div class="grid">
                    <div>
                        <label for="aktivita">Aktivita</label>
                        <input id="aktivita" name="aktivita" value="Aktuálně hraju" required>
                    </div>
                    <div>
                        <label for="status_typ">Typ</label>
                        <select id="status_typ" name="status_typ">
                            <option value="Hra">Hra</option>
                            <option value="Anime">Anime</option>
                        </select>
                    </div>
                </div>
                <label for="nazev_dila">Název díla</label>
                <input id="nazev_dila" name="nazev_dila" required>
                <p><button type="submit">Uložit status</button></p>
            </form>
        </section>

        <form method="post"><button type="submit" name="logout" value="1">Odhlásit</button></form>
    <?php endif; ?>
</div>
</body>
</html>
