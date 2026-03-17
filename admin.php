<?php
declare(strict_types=1);

session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/common.php';

const ADMIN_PASSWORD = 'Ratan123!';

function isAdminLoggedIn(): bool
{
    return isset($_SESSION['ratan_admin']) && $_SESSION['ratan_admin'] === true;
}

/** @return array<int, string> */
function getColumns(PDO $pdo, string $table): array
{
    $stmt = $pdo->query('SHOW COLUMNS FROM ' . $table);
    return array_map(static fn(array $col): string => (string) $col['Field'], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function storeUploadedImage(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Nahrání obrázku selhalo.');
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = (string) finfo_file($finfo, $tmp);
    finfo_close($finfo);

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Povolené formáty: JPG, PNG, WEBP.');
    }

    $uploadDir = __DIR__ . '/img/uploads';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Nelze vytvořit upload složku.');
    }

    $filename = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    $target = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($tmp, $target)) {
        throw new RuntimeException('Uložení souboru selhalo.');
    }

    return 'img/uploads/' . $filename;
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

    $names = array_keys($insert);
    $holders = array_map(static fn(string $key): string => ':' . $key, $names);
    $sql = 'INSERT INTO recenze (' . implode(', ', $names) . ') VALUES (' . implode(', ', $holders) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($insert);
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
            $imagePath = storeUploadedImage($_FILES['fotka_upload'] ?? []);
            $rating = max(0, min(100, (int) ($_POST['rating'] ?? 0)));
            saveReview($pdo, [
                'typ' => (($_POST['typ'] ?? 'Hra') === 'Anime') ? 'Anime' : 'Hra',
                'nazev' => trim((string) ($_POST['nazev'] ?? '')),
                'fotka' => $imagePath,
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
            $pdo->exec('CREATE TABLE IF NOT EXISTS statusy (
                id INT AUTO_INCREMENT PRIMARY KEY,
                aktivita VARCHAR(100) NOT NULL,
                nazev_dila VARCHAR(255) NOT NULL,
                typ VARCHAR(20) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

            $stmt = $pdo->prepare('INSERT INTO statusy (aktivita, nazev_dila, typ) VALUES (:aktivita, :nazev_dila, :typ)');
            $stmt->execute([
                'aktivita' => trim((string) ($_POST['aktivita'] ?? 'Aktuálně hraju')),
                'nazev_dila' => trim((string) ($_POST['nazev_dila'] ?? '')),
                'typ' => (($_POST['status_typ'] ?? 'Hra') === 'Anime') ? 'Anime' : 'Hra',
            ]);
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
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administrace | Ratanovo Doupě</title>
    <style>
        :root { --line:#222; --accent:#8B4513; }
        body { margin:0; font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif; }
        .wrap { width:min(940px,92vw); margin:2rem auto; }
        .panel,.msg { border:2px solid var(--line); padding:1rem; margin-bottom:1rem; }
        input,textarea,select,button { width:100%; border:2px solid var(--line); border-radius:0; padding:.55rem; font:inherit; background:#fff; }
        button { width:auto; background:var(--accent); color:#fff; font-weight:700; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:1rem; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Administrace</h1><p><a href="index.php">← zpět na web</a></p>
    <?php if ($message): ?><p class="msg"><?= e($message); ?></p><?php endif; ?>
    <?php if ($error): ?><p class="msg"><?= e($error); ?></p><?php endif; ?>

    <?php if (!isAdminLoggedIn()): ?>
        <section class="panel"><h2>Přihlášení</h2><form method="post"><input type="password" name="login_password" required><p><button type="submit">Přihlásit</button></p></form></section>
    <?php else: ?>
        <section class="panel">
            <h2>Nová recenze</h2>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="new_review">
                <div class="grid">
                    <div><label>Typ</label><select name="typ"><option value="Hra">Hra</option><option value="Anime">Anime</option></select></div>
                    <div><label>Rating (0–100)</label><input type="number" name="rating" min="0" max="100" required></div>
                </div>
                <label>Název</label><input name="nazev" required>
                <label>Obrázek (upload)</label><input type="file" name="fotka_upload" accept="image/*" required>
                <label>YouTube ID</label><input name="youtube_id" required>
                <label>Krátký popis</label><textarea name="kratky_popis" required></textarea>
                <label>Hlavní text</label><textarea name="text_recenze" required></textarea>
                <div class="grid">
                    <div><label>Plusy</label><textarea name="plusy" required></textarea></div>
                    <div><label>Mínusy</label><textarea name="minusy" required></textarea></div>
                </div>
                <label>Verdikt</label><textarea name="verdikt" required></textarea>
                <p><button type="submit">Uložit recenzi</button></p>
            </form>
        </section>

        <section class="panel">
            <h2>Status</h2>
            <form method="post">
                <input type="hidden" name="action" value="update_status">
                <div class="grid">
                    <div><label>Aktivita</label><input name="aktivita" value="Aktuálně hraju" required></div>
                    <div><label>Typ</label><select name="status_typ"><option value="Hra">Hra</option><option value="Anime">Anime</option></select></div>
                </div>
                <label>Název díla</label><input name="nazev_dila" required>
                <p><button type="submit">Uložit status</button></p>
            </form>
        </section>

        <form method="post"><button type="submit" name="logout" value="1">Odhlásit</button></form>
    <?php endif; ?>
</div>
</body>
</html>
