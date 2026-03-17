<?php
declare(strict_types=1);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function appError(string $fallback = 'Dočasná chyba aplikace.'): string
{
    return $fallback;
}

function hasTable(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE :table');
        $stmt->execute(['table' => $table]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

/** @return array<int, string> */
function tableColumns(PDO $pdo, string $table): array
{
    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM ' . $table);
        return array_map(static fn(array $col): string => (string) $col['Field'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $e) {
        return [];
    }
}

function ensureNewsletterTable(PDO $pdo): void
{
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS odberatele (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            hash_pro_odhlaseni VARCHAR(64) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    } catch (Throwable $e) {
        // noop: no CREATE rights on some hostings
    }
}

/** @return array{ok:string,error:string} */
function handleNewsletterSubmit(PDO $pdo): array
{
    $result = ['ok' => '', 'error' => ''];
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['newsletter_submit'])) {
        return $result;
    }

    $email = trim((string) ($_POST['newsletter_email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $result['error'] = 'Zadejte platný e-mail.';
        return $result;
    }

    ensureNewsletterTable($pdo);
    if (!hasTable($pdo, 'odberatele')) {
        $result['error'] = 'Newsletter nyní není dostupný (chybí tabulka odberatele).';
        return $result;
    }

    try {
        $hash = hash('sha256', $email . '|' . bin2hex(random_bytes(16)));
        $stmt = $pdo->prepare('INSERT INTO odberatele (email, hash_pro_odhlaseni) VALUES (:email, :hash) ON DUPLICATE KEY UPDATE email = VALUES(email)');
        $stmt->execute(['email' => $email, 'hash' => $hash]);
        $result['ok'] = 'Děkujeme za přihlášení k newsletteru.';
    } catch (Throwable $e) {
        $result['error'] = 'Newsletter nyní není dostupný.';
    }

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
        if (!hasTable($pdo, $table)) {
            continue;
        }

        $columns = tableColumns($pdo, $table);
        $idCol = in_array('id', $columns, true) ? 'id' : (in_array('uzivatel_id', $columns, true) ? 'uzivatel_id' : null);
        if ($idCol === null) {
            continue;
        }

        foreach (['email_verified', 'overen_email', 'is_verified'] as $verifyCol) {
            if (!in_array($verifyCol, $columns, true)) {
                continue;
            }
            try {
                $stmt = $pdo->prepare("SELECT {$verifyCol} FROM {$table} WHERE {$idCol} = :id LIMIT 1");
                $stmt->execute(['id' => $userId]);
                $value = $stmt->fetchColumn();
                if ($value !== false) {
                    return (bool) $value;
                }
            } catch (Throwable $e) {
                return false;
            }
        }
    }

    return false;
}

function snippet(string $text, int $length = 180): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $length);
    }

    return substr($text, 0, $length);
}
