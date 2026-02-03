<?php
/**
 * Benutzerprofil
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Auth.php';

requireLogin();

$auth = new Auth();
$user = $auth->getUserById($_SESSION['user_id']);

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $data = [
            'vorname' => trim($_POST['vorname'] ?? ''),
            'nachname' => trim($_POST['nachname'] ?? ''),
            'email' => trim($_POST['email'] ?? '')
        ];

        if (empty($data['vorname']) || empty($data['nachname']) || empty($data['email'])) {
            $errors[] = 'Bitte füllen Sie alle Pflichtfelder aus.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
        } else {
            $result = $auth->updateUser($_SESSION['user_id'], $data);
            if ($result['success']) {
                // Session aktualisieren
                $_SESSION['user']['vorname'] = $data['vorname'];
                $_SESSION['user']['nachname'] = $data['nachname'];
                $_SESSION['user']['email'] = $data['email'];
                $_SESSION['user']['voller_name'] = $data['vorname'] . ' ' . $data['nachname'];
                $success = 'Profil wurde aktualisiert.';
                $user = $auth->getUserById($_SESSION['user_id']);
            }
        }
    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $errors[] = 'Bitte füllen Sie alle Passwortfelder aus.';
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = 'Die neuen Passwörter stimmen nicht überein.';
        } elseif (strlen($newPassword) < 6) {
            $errors[] = 'Das neue Passwort muss mindestens 6 Zeichen lang sein.';
        } else {
            $result = $auth->changePassword($_SESSION['user_id'], $currentPassword, $newPassword);
            if ($result['success']) {
                $success = 'Passwort wurde geändert.';
            } else {
                $errors[] = $result['error'];
            }
        }
    }
}

$pageTitle = 'Mein Profil';
require_once __DIR__ . '/templates/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="bi bi-person-circle me-2"></i>Mein Profil
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Profil</li>
                        </ol>
                    </nav>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                    <li><?= sanitize($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success"><?= sanitize($success) ?></div>
            <?php endif; ?>

            <!-- Profil-Informationen -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Profil-Informationen</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="vorname" class="form-label">Vorname *</label>
                                <input type="text" class="form-control" id="vorname" name="vorname"
                                       required value="<?= sanitize($user['vorname']) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="nachname" class="form-label">Nachname *</label>
                                <input type="text" class="form-control" id="nachname" name="nachname"
                                       required value="<?= sanitize($user['nachname']) ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">E-Mail-Adresse *</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   required value="<?= sanitize($user['email']) ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Benutzername</label>
                            <input type="text" class="form-control" value="<?= sanitize($user['benutzername']) ?>" disabled>
                            <div class="form-text">Der Benutzername kann nicht geändert werden.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Rolle</label>
                            <input type="text" class="form-control"
                                   value="<?= $ROLE_NAMES[$user['rolle']] ?? 'Unbekannt' ?>" disabled>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i>Änderungen speichern
                        </button>
                    </form>
                </div>
            </div>

            <!-- Passwort ändern -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Passwort ändern</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">

                        <div class="mb-3">
                            <label for="current_password" class="form-label">Aktuelles Passwort *</label>
                            <input type="password" class="form-control" id="current_password"
                                   name="current_password" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="new_password" class="form-label">Neues Passwort *</label>
                                <input type="password" class="form-control" id="new_password"
                                       name="new_password" required minlength="6">
                                <div class="form-text">Mindestens 6 Zeichen</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Passwort wiederholen *</label>
                                <input type="password" class="form-control" id="confirm_password"
                                       name="confirm_password" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-key me-2"></i>Passwort ändern
                        </button>
                    </form>
                </div>
            </div>

            <!-- Konto-Informationen -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Konto-Informationen</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Konto erstellt:</strong></p>
                            <p class="text-muted"><?= date('d.m.Y H:i', strtotime($user['erstellt_am'])) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Letzter Login:</strong></p>
                            <p class="text-muted">
                                <?= $user['letzter_login'] ? date('d.m.Y H:i', strtotime($user['letzter_login'])) : 'Noch nie' ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Passwort-Bestätigung validieren
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    if (this.value !== newPassword) {
        this.setCustomValidity('Die Passwörter stimmen nicht überein');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
