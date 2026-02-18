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
    // Spalte logo_url zu firmen hinzufügen falls nicht vorhanden
    $logoColExists = $db->fetchOne("SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'firmen' AND COLUMN_NAME = 'logo_url'");
    if ($logoColExists['cnt'] == 0) {
        $db->query("ALTER TABLE firmen ADD COLUMN logo_url VARCHAR(500) DEFAULT NULL");
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

        case 'edit_firma':
            $firmaId = (int)$_POST['firma_id'];
            $updateData = [
                'name' => trim($_POST['firma_name'] ?? ''),
                'ort' => $_POST['firma_ort'] ?? null
            ];

            // Logo hochladen
            if (isset($_FILES['firma_logo']) && $_FILES['firma_logo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../uploads/logos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                // Altes Logo löschen
                $alteFirma = $db->fetchOne("SELECT logo_url FROM firmen WHERE id = ?", [$firmaId]);
                if ($alteFirma && $alteFirma['logo_url']) {
                    $oldFile = __DIR__ . '/..' . str_replace(BASE_URL, '', $alteFirma['logo_url']);
                    if (file_exists($oldFile)) {
                        @unlink($oldFile);
                    }
                }
                $ext = strtolower(pathinfo($_FILES['firma_logo']['name'], PATHINFO_EXTENSION));
                $filename = 'logo_' . $firmaId . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['firma_logo']['tmp_name'], $uploadDir . $filename)) {
                    $updateData['logo_url'] = BASE_URL . '/uploads/logos/' . $filename;
                }
            }

            // Logo löschen wenn gewünscht
            if (isset($_POST['delete_logo']) && $_POST['delete_logo'] == '1') {
                $alteFirma = $db->fetchOne("SELECT logo_url FROM firmen WHERE id = ?", [$firmaId]);
                if ($alteFirma && $alteFirma['logo_url']) {
                    $oldFile = __DIR__ . '/..' . str_replace(BASE_URL, '', $alteFirma['logo_url']);
                    if (file_exists($oldFile)) {
                        @unlink($oldFile);
                    }
                }
                $updateData['logo_url'] = null;
            }

            if (!empty($updateData['name'])) {
                $db->update('firmen', $updateData, 'id = :id', ['id' => $firmaId]);
                setFlashMessage('success', 'Unternehmen wurde aktualisiert.');
            }
            break;

        case 'delete_firma':
            $firmaId = (int)$_POST['firma_id'];
            // Prüfen ob noch Benutzer zugewiesen sind
            $userCount = $db->fetchOne("SELECT COUNT(*) as cnt FROM benutzer WHERE firma_id = ?", [$firmaId]);
            if ($userCount['cnt'] > 0) {
                setFlashMessage('error', 'Unternehmen kann nicht gelöscht werden, da noch Benutzer zugewiesen sind.');
            } else {
                // Logo löschen
                $alteFirma = $db->fetchOne("SELECT logo_url FROM firmen WHERE id = ?", [$firmaId]);
                if ($alteFirma && $alteFirma['logo_url']) {
                    $oldFile = __DIR__ . '/..' . str_replace(BASE_URL, '', $alteFirma['logo_url']);
                    if (file_exists($oldFile)) {
                        @unlink($oldFile);
                    }
                }
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

// Benutzer mit Firma laden
$users = $db->fetchAll("
    SELECT b.*, f.name as firma_name
    FROM benutzer b
    LEFT JOIN firmen f ON b.firma_id = f.id
    ORDER BY f.name, b.nachname, b.vorname
");

// Firmen neu laden (nach möglichen Änderungen)
$firmen = $db->fetchAll("SELECT * FROM firmen WHERE aktiv = 1 ORDER BY name");

$pageTitle = 'Benutzerverwaltung';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="bi bi-people me-2"></i>Benutzerverwaltung
            </h1>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#firmaModal">
                <i class="bi bi-building me-2"></i>Unternehmen verwalten
            </button>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#benutzerModal">
                <i class="bi bi-plus-lg me-2"></i>Neuer Benutzer
            </button>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Unternehmen</th>
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
                            <td>
                                <?php if ($user['firma_name']): ?>
                                <span class="badge bg-info"><?= sanitize($user['firma_name']) ?></span>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
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
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rolle *</label>
                            <select class="form-select" name="rolle" required>
                                <option value="<?= ROLE_VIEWER ?>">Betrachter</option>
                                <option value="<?= ROLE_EDITOR ?>">Bearbeiter</option>
                                <option value="<?= ROLE_ADMIN ?>">Administrator</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Unternehmen</label>
                            <select class="form-select" name="firma_id">
                                <option value="">-- Kein Unternehmen --</option>
                                <?php foreach ($firmen as $firma): ?>
                                <option value="<?= $firma['id'] ?>"><?= sanitize($firma['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
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
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rolle *</label>
                            <select class="form-select" name="rolle" id="edit_rolle" required>
                                <option value="<?= ROLE_VIEWER ?>">Betrachter</option>
                                <option value="<?= ROLE_EDITOR ?>">Bearbeiter</option>
                                <option value="<?= ROLE_ADMIN ?>">Administrator</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Unternehmen</label>
                            <select class="form-select" name="firma_id" id="edit_firma_id">
                                <option value="">-- Kein Unternehmen --</option>
                                <?php foreach ($firmen as $firma): ?>
                                <option value="<?= $firma['id'] ?>"><?= sanitize($firma['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
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

<!-- Modal: Unternehmen verwalten -->
<div class="modal fade" id="firmaModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-building me-2"></i>Unternehmen verwalten</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Neues Unternehmen -->
                <form method="POST" class="mb-4">
                    <input type="hidden" name="action" value="create_firma">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label">Name *</label>
                            <input type="text" class="form-control" name="firma_name" required placeholder="z.B. HABEGGER AG">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ort</label>
                            <input type="text" class="form-control" name="firma_ort" placeholder="z.B. Regensdorf">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-plus-lg me-1"></i>Hinzufügen
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Liste der Unternehmen -->
                <h6>Vorhandene Unternehmen</h6>
                <?php if (empty($firmen)): ?>
                <p class="text-muted">Noch keine Unternehmen vorhanden.</p>
                <?php else: ?>
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th style="width: 50px;">Logo</th>
                            <th>Name</th>
                            <th>Ort</th>
                            <th>Benutzer</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($firmen as $firma):
                            $userCount = $db->fetchOne("SELECT COUNT(*) as cnt FROM benutzer WHERE firma_id = ?", [$firma['id']]);
                        ?>
                        <tr>
                            <td>
                                <?php if (!empty($firma['logo_url'])): ?>
                                <img src="<?= sanitize($firma['logo_url']) ?>" alt="Logo" style="max-width: 40px; max-height: 40px; object-fit: contain;">
                                <?php else: ?>
                                <span class="text-muted"><i class="bi bi-image" style="font-size: 1.2rem;"></i></span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= sanitize($firma['name']) ?></strong></td>
                            <td><?= sanitize($firma['ort'] ?? '-') ?></td>
                            <td><span class="badge bg-secondary"><?= $userCount['cnt'] ?></span></td>
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-primary" title="Bearbeiten"
                                        onclick="editFirma(<?= $firma['id'] ?>, '<?= addslashes(sanitize($firma['name'])) ?>', '<?= addslashes(sanitize($firma['ort'] ?? '')) ?>', '<?= addslashes(sanitize($firma['logo_url'] ?? '')) ?>')">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php if ($userCount['cnt'] == 0): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Unternehmen wirklich löschen?')">
                                    <input type="hidden" name="action" value="delete_firma">
                                    <input type="hidden" name="firma_id" value="<?= $firma['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                                <?php else: ?>
                                <span class="text-muted small ms-1">Benutzer zugewiesen</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Unternehmen bearbeiten -->
<div class="modal fade" id="editFirmaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_firma">
                <input type="hidden" name="firma_id" id="editFirmaId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Unternehmen bearbeiten</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name *</label>
                        <input type="text" class="form-control" name="firma_name" id="editFirmaName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ort</label>
                        <input type="text" class="form-control" name="firma_ort" id="editFirmaOrt">
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label class="form-label">Aktuelles Logo</label>
                        <div id="editFirmaCurrentLogo" class="mb-2"></div>
                        <div id="editFirmaDeleteLogoDiv" class="form-check mb-2" style="display: none;">
                            <input class="form-check-input" type="checkbox" name="delete_logo" value="1" id="editFirmaDeleteLogo">
                            <label class="form-check-label text-danger" for="editFirmaDeleteLogo">
                                <i class="bi bi-trash me-1"></i>Logo entfernen
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Neues Logo hochladen</label>
                        <input type="file" class="form-control" name="firma_logo" accept="image/*">
                        <small class="text-muted">Empfohlen: PNG oder SVG mit transparentem Hintergrund, max. 500x200px</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-2"></i>Speichern
                    </button>
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
    document.getElementById('edit_firma_id').value = user.firma_id || '';
    document.getElementById('edit_passwort').value = '';

    new bootstrap.Modal(document.getElementById('editBenutzerModal')).show();
}

function editFirma(id, name, ort, logoUrl) {
    document.getElementById('editFirmaId').value = id;
    document.getElementById('editFirmaName').value = name;
    document.getElementById('editFirmaOrt').value = ort;
    document.getElementById('editFirmaDeleteLogo').checked = false;

    const currentLogoDiv = document.getElementById('editFirmaCurrentLogo');
    const deleteLogoDiv = document.getElementById('editFirmaDeleteLogoDiv');

    if (logoUrl) {
        currentLogoDiv.innerHTML = '<img src="' + logoUrl + '" alt="Logo" style="max-width: 150px; max-height: 80px; object-fit: contain; border: 1px solid #ddd; padding: 5px; border-radius: 4px;">';
        deleteLogoDiv.style.display = 'block';
    } else {
        currentLogoDiv.innerHTML = '<span class="text-muted"><i class="bi bi-image me-1"></i>Kein Logo vorhanden</span>';
        deleteLogoDiv.style.display = 'none';
    }

    // Erst das andere Modal schließen, dann das neue öffnen
    var firmaModal = bootstrap.Modal.getInstance(document.getElementById('firmaModal'));
    if (firmaModal) firmaModal.hide();

    setTimeout(function() {
        new bootstrap.Modal(document.getElementById('editFirmaModal')).show();
    }, 300);
}
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
