<?php
/**
 * Benutzerverwaltung (Admin)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Auth.php';

requireRole(ROLE_ADMIN);

$auth = new Auth();
$db = Database::getInstance();

// Firmen-Tabelle erstellen falls nicht vorhanden
try {
    $db->query("CREATE TABLE IF NOT EXISTS `firmen` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(200) NOT NULL,
        `strasse` VARCHAR(200) DEFAULT NULL,
        `plz` VARCHAR(10) DEFAULT NULL,
        `ort` VARCHAR(100) DEFAULT NULL,
        `land` VARCHAR(100) DEFAULT 'Schweiz',
        `telefon` VARCHAR(50) DEFAULT NULL,
        `email` VARCHAR(100) DEFAULT NULL,
        `webseite` VARCHAR(200) DEFAULT NULL,
        `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
        `erstellt_von` INT UNSIGNED DEFAULT NULL,
        `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `aktualisiert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Spalte firma_id zu benutzer hinzufügen falls nicht vorhanden
    $colExists = $db->fetchOne("SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'benutzer' AND COLUMN_NAME = 'firma_id'");
    if ($colExists['cnt'] == 0) {
        $db->query("ALTER TABLE benutzer ADD COLUMN firma_id INT UNSIGNED DEFAULT NULL AFTER rolle");
    }
} catch (Exception $e) {
    // Ignorieren
}

// Firmen laden
$firmen = $db->fetchAll("SELECT * FROM firmen WHERE aktiv = 1 ORDER BY name");

// Aktion verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = $_POST['user_id'] ?? null;

    switch ($action) {
        case 'create':
            $result = $auth->register([
                'benutzername' => $_POST['benutzername'] ?? '',
                'email' => $_POST['email'] ?? '',
                'passwort' => $_POST['passwort'] ?? '',
                'passwort_confirm' => $_POST['passwort'] ?? '',
                'vorname' => $_POST['vorname'] ?? '',
                'nachname' => $_POST['nachname'] ?? ''
            ]);

            if ($result['success']) {
                // Rolle und Firma setzen
                $updateData = ['rolle' => (int)$_POST['rolle']];
                if (!empty($_POST['firma_id'])) {
                    $updateData['firma_id'] = (int)$_POST['firma_id'];
                }
                $auth->updateUser($result['user_id'], $updateData);
                setFlashMessage('success', 'Benutzer wurde erstellt.');
            } else {
                setFlashMessage('error', implode(' ', $result['errors']));
            }
            break;

        case 'create_firma':
            $firmaName = trim($_POST['firma_name'] ?? '');
            if (!empty($firmaName)) {
                $db->insert('firmen', [
                    'name' => $firmaName,
                    'ort' => $_POST['firma_ort'] ?? null,
                    'erstellt_von' => $_SESSION['user_id']
                ]);
                setFlashMessage('success', 'Unternehmen wurde erstellt.');
            }
            break;

        case 'delete_firma':
            $firmaId = (int)$_POST['firma_id'];
            // Prüfen ob noch Benutzer zugewiesen sind
            $userCount = $db->fetchOne("SELECT COUNT(*) as cnt FROM benutzer WHERE firma_id = ?", [$firmaId]);
            if ($userCount['cnt'] > 0) {
                setFlashMessage('error', 'Unternehmen kann nicht gelöscht werden, da noch Benutzer zugewiesen sind.');
            } else {
                $db->delete('firmen', 'id = ?', [$firmaId]);
                setFlashMessage('success', 'Unternehmen wurde gelöscht.');
            }
            break;

        case 'update_role':
            $newRole = (int)$_POST['rolle'];
            if ($userId && in_array($newRole, [ROLE_VIEWER, ROLE_EDITOR, ROLE_ADMIN])) {
                $auth->updateUser($userId, ['rolle' => $newRole]);
                setFlashMessage('success', 'Rolle wurde aktualisiert.');
            }
            break;

        case 'toggle_active':
            $user = $auth->getUserById($userId);
            if ($user && $userId != $_SESSION['user_id']) {
                $auth->updateUser($userId, ['aktiv' => $user['aktiv'] ? 0 : 1]);
                setFlashMessage('success', 'Benutzerstatus wurde aktualisiert.');
            }
            break;

        case 'delete':
            if ($userId && $userId != $_SESSION['user_id']) {
                $result = $auth->deleteUser($userId);
                if ($result['success']) {
                    setFlashMessage('success', 'Benutzer wurde gelöscht.');
                } else {
                    setFlashMessage('error', $result['error']);
                }
            }
            break;

        case 'update':
            if ($userId) {
                $updateData = [
                    'vorname' => $_POST['vorname'] ?? '',
                    'nachname' => $_POST['nachname'] ?? '',
                    'email' => $_POST['email'] ?? '',
                    'rolle' => (int)$_POST['rolle'],
                    'firma_id' => !empty($_POST['firma_id']) ? (int)$_POST['firma_id'] : null
                ];

                $auth->updateUser($userId, $updateData);

                // Neues Passwort setzen wenn angegeben
                if (!empty($_POST['neues_passwort'])) {
                    $result = $auth->resetPassword($userId, $_POST['neues_passwort']);
                    if (!$result['success']) {
                        setFlashMessage('error', $result['error']);
                        break;
                    }
                }

                setFlashMessage('success', 'Benutzer wurde aktualisiert.');
            }
            break;
    }

    redirect('admin/benutzer.php');
}

$users = $auth->getAllUsers();

$pageTitle = 'Benutzerverwaltung';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="bi bi-people me-2"></i>Benutzerverwaltung
            </h1>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#benutzerModal">
            <i class="bi bi-plus-lg me-2"></i>Neuer Benutzer
        </button>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Benutzername</th>
                            <th>E-Mail</th>
                            <th>Rolle</th>
                            <th>Status</th>
                            <th>Letzter Login</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr class="<?= !$user['aktiv'] ? 'table-secondary' : '' ?>">
                            <td><?= $user['id'] ?></td>
                            <td>
                                <strong><?= sanitize($user['vorname']) ?> <?= sanitize($user['nachname']) ?></strong>
                            </td>
                            <td><?= sanitize($user['benutzername']) ?></td>
                            <td><?= sanitize($user['email']) ?></td>
                            <td>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="update_role">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <select name="rolle"
                                            class="form-select form-select-sm d-inline-block w-auto"
                                            onchange="this.form.submit()"
                                            <?= $user['id'] == $_SESSION['user_id'] ? 'disabled' : '' ?>>
                                        <option value="<?= ROLE_VIEWER ?>" <?= $user['rolle'] == ROLE_VIEWER ? 'selected' : '' ?>>
                                            Betrachter
                                        </option>
                                        <option value="<?= ROLE_EDITOR ?>" <?= $user['rolle'] == ROLE_EDITOR ? 'selected' : '' ?>>
                                            Bearbeiter
                                        </option>
                                        <option value="<?= ROLE_ADMIN ?>" <?= $user['rolle'] == ROLE_ADMIN ? 'selected' : '' ?>>
                                            Administrator
                                        </option>
                                    </select>
                                </form>
                            </td>
                            <td>
                                <?php if ($user['aktiv']): ?>
                                    <span class="badge bg-success">Aktiv</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inaktiv</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $user['letzter_login'] ? date('d.m.Y H:i', strtotime($user['letzter_login'])) : '-' ?>
                            </td>
                            <td>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-primary" title="Bearbeiten"
                                            onclick="editBenutzer(<?= htmlspecialchars(json_encode($user)) ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button type="submit"
                                                class="btn btn-<?= $user['aktiv'] ? 'warning' : 'success' ?>"
                                                title="<?= $user['aktiv'] ? 'Deaktivieren' : 'Aktivieren' ?>">
                                            <i class="bi bi-<?= $user['aktiv'] ? 'pause' : 'play' ?>-fill"></i>
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline"
                                          onsubmit="return confirm('Benutzer wirklich löschen?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="btn btn-danger" title="Löschen">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                                <?php else: ?>
                                    <span class="text-muted small">(Sie selbst)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Neuer Benutzer -->
<div class="modal fade" id="benutzerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create">

                <div class="modal-header">
                    <h5 class="modal-title">Neuer Benutzer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vorname *</label>
                            <input type="text" class="form-control" name="vorname" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nachname *</label>
                            <input type="text" class="form-control" name="nachname" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Benutzername *</label>
                        <input type="text" class="form-control" name="benutzername" required minlength="3">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">E-Mail *</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Passwort *</label>
                        <input type="password" class="form-control" name="passwort" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rolle *</label>
                        <select class="form-select" name="rolle" required>
                            <option value="<?= ROLE_VIEWER ?>">Betrachter</option>
                            <option value="<?= ROLE_EDITOR ?>">Bearbeiter</option>
                            <option value="<?= ROLE_ADMIN ?>">Administrator</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Benutzer erstellen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Benutzer bearbeiten -->
<div class="modal fade" id="editBenutzerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" id="edit_user_id">

                <div class="modal-header">
                    <h5 class="modal-title">Benutzer bearbeiten</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vorname *</label>
                            <input type="text" class="form-control" name="vorname" id="edit_vorname" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nachname *</label>
                            <input type="text" class="form-control" name="nachname" id="edit_nachname" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">E-Mail *</label>
                        <input type="email" class="form-control" name="email" id="edit_email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rolle *</label>
                        <select class="form-select" name="rolle" id="edit_rolle" required>
                            <option value="<?= ROLE_VIEWER ?>">Betrachter</option>
                            <option value="<?= ROLE_EDITOR ?>">Bearbeiter</option>
                            <option value="<?= ROLE_ADMIN ?>">Administrator</option>
                        </select>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label class="form-label">Neues Passwort</label>
                        <input type="password" class="form-control" name="neues_passwort" id="edit_passwort" minlength="6">
                        <small class="text-muted">Leer lassen um das Passwort nicht zu ändern. Mindestens 6 Zeichen.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editBenutzer(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_vorname').value = user.vorname;
    document.getElementById('edit_nachname').value = user.nachname;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_rolle').value = user.rolle;
    document.getElementById('edit_passwort').value = '';

    new bootstrap.Modal(document.getElementById('editBenutzerModal')).show();
}
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
