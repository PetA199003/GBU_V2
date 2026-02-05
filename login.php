<?php
/**
 * Login-Seite mit IP-basierter Sperre nach 5 Fehlversuchen
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/Auth.php';

// Bereits angemeldet? Weiterleiten
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$auth = new Auth();
$db = Database::getInstance();

// Konfiguration
$maxAttempts = 5;        // Maximale Fehlversuche
$lockoutTime = 10;       // Sperrzeit in Minuten

// IP-Adresse ermitteln
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Kann mehrere IPs enthalten, erste nehmen
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

$clientIP = getClientIP();
$isLocked = false;
$remainingTime = 0;

// Login-Attempts Tabelle erstellen falls nicht vorhanden
try {
    $db->query("CREATE TABLE IF NOT EXISTS `login_attempts` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `ip_address` VARCHAR(45) NOT NULL,
        `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `benutzername` VARCHAR(100) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_ip_address` (`ip_address`),
        KEY `idx_attempted_at` (`attempted_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    // Tabelle existiert bereits - ignorieren
}

// Alte Einträge löschen (älter als Sperrzeit)
$db->query("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)", [$lockoutTime]);

// Fehlversuche für diese IP zählen
$attempts = $db->fetchOne(
    "SELECT COUNT(*) as cnt, MAX(attempted_at) as last_attempt
     FROM login_attempts
     WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)",
    [$clientIP, $lockoutTime]
);

$attemptCount = (int)($attempts['cnt'] ?? 0);

// Prüfen ob IP gesperrt ist
if ($attemptCount >= $maxAttempts) {
    $isLocked = true;
    $lastAttempt = strtotime($attempts['last_attempt']);
    $unlockTime = $lastAttempt + ($lockoutTime * 60);
    $remainingTime = ceil(($unlockTime - time()) / 60);
    if ($remainingTime < 1) $remainingTime = 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isLocked) {
    $benutzername = $_POST['benutzername'] ?? '';
    $passwort = $_POST['passwort'] ?? '';

    $result = $auth->login($benutzername, $passwort);

    if ($result['success']) {
        // Erfolgreicher Login - alle Fehlversuche für diese IP löschen
        $db->query("DELETE FROM login_attempts WHERE ip_address = ?", [$clientIP]);
        redirect('index.php');
    } else {
        // Fehlgeschlagener Login - Versuch protokollieren
        $db->insert('login_attempts', [
            'ip_address' => $clientIP,
            'benutzername' => $benutzername
        ]);

        $attemptCount++;
        $remainingAttempts = $maxAttempts - $attemptCount;

        if ($remainingAttempts <= 0) {
            $isLocked = true;
            $remainingTime = $lockoutTime;
            $error = "Zu viele fehlgeschlagene Anmeldeversuche. Bitte warten Sie $lockoutTime Minuten.";
        } elseif ($remainingAttempts <= 2) {
            $error = $result['error'] . " (Noch $remainingAttempts Versuch" . ($remainingAttempts > 1 ? 'e' : '') . " übrig)";
        } else {
            $error = $result['error'];
        }
    }
}

$pageTitle = 'Anmelden';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="text-center mb-4">
                <div class="login-logo">
                    <i class="bi bi-shield-check"></i>
                </div>
                <h4><?= APP_NAME ?></h4>
                <p class="text-muted">Bitte melden Sie sich an</p>
            </div>

            <?php if ($isLocked): ?>
            <div class="alert alert-danger">
                <i class="bi bi-lock-fill me-2"></i>
                <strong>Zugang gesperrt!</strong><br>
                Zu viele fehlgeschlagene Anmeldeversuche.<br>
                Bitte warten Sie noch <strong><?= $remainingTime ?> Minute<?= $remainingTime > 1 ? 'n' : '' ?></strong>.
            </div>
            <?php elseif ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle me-2"></i><?= sanitize($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="" <?= $isLocked ? 'style="opacity: 0.5; pointer-events: none;"' : '' ?>>
                <div class="mb-3">
                    <label for="benutzername" class="form-label">Benutzername oder E-Mail</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text"
                               class="form-control"
                               id="benutzername"
                               name="benutzername"
                               required
                               autofocus
                               value="<?= sanitize($_POST['benutzername'] ?? '') ?>">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="passwort" class="form-label">Passwort</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password"
                               class="form-control"
                               id="passwort"
                               name="passwort"
                               required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Anmelden
                </button>

                <div class="text-center">
                    <a href="<?= BASE_URL ?>/register.php" class="text-decoration-none">
                        Noch kein Konto? Jetzt registrieren
                    </a>
                </div>
            </form>

            <hr class="my-4">

            <div class="text-center text-muted small">
                <p class="mb-1">&copy; <?= date('Y') ?> Peter Astor</p>
                <p class="mb-0">
                    <i class="bi bi-envelope me-1"></i>
                    <a href="mailto:info@peter-astor.ch" class="text-muted">info@peter-astor.ch</a>
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
