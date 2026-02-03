<?php
/**
 * Maßnahmenbibliothek
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Gefaehrdungsbeurteilung.php';

requireRole(ROLE_EDITOR);

$gbClass = new Gefaehrdungsbeurteilung();
$db = Database::getInstance();

// Kategorien laden
$kategorien = $gbClass->getKategorien();

// Aktion verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
        case 'update':
            $data = [
                'kategorie_id' => $_POST['kategorie_id'] ?: null,
                'titel' => $_POST['titel'],
                'beschreibung' => $_POST['beschreibung'],
                'stop_typ' => $_POST['stop_typ'],
                'gesetzliche_grundlage' => $_POST['gesetzliche_grundlage'] ?: null
            ];

            if (empty($data['titel']) || empty($data['beschreibung']) || empty($data['stop_typ'])) {
                setFlashMessage('error', 'Titel, Beschreibung und STOP-Typ sind Pflichtfelder.');
            } else {
                if ($action === 'create') {
                    $data['erstellt_von'] = $_SESSION['user_id'];
                    $db->insert('massnahmen_bibliothek', $data);
                    setFlashMessage('success', 'Maßnahme wurde zur Bibliothek hinzugefügt.');
                } else {
                    $id = $_POST['id'];
                    $db->update('massnahmen_bibliothek', $data, 'id = :id', ['id' => $id]);
                    setFlashMessage('success', 'Maßnahme wurde aktualisiert.');
                }
            }
            break;

        case 'delete':
            $db->delete('massnahmen_bibliothek', 'id = ?', [$_POST['id']]);
            setFlashMessage('success', 'Maßnahme wurde gelöscht.');
            break;
    }

    redirect('bibliothek/massnahmen.php');
}

// Filter
$stopFilter = $_GET['stop'] ?? null;
$kategorieFilter = $_GET['kategorie'] ?? null;

$sql = "
    SELECT mb.*, gk.name as kategorie_name,
           CONCAT(b.vorname, ' ', b.nachname) as erstellt_von_name
    FROM massnahmen_bibliothek mb
    LEFT JOIN gefaehrdung_kategorien gk ON mb.kategorie_id = gk.id
    LEFT JOIN benutzer b ON mb.erstellt_von = b.id
    WHERE 1=1
";

$params = [];
if ($stopFilter) {
    $sql .= " AND mb.stop_typ = ?";
    $params[] = $stopFilter;
}
if ($kategorieFilter) {
    $sql .= " AND mb.kategorie_id = ?";
    $params[] = $kategorieFilter;
}

$sql .= " ORDER BY gk.sortierung, mb.stop_typ, mb.titel";

$massnahmen = $db->fetchAll($sql, $params);

$pageTitle = 'Maßnahmenbibliothek';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="bi bi-check2-circle me-2"></i>Maßnahmenbibliothek
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Maßnahmenbibliothek</li>
                </ol>
            </nav>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#massnahmeModal">
            <i class="bi bi-plus-lg me-2"></i>Neue Maßnahme
        </button>
    </div>

    <!-- STOP-Legende -->
    <div class="stop-legend mb-4">
        <strong class="me-3">STOP-Prinzip:</strong>
        <div class="stop-legend-item">
            <div class="stop-legend-color" style="background-color: var(--stop-s);"></div>
            <span><strong>S</strong> - Substitution (Ersatz durch sichere Verfahren)</span>
        </div>
        <div class="stop-legend-item">
            <div class="stop-legend-color" style="background-color: var(--stop-t);"></div>
            <span><strong>T</strong> - Technische Lösungen</span>
        </div>
        <div class="stop-legend-item">
            <div class="stop-legend-color" style="background-color: var(--stop-o);"></div>
            <span><strong>O</strong> - Organisatorische Lösungen</span>
        </div>
        <div class="stop-legend-item">
            <div class="stop-legend-color" style="background-color: var(--stop-p);"></div>
            <span><strong>P</strong> - Persönliche Schutzausrüstung</span>
        </div>
    </div>

    <!-- Filter -->
    <div class="card mb-4">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-auto">
                    <label class="col-form-label">STOP-Typ:</label>
                </div>
                <div class="col-auto">
                    <select name="stop" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Alle</option>
                        <option value="S" <?= $stopFilter === 'S' ? 'selected' : '' ?>>S - Substitution</option>
                        <option value="T" <?= $stopFilter === 'T' ? 'selected' : '' ?>>T - Technisch</option>
                        <option value="O" <?= $stopFilter === 'O' ? 'selected' : '' ?>>O - Organisatorisch</option>
                        <option value="P" <?= $stopFilter === 'P' ? 'selected' : '' ?>>P - Persönlich</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="col-form-label">Kategorie:</label>
                </div>
                <div class="col-auto">
                    <select name="kategorie" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Alle Kategorien</option>
                        <?php foreach ($kategorien as $kat): ?>
                        <option value="<?= $kat['id'] ?>" <?= $kategorieFilter == $kat['id'] ? 'selected' : '' ?>>
                            <?= sanitize($kat['nummer'] . ' - ' . $kat['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <span class="badge bg-secondary"><?= count($massnahmen) ?> Einträge</span>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($massnahmen)): ?>
    <div class="card">
        <div class="card-body empty-state">
            <i class="bi bi-check2-circle"></i>
            <h5>Keine Maßnahmen in der Bibliothek</h5>
            <p class="text-muted">Fügen Sie häufig verwendete Maßnahmen zur Bibliothek hinzu, um sie wiederverwenden zu können.</p>
        </div>
    </div>
    <?php else: ?>

    <div class="row">
        <?php foreach ($massnahmen as $mass): ?>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 library-card">
                <div class="card-header d-flex justify-content-between align-items-start">
                    <div class="d-flex align-items-center gap-2">
                        <span class="stop-badge stop-<?= strtolower($mass['stop_typ']) ?>"><?= $mass['stop_typ'] ?></span>
                        <div>
                            <h6 class="mb-0"><?= sanitize($mass['titel']) ?></h6>
                            <?php if ($mass['kategorie_name']): ?>
                            <small class="text-muted"><?= sanitize($mass['kategorie_name']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-link text-muted" data-bs-toggle="dropdown">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="#"
                                   onclick="editMassnahme(<?= htmlspecialchars(json_encode($mass)) ?>)">
                                    <i class="bi bi-pencil me-2"></i>Bearbeiten
                                </a>
                            </li>
                            <li>
                                <form method="POST" class="d-inline"
                                      onsubmit="return confirm('Maßnahme wirklich löschen?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $mass['id'] ?>">
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="bi bi-trash me-2"></i>Löschen
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <p class="card-text small"><?= nl2br(sanitize($mass['beschreibung'])) ?></p>
                </div>
                <?php if ($mass['gesetzliche_grundlage']): ?>
                <div class="card-footer small text-muted">
                    <i class="bi bi-book me-1"></i><?= sanitize($mass['gesetzliche_grundlage']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal -->
<div class="modal fade" id="massnahmeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" id="mass_action" value="create">
                <input type="hidden" name="id" id="mass_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Neue Maßnahme</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="stop_typ" class="form-label">STOP-Typ *</label>
                            <select class="form-select" id="stop_typ" name="stop_typ" required>
                                <option value="">-- Auswählen --</option>
                                <option value="S">S - Substitution</option>
                                <option value="T">T - Technisch</option>
                                <option value="O">O - Organisatorisch</option>
                                <option value="P">P - Persönlich (PSA)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="kategorie_id" class="form-label">Kategorie</label>
                            <select class="form-select" id="kategorie_id" name="kategorie_id">
                                <option value="">-- Auswählen --</option>
                                <?php foreach ($kategorien as $kat): ?>
                                <option value="<?= $kat['id'] ?>"><?= sanitize($kat['nummer'] . ' - ' . $kat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="titel" class="form-label">Titel *</label>
                        <input type="text" class="form-control" id="titel" name="titel" required
                               placeholder="z.B. Ergonomische Bürostühle">
                    </div>

                    <div class="mb-3">
                        <label for="beschreibung" class="form-label">Beschreibung *</label>
                        <textarea class="form-control" id="beschreibung" name="beschreibung" rows="5" required
                                  placeholder="Detaillierte Beschreibung der Maßnahme..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="gesetzliche_grundlage" class="form-label">Gesetzliche Grundlage</label>
                        <input type="text" class="form-control" id="gesetzliche_grundlage" name="gesetzliche_grundlage"
                               placeholder="z.B. ArbSchG, ArbStättV, DGUV Information 215-410">
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
function editMassnahme(data) {
    document.getElementById('mass_action').value = 'update';
    document.getElementById('mass_id').value = data.id;
    document.getElementById('modalTitle').textContent = 'Maßnahme bearbeiten';
    document.getElementById('stop_typ').value = data.stop_typ;
    document.getElementById('kategorie_id').value = data.kategorie_id || '';
    document.getElementById('titel').value = data.titel;
    document.getElementById('beschreibung').value = data.beschreibung;
    document.getElementById('gesetzliche_grundlage').value = data.gesetzliche_grundlage || '';

    new bootstrap.Modal(document.getElementById('massnahmeModal')).show();
}

document.getElementById('massnahmeModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('mass_action').value = 'create';
    document.getElementById('mass_id').value = '';
    document.getElementById('modalTitle').textContent = 'Neue Maßnahme';
    this.querySelector('form').reset();
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
