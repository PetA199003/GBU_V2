<?php
/**
 * Registrierungs-Seite
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Auth.php';

// Bereits angemeldet? Weiterleiten
if (isLoggedIn()) {
    redirect('index.php');
}

$errors = [];
$auth = new Auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $auth->register([
        'benutzername' => $_POST['benutzername'] ?? '',
        'email' => $_POST['email'] ?? '',
        'passwort' => $_POST['passwort'] ?? '',
        'passwort_confirm' => $_POST['passwort_confirm'] ?? '',
        'vorname' => $_POST['vorname'] ?? '',
        'nachname' => $_POST['nachname'] ?? ''
    ]);

    if ($result['success']) {
        setFlashMessage('success', 'Registrierung erfolgreich! Sie können sich jetzt anmelden.');
        redirect('login.php');
    } else {
        $errors = $result['errors'];
    }
}

$pageTitle = 'Registrieren';
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
        <div class="login-card" style="max-width: 500px;">
            <div class="text-center mb-4">
                <div class="login-logo">
                    <i class="bi bi-shield-check"></i>
                </div>
                <h4><?= APP_NAME ?></h4>
                <p class="text-muted">Neues Konto erstellen</p>
            </div>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle me-2"></i>
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $error): ?>
                    <li><?= sanitize($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form method="POST" action="" data-validate>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="vorname" class="form-label">Vorname *</label>
                        <input type="text"
                               class="form-control"
                               id="vorname"
                               name="vorname"
                               required
                               value="<?= sanitize($_POST['vorname'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="nachname" class="form-label">Nachname *</label>
                        <input type="text"
                               class="form-control"
                               id="nachname"
                               name="nachname"
                               required
                               value="<?= sanitize($_POST['nachname'] ?? '') ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="benutzername" class="form-label">Benutzername *</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text"
                               class="form-control"
                               id="benutzername"
                               name="benutzername"
                               required
                               minlength="3"
                               pattern="[a-zA-Z0-9_]+"
                               title="Nur Buchstaben, Zahlen und Unterstriche"
                               value="<?= sanitize($_POST['benutzername'] ?? '') ?>">
                    </div>
                    <div class="form-text">Mindestens 3 Zeichen, nur Buchstaben, Zahlen und Unterstriche</div>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">E-Mail-Adresse *</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email"
                               class="form-control"
                               id="email"
                               name="email"
                               required
                               value="<?= sanitize($_POST['email'] ?? '') ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="passwort" class="form-label">Passwort *</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password"
                               class="form-control"
                               id="passwort"
                               name="passwort"
                               required
                               minlength="6">
                    </div>
                    <div class="form-text">Mindestens 6 Zeichen</div>
                </div>

                <div class="mb-4">
                    <label for="passwort_confirm" class="form-label">Passwort wiederholen *</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                        <input type="password"
                               class="form-control"
                               id="passwort_confirm"
                               name="passwort_confirm"
                               required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3">
                    <i class="bi bi-person-plus me-2"></i>Registrieren
                </button>

                <div class="text-center">
                    <a href="<?= BASE_URL ?>/login.php" class="text-decoration-none">
                        Bereits registriert? Jetzt anmelden
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Passwort-Bestätigung validieren
        document.getElementById('passwort_confirm').addEventListener('input', function() {
            const passwort = document.getElementById('passwort').value;
            if (this.value !== passwort) {
                this.setCustomValidity('Die Passwörter stimmen nicht überein');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
