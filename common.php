<?php
declare(strict_types=1);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function ensureNewsletterTable(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS odberatele (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        hash_pro_odhlaseni VARCHAR(64) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

function handleNewsletterSubmit(PDO $pdo): array
{
    $result = ['ok' => '', 'error' => ''];
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['newsletter_submit'])) {
        return $result;
    }

    ensureNewsletterTable($pdo);

    $email = trim((string) ($_POST['newsletter_email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $result['error'] = 'Zadejte platný e-mail.';
        return $result;
    }

    $hash = hash('sha256', $email . '|' . bin2hex(random_bytes(16)));
    $stmt = $pdo->prepare('INSERT INTO odberatele (email, hash_pro_odhlaseni) VALUES (:email, :hash) ON DUPLICATE KEY UPDATE email = VALUES(email)');
    $stmt->execute(['email' => $email, 'hash' => $hash]);
    $result['ok'] = 'Děkujeme za přihlášení k newsletteru.';

    return $result;
}

function currentUserId(): ?int
{
    if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
        return (int) $_SESSION['user_id'];
    }
    if (isset($_SESSION['uzivatel_id']) && is_numeric($_SESSION['uzivatel_id'])) {
        return (int) $_SESSION['uzivatel_id'];
    }

    return null;
}

function isUserEmailVerified(PDO $pdo, int $userId): bool
{
    if (isset($_SESSION['email_verified'])) {
        return (bool) $_SESSION['email_verified'];
    }
    if (isset($_SESSION['overen_email'])) {
        return (bool) $_SESSION['overen_email'];
    }

    $possibleTables = ['users', 'uzivatele'];
    foreach ($possibleTables as $table) {
        $check = $pdo->query("SHOW TABLES LIKE '{$table}'");
        if (!$check || !$check->fetchColumn()) {
            continue;
        }

        $columnsStmt = $pdo->query('SHOW COLUMNS FROM ' . $table);
        $columns = array_column($columnsStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
        $idCol = in_array('id', $columns, true) ? 'id' : (in_array('uzivatel_id', $columns, true) ? 'uzivatel_id' : null);
        if ($idCol === null) {
            continue;
        }

        foreach (['email_verified', 'overen_email', 'is_verified'] as $verifyCol) {
            if (!in_array($verifyCol, $columns, true)) {
                continue;
            }
            $stmt = $pdo->prepare("SELECT {$verifyCol} FROM {$table} WHERE {$idCol} = :id LIMIT 1");
            $stmt->execute(['id' => $userId]);
            $value = $stmt->fetchColumn();
            if ($value !== false) {
                return (bool) $value;
            }
        }
    }

    return false;
}
