<?php
/**
 * Gefährdungsbibliothek
 * Gespeicherte Gefährdungen zur Wiederverwendung
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

requireRole(ROLE_EDITOR);

$db = Database::getInstance();

// Tags laden
$tags = $db->fetchAll("SELECT * FROM gefaehrdung_tags ORDER BY sortierung");

// Aktion verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
        case 'update':
            $data = [
                'titel' => $_POST['titel'],
                'beschreibung' => $_POST['beschreibung'],
                'typische_massnahmen' => $_POST['typische_massnahmen'] ?: null,
                'standard_schadenschwere' => $_POST['standard_schadenschwere'] ?? 2,
                'standard_wahrscheinlichkeit' => $_POST['standard_wahrscheinlichkeit'] ?? 2,
                'ist_standard' => isset($_POST['ist_standard']) ? 1 : 0
            ];

            if (empty($data['titel']) || empty($data['beschreibung'])) {
                setFlashMessage('error', 'Titel und Beschreibung sind Pflichtfelder.');
            } else {
                if ($action === 'create') {
                    $data['erstellt_von'] = $_SESSION['user_id'];
                    $id = $db->insert('gefaehrdung_bibliothek', $data);

                    // Tags speichern
                    if (!empty($_POST['tags']) && is_array($_POST['tags'])) {
                        foreach ($_POST['tags'] as $tagId) {
                            $db->query("INSERT IGNORE INTO gefaehrdung_bibliothek_tags (gefaehrdung_id, tag_id) VALUES (?, ?)", [$id, $tagId]);
                        }
                    }

                    setFlashMessage('success', 'Gefährdung wurde zur Bibliothek hinzugefügt.');
                } else {
                    $id = $_POST['id'];
                    $db->update('gefaehrdung_bibliothek', $data, 'id = :id', ['id' => $id]);

                    // Tags aktualisieren
                    $db->delete('gefaehrdung_bibliothek_tags', 'gefaehrdung_id = ?', [$id]);
                    if (!empty($_POST['tags']) && is_array($_POST['tags'])) {
                        foreach ($_POST['tags'] as $tagId) {
                            $db->query("INSERT IGNORE INTO gefaehrdung_bibliothek_tags (gefaehrdung_id, tag_id) VALUES (?, ?)", [$id, $tagId]);
                        }
                    }

                    setFlashMessage('success', 'Gefährdung wurde aktualisiert.');
                }
            }
            break;

        case 'delete':
            $id = $_POST['id'];
            $db->delete('gefaehrdung_bibliothek_tags', 'gefaehrdung_id = ?', [$id]);
            $db->delete('gefaehrdung_bibliothek', 'id = ?', [$id]);
            setFlashMessage('success', 'Gefährdung wurde gelöscht.');
            break;
    }

    redirect('bibliothek/gefaehrdungen.php');
}

// Suche/Filter
$search = $_GET['q'] ?? '';
$tagFilter = $_GET['tag'] ?? '';

$sql = "
    SELECT gb.*,
           CONCAT(b.vorname, ' ', b.nachname) as erstellt_von_name,
           (SELECT COUNT(*) FROM projekt_gefaehrdungen WHERE gefaehrdung_bibliothek_id = gb.id) as verwendung_count
    FROM gefaehrdung_bibliothek gb
    LEFT JOIN benutzer b ON gb.erstellt_von = b.id
";

$params = [];
$where = [];

if ($search) {
    $where[] = "(gb.titel LIKE ? OR gb.beschreibung LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($tagFilter) {
    $sql .= " JOIN gefaehrdung_bibliothek_tags gbt ON gb.id = gbt.gefaehrdung_id";
    $where[] = "gbt.tag_id = ?";
    $params[] = $tagFilter;
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

$sql .= " ORDER BY gb.titel";

$gefaehrdungen = $db->fetchAll($sql, $params);

// Tags pro Gefährdung laden
$gefTagsMap = [];
$allGefTags = $db->fetchAll("
    SELECT gbt.gefaehrdung_id, gt.id, gt.name, gt.farbe
    FROM gefaehrdung_bibliothek_tags gbt
    JOIN gefaehrdung_tags gt ON gbt.tag_id = gt.id
");
foreach ($allGefTags as $gt) {
    $gefTagsMap[$gt['gefaehrdung_id']][] = $gt;
}

$pageTitle = 'Gefährdungsbibliothek';
require_once __DIR__ . '/../templates/header.php';

global $SCHADENSCHWERE, $WAHRSCHEINLICHKEIT;
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="bi bi-book me-2"></i>Gefährdungsbibliothek
            </h1>
            <p class="text-muted mb-0">Gespeicherte Gefährdungen zur Wiederverwendung in Projekten</p>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#gefaehrdungModal">
            <i class="bi bi-plus-lg me-2"></i>Neue Gefährdung
        </button>
    </div>

    <!-- Filter -->
    <div class="card mb-4">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <input type="text" name="q" class="form-control form-control-sm" placeholder="Suchen..."
                           value="<?= sanitize($search) ?>">
                </div>
                <div class="col-md-3">
                    <select name="tag" class="form-select form-select-sm">
                        <option value="">Alle Tags</option>
                        <?php foreach ($tags as $tag): ?>
                        <option value="<?= $tag['id'] ?>" <?= $tagFilter == $tag['id'] ? 'selected' : '' ?>>
                            <?= sanitize($tag['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-search me-1"></i>Filtern
                    </button>
                    <?php if ($search || $tagFilter): ?>
                    <a href="<?= BASE_URL ?>/bibliothek/gefaehrdungen.php" class="btn btn-sm btn-outline-secondary">
                        Zurücksetzen
                    </a>
                    <?php endif; ?>
                </div>
                <div class="col-auto ms-auto">
                    <span class="badge bg-secondary"><?= count($gefaehrdungen) ?> Einträge</span>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($gefaehrdungen)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-book display-4 text-muted"></i>
            <h5 class="mt-3">Keine Gefährdungen in der Bibliothek</h5>
            <p class="text-muted">Erstellen Sie neue Gefährdungen oder speichern Sie Gefährdungen aus Projekten in der Bibliothek.</p>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#gefaehrdungModal">
                <i class="bi bi-plus-lg me-2"></i>Erste Gefährdung erstellen
            </button>
        </div>
    </div>
    <?php else: ?>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 25%">Titel</th>
                        <th style="width: 30%">Beschreibung</th>
                        <th style="width: 15%">Maßnahmen</th>
                        <th style="width: 10%">Risiko</th>
                        <th style="width: 10%">Tags</th>
                        <th style="width: 5%">Verw.</th>
                        <th style="width: 5%"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gefaehrdungen as $gef): ?>
                    <tr>
                        <td>
                            <strong><?= sanitize($gef['titel']) ?></strong>
                            <?php if ($gef['ist_standard']): ?>
                            <br><span class="badge bg-success">Standard</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small><?= sanitize(substr($gef['beschreibung'], 0, 100)) ?><?= strlen($gef['beschreibung']) > 100 ? '...' : '' ?></small>
                        </td>
                        <td>
                            <?php if ($gef['typische_massnahmen']): ?>
                            <small class="text-muted"><?= sanitize(substr($gef['typische_massnahmen'], 0, 60)) ?>...</small>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $s = $gef['standard_schadenschwere'] ?? 2;
                            $w = $gef['standard_wahrscheinlichkeit'] ?? 2;
                            $r = $s * $s * $w;
                            $rColor = getRiskColor($r);
                            ?>
                            <span class="badge" style="background-color: <?= $rColor ?>; color: <?= $r >= 9 ? '#fff' : '#000' ?>">
                                R = <?= $r ?>
                            </span>
                            <br><small class="text-muted">S=<?= $s ?> W=<?= $w ?></small>
                        </td>
                        <td>
                            <?php if (!empty($gefTagsMap[$gef['id']])): ?>
                            <?php foreach ($gefTagsMap[$gef['id']] as $gt): ?>
                            <span class="badge" style="background-color: <?= $gt['farbe'] ?>; font-size: 0.65rem;"><?= sanitize($gt['name']) ?></span>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= $gef['verwendung_count'] > 0 ? 'primary' : 'secondary' ?>">
                                <?= $gef['verwendung_count'] ?>
                            </span>
                        </td>
                        <td>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-link text-muted" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#"
                                           onclick="editGefaehrdung(<?= htmlspecialchars(json_encode($gef)) ?>, <?= htmlspecialchars(json_encode($gefTagsMap[$gef['id']] ?? [])) ?>)">
                                            <i class="bi bi-pencil me-2"></i>Bearbeiten
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" onsubmit="return confirm('Gefährdung wirklich aus der Bibliothek löschen?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $gef['id'] ?>">
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="bi bi-trash me-2"></i>Löschen
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal: Gefährdung -->
<div class="modal fade" id="gefaehrdungModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" id="gef_action" value="create">
                <input type="hidden" name="id" id="gef_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Neue Gefährdung</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Titel *</label>
                        <input type="text" class="form-control" name="titel" id="gef_titel" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Beschreibung *</label>
                        <textarea class="form-control" name="beschreibung" id="gef_beschreibung" rows="3" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Typische Maßnahmen</label>
                        <textarea class="form-control" name="typische_massnahmen" id="gef_massnahmen" rows="3"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Standard-Schadenschwere</label>
                            <select class="form-select" name="standard_schadenschwere" id="gef_schadenschwere">
                                <?php foreach ($SCHADENSCHWERE as $val => $info): ?>
                                <option value="<?= $val ?>" <?= $val == 2 ? 'selected' : '' ?>><?= $val ?> - <?= $info['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Standard-Wahrscheinlichkeit</label>
                            <select class="form-select" name="standard_wahrscheinlichkeit" id="gef_wahrscheinlichkeit">
                                <?php foreach ($WAHRSCHEINLICHKEIT as $val => $info): ?>
                                <option value="<?= $val ?>" <?= $val == 2 ? 'selected' : '' ?>><?= $val ?> - <?= $info['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tags (für automatische Zuweisung)</label>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($tags as $tag): ?>
                            <div class="form-check">
                                <input class="form-check-input tag-check" type="checkbox" name="tags[]"
                                       value="<?= $tag['id'] ?>" id="tag_<?= $tag['id'] ?>">
                                <label class="form-check-label" for="tag_<?= $tag['id'] ?>">
                                    <span class="badge" style="background-color: <?= $tag['farbe'] ?>"><?= sanitize($tag['name']) ?></span>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="ist_standard" id="gef_ist_standard" value="1">
                        <label class="form-check-label" for="gef_ist_standard">
                            <strong>Als Standard-Gefährdung markieren</strong>
                            <br><small class="text-muted">Wird bei passenden Tags automatisch zu neuen Projekten hinzugefügt</small>
                        </label>
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
function editGefaehrdung(data, gefTags) {
    document.getElementById('gef_action').value = 'update';
    document.getElementById('gef_id').value = data.id;
    document.getElementById('modalTitle').textContent = 'Gefährdung bearbeiten';
    document.getElementById('gef_titel').value = data.titel;
    document.getElementById('gef_beschreibung').value = data.beschreibung;
    document.getElementById('gef_massnahmen').value = data.typische_massnahmen || '';
    document.getElementById('gef_schadenschwere').value = data.standard_schadenschwere || 2;
    document.getElementById('gef_wahrscheinlichkeit').value = data.standard_wahrscheinlichkeit || 2;
    document.getElementById('gef_ist_standard').checked = data.ist_standard == 1;

    // Tags setzen
    document.querySelectorAll('.tag-check').forEach(cb => cb.checked = false);
    if (gefTags && gefTags.length) {
        gefTags.forEach(tag => {
            const cb = document.getElementById('tag_' + tag.id);
            if (cb) cb.checked = true;
        });
    }

    new bootstrap.Modal(document.getElementById('gefaehrdungModal')).show();
}

document.getElementById('gefaehrdungModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('gef_action').value = 'create';
    document.getElementById('gef_id').value = '';
    document.getElementById('modalTitle').textContent = 'Neue Gefährdung';
    document.querySelectorAll('.tag-check').forEach(cb => cb.checked = false);
    this.querySelector('form').reset();
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
