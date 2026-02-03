<?php
/**
 * Unternehmensverwaltung
 */

require_once __DIR__ . '/../config/config.php';

requireRole(ROLE_ADMIN);

$db = Database::getInstance();

// Aktion verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
        case 'update':
            $data = [
                'name' => $_POST['name'] ?? '',
                'strasse' => $_POST['strasse'] ?? null,
                'plz' => $_POST['plz'] ?? null,
                'ort' => $_POST['ort'] ?? null,
                'telefon' => $_POST['telefon'] ?? null,
                'email' => $_POST['email'] ?? null
            ];

            if (empty($data['name'])) {
                setFlashMessage('error', 'Bitte geben Sie einen Unternehmensnamen ein.');
            } else {
                if ($action === 'create') {
                    $data['erstellt_von'] = $_SESSION['user_id'];
                    $db->insert('unternehmen', $data);
                    setFlashMessage('success', 'Unternehmen wurde erstellt.');
                } else {
                    $id = $_POST['id'];
                    $db->update('unternehmen', $data, 'id = :id', ['id' => $id]);
                    setFlashMessage('success', 'Unternehmen wurde aktualisiert.');
                }
            }
            break;

        case 'delete':
            $id = $_POST['id'];
            // Prüfen ob Gefährdungsbeurteilungen existieren
            $count = $db->fetchOne(
                "SELECT COUNT(*) as cnt FROM gefaehrdungsbeurteilungen WHERE unternehmen_id = ?",
                [$id]
            );
            if ($count['cnt'] > 0) {
                setFlashMessage('error', 'Unternehmen kann nicht gelöscht werden, da noch Gefährdungsbeurteilungen existieren.');
            } else {
                $db->delete('unternehmen', 'id = ?', [$id]);
                setFlashMessage('success', 'Unternehmen wurde gelöscht.');
            }
            break;
    }

    redirect('admin/unternehmen.php');
}

// Unternehmen laden
$unternehmen = $db->fetchAll("
    SELECT u.*, COUNT(g.id) as gb_count,
           CONCAT(b.vorname, ' ', b.nachname) as erstellt_von_name
    FROM unternehmen u
    LEFT JOIN gefaehrdungsbeurteilungen g ON u.id = g.unternehmen_id
    LEFT JOIN benutzer b ON u.erstellt_von = b.id
    GROUP BY u.id
    ORDER BY u.name
");

// Einzelnes Unternehmen für Bearbeitung
$editUnternehmen = null;
if (isset($_GET['edit'])) {
    $editUnternehmen = $db->fetchOne("SELECT * FROM unternehmen WHERE id = ?", [$_GET['edit']]);
}

$pageTitle = 'Unternehmensverwaltung';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="bi bi-building me-2"></i>Unternehmensverwaltung
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Unternehmensverwaltung</li>
                </ol>
            </nav>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#unternehmensModal">
            <i class="bi bi-plus-lg me-2"></i>Neues Unternehmen
        </button>
    </div>

    <div class="row">
        <?php if (empty($unternehmen)): ?>
        <div class="col-12">
            <div class="card">
                <div class="card-body empty-state">
                    <i class="bi bi-building"></i>
                    <h5>Keine Unternehmen vorhanden</h5>
                    <p class="text-muted">Erstellen Sie Ihr erstes Unternehmen, um Gefährdungsbeurteilungen zu verwalten.</p>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#unternehmensModal">
                        <i class="bi bi-plus-lg me-2"></i>Unternehmen erstellen
                    </button>
                </div>
            </div>
        </div>
        <?php else: ?>
        <?php foreach ($unternehmen as $u): ?>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title"><?= sanitize($u['name']) ?></h5>
                    <?php if ($u['strasse'] || $u['plz'] || $u['ort']): ?>
                    <p class="card-text text-muted mb-2">
                        <i class="bi bi-geo-alt me-1"></i>
                        <?= sanitize($u['strasse']) ?><br>
                        <?= sanitize($u['plz']) ?> <?= sanitize($u['ort']) ?>
                    </p>
                    <?php endif; ?>
                    <?php if ($u['telefon']): ?>
                    <p class="card-text mb-1">
                        <i class="bi bi-telephone me-1"></i><?= sanitize($u['telefon']) ?>
                    </p>
                    <?php endif; ?>
                    <?php if ($u['email']): ?>
                    <p class="card-text mb-2">
                        <i class="bi bi-envelope me-1"></i><?= sanitize($u['email']) ?>
                    </p>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-transparent">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="badge bg-primary">
                            <?= $u['gb_count'] ?> Beurteilung<?= $u['gb_count'] != 1 ? 'en' : '' ?>
                        </span>
                        <div class="btn-group btn-group-sm">
                            <a href="?edit=<?= $u['id'] ?>"
                               class="btn btn-outline-primary"
                               data-bs-toggle="modal"
                               data-bs-target="#unternehmensModal"
                               onclick="fillEditForm(<?= htmlspecialchars(json_encode($u)) ?>); return false;">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php if ($u['gb_count'] == 0): ?>
                            <form method="POST" class="d-inline"
                                  onsubmit="return confirm('Unternehmen wirklich löschen?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal für Unternehmen erstellen/bearbeiten -->
<div class="modal fade" id="unternehmensModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" id="modal-action" value="create">
                <input type="hidden" name="id" id="modal-id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="modal-title">Neues Unternehmen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Unternehmensname *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="strasse" class="form-label">Straße</label>
                        <input type="text" class="form-control" id="strasse" name="strasse">
                    </div>
                    <div class="row">
                        <div class="col-4 mb-3">
                            <label for="plz" class="form-label">PLZ</label>
                            <input type="text" class="form-control" id="plz" name="plz" maxlength="10">
                        </div>
                        <div class="col-8 mb-3">
                            <label for="ort" class="form-label">Ort</label>
                            <input type="text" class="form-control" id="ort" name="ort">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="telefon" class="form-label">Telefon</label>
                        <input type="tel" class="form-control" id="telefon" name="telefon">
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">E-Mail</label>
                        <input type="email" class="form-control" id="email" name="email">
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
function fillEditForm(data) {
    document.getElementById('modal-action').value = 'update';
    document.getElementById('modal-id').value = data.id;
    document.getElementById('modal-title').textContent = 'Unternehmen bearbeiten';
    document.getElementById('name').value = data.name || '';
    document.getElementById('strasse').value = data.strasse || '';
    document.getElementById('plz').value = data.plz || '';
    document.getElementById('ort').value = data.ort || '';
    document.getElementById('telefon').value = data.telefon || '';
    document.getElementById('email').value = data.email || '';
}

document.getElementById('unternehmensModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('modal-action').value = 'create';
    document.getElementById('modal-id').value = '';
    document.getElementById('modal-title').textContent = 'Neues Unternehmen';
    this.querySelector('form').reset();
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
