<?php
/**
 * Benutzerverwaltung (Admin)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Auth.php';

requireRole(ROLE_ADMIN);

$auth = new Auth();
$db = Database::getInstance();

// Aktion verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = $_POST['user_id'] ?? null;

    switch ($action) {
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
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Benutzerverwaltung</li>
                </ol>
            </nav>
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

    <!-- Rollen-Erklärung -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="bi bi-info-circle me-2"></i>Rollenübersicht</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="d-flex align-items-start">
                        <span class="badge bg-secondary me-2">Betrachter</span>
                        <small>Kann Gefährdungsbeurteilungen ansehen, aber nicht bearbeiten.</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-start">
                        <span class="badge bg-primary me-2">Bearbeiter</span>
                        <small>Kann Gefährdungsbeurteilungen erstellen und bearbeiten.</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-start">
                        <span class="badge bg-danger me-2">Administrator</span>
                        <small>Voller Zugriff inkl. Benutzerverwaltung und Systemeinstellungen.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
