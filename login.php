<?php
/**
 * Login-Seite
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Auth.php';

// Bereits angemeldet? Weiterleiten
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$auth = new Auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $benutzername = $_POST['benutzername'] ?? '';
    $passwort = $_POST['passwort'] ?? '';

    $result = $auth->login($benutzername, $passwort);

    if ($result['success']) {
        redirect('index.php');
    } else {
        $error = $result['error'];
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

            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle me-2"></i><?= sanitize($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
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
                <p class="mb-1">Demo-Zugangsdaten:</p>
                <p class="mb-0"><strong>Benutzer:</strong> admin</p>
                <p><strong>Passwort:</strong> password</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
