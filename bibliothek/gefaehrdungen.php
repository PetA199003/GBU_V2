<?php
/**
 * Gefährdungsbibliothek
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Gefaehrdungsbeurteilung.php';

requireRole(ROLE_EDITOR);

$gbClass = new Gefaehrdungsbeurteilung();
$db = Database::getInstance();

// Kategorien laden
$kategorien = $gbClass->getKategorien();
$faktoren = $gbClass->getFaktoren();

// Aktion verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
        case 'update':
            $data = [
                'kategorie_id' => $_POST['kategorie_id'] ?: null,
                'faktor_id' => $_POST['faktor_id'] ?: null,
                'titel' => $_POST['titel'],
                'beschreibung' => $_POST['beschreibung'],
                'typische_massnahmen' => $_POST['typische_massnahmen'] ?: null,
                'gesetzliche_grundlage' => $_POST['gesetzliche_grundlage'] ?: null
            ];

            if (empty($data['titel']) || empty($data['beschreibung'])) {
                setFlashMessage('error', 'Titel und Beschreibung sind Pflichtfelder.');
            } else {
                if ($action === 'create') {
                    $data['erstellt_von'] = $_SESSION['user_id'];
                    $db->insert('gefaehrdung_bibliothek', $data);
                    setFlashMessage('success', 'Gefährdung wurde zur Bibliothek hinzugefügt.');
                } else {
                    $id = $_POST['id'];
                    $db->update('gefaehrdung_bibliothek', $data, 'id = :id', ['id' => $id]);
                    setFlashMessage('success', 'Gefährdung wurde aktualisiert.');
                }
            }
            break;

        case 'delete':
            $db->delete('gefaehrdung_bibliothek', 'id = ?', [$_POST['id']]);
            setFlashMessage('success', 'Gefährdung wurde gelöscht.');
            break;
    }

    redirect('bibliothek/gefaehrdungen.php');
}

// Filter
$kategorieFilter = $_GET['kategorie'] ?? null;

$sql = "
    SELECT gb.*, gk.name as kategorie_name, gf.name as faktor_name, gf.nummer as faktor_nummer,
           CONCAT(b.vorname, ' ', b.nachname) as erstellt_von_name
    FROM gefaehrdung_bibliothek gb
    LEFT JOIN gefaehrdung_kategorien gk ON gb.kategorie_id = gk.id
    LEFT JOIN gefaehrdung_faktoren gf ON gb.faktor_id = gf.id
    LEFT JOIN benutzer b ON gb.erstellt_von = b.id
";

$params = [];
if ($kategorieFilter) {
    $sql .= " WHERE gb.kategorie_id = ?";
    $params[] = $kategorieFilter;
}

$sql .= " ORDER BY gk.sortierung, gb.titel";

$gefaehrdungen = $db->fetchAll($sql, $params);

$pageTitle = 'Gefährdungsbibliothek';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="bi bi-exclamation-triangle me-2"></i>Gefährdungsbibliothek
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Gefährdungsbibliothek</li>
                </ol>
            </nav>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#gefaehrdungModal">
            <i class="bi bi-plus-lg me-2"></i>Neue Gefährdung
        </button>
    </div>

    <!-- Filter -->
    <div class="card mb-4">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-center">
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
                    <span class="badge bg-secondary"><?= count($gefaehrdungen) ?> Einträge</span>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($gefaehrdungen)): ?>
    <div class="card">
        <div class="card-body empty-state">
            <i class="bi bi-exclamation-triangle"></i>
            <h5>Keine Gefährdungen in der Bibliothek</h5>
            <p class="text-muted">Fügen Sie häufig verwendete Gefährdungen zur Bibliothek hinzu, um sie wiederverwenden zu können.</p>
        </div>
    </div>
    <?php else: ?>

    <div class="row">
        <?php foreach ($gefaehrdungen as $gef): ?>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 library-card">
                <div class="card-header d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-0"><?= sanitize($gef['titel']) ?></h6>
                        <?php if ($gef['kategorie_name']): ?>
                        <small class="text-muted"><?= sanitize($gef['kategorie_name']) ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-link text-muted" data-bs-toggle="dropdown">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="#"
                                   onclick="editGefaehrdung(<?= htmlspecialchars(json_encode($gef)) ?>)">
                                    <i class="bi bi-pencil me-2"></i>Bearbeiten
                                </a>
                            </li>
                            <li>
                                <form method="POST" class="d-inline"
                                      onsubmit="return confirm('Gefährdung wirklich löschen?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $gef['id'] ?>">
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="bi bi-trash me-2"></i>Löschen
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <p class="card-text small"><?= nl2br(sanitize($gef['beschreibung'])) ?></p>

                    <?php if ($gef['faktor_name']): ?>
                    <p class="mb-1">
                        <span class="badge bg-info"><?= sanitize($gef['faktor_nummer']) ?></span>
                        <small><?= sanitize($gef['faktor_name']) ?></small>
                    </p>
                    <?php endif; ?>

                    <?php if ($gef['typische_massnahmen']): ?>
                    <p class="card-text small text-muted mt-2">
                        <strong>Typische Maßnahmen:</strong><br>
                        <?= sanitize(substr($gef['typische_massnahmen'], 0, 150)) ?>...
                    </p>
                    <?php endif; ?>
                </div>
                <?php if ($gef['gesetzliche_grundlage']): ?>
                <div class="card-footer small text-muted">
                    <i class="bi bi-book me-1"></i><?= sanitize($gef['gesetzliche_grundlage']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal -->
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
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="kategorie_id" class="form-label">Kategorie</label>
                            <select class="form-select" id="kategorie_id" name="kategorie_id">
                                <option value="">-- Auswählen --</option>
                                <?php foreach ($kategorien as $kat): ?>
                                <option value="<?= $kat['id'] ?>"><?= sanitize($kat['nummer'] . ' - ' . $kat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="faktor_id" class="form-label">Gefährdungsfaktor</label>
                            <select class="form-select" id="faktor_id" name="faktor_id">
                                <option value="">-- Auswählen --</option>
                                <?php
                                $currentKat = null;
                                foreach ($faktoren as $f):
                                    if ($currentKat !== $f['kategorie_name']):
                                        if ($currentKat !== null) echo '</optgroup>';
                                        $currentKat = $f['kategorie_name'];
                                        echo '<optgroup label="' . sanitize($currentKat) . '">';
                                    endif;
                                ?>
                                <option value="<?= $f['id'] ?>"><?= sanitize($f['nummer'] . ' - ' . $f['name']) ?></option>
                                <?php endforeach; ?>
                                <?php if ($currentKat !== null) echo '</optgroup>'; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="titel" class="form-label">Titel *</label>
                        <input type="text" class="form-control" id="titel" name="titel" required>
                    </div>

                    <div class="mb-3">
                        <label for="beschreibung" class="form-label">Beschreibung *</label>
                        <textarea class="form-control" id="beschreibung" name="beschreibung" rows="4" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="typische_massnahmen" class="form-label">Typische Maßnahmen</label>
                        <textarea class="form-control" id="typische_massnahmen" name="typische_massnahmen" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="gesetzliche_grundlage" class="form-label">Gesetzliche Grundlage</label>
                        <input type="text" class="form-control" id="gesetzliche_grundlage" name="gesetzliche_grundlage"
                               placeholder="z.B. ArbSchG, ArbStättV, DGUV...">
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
function editGefaehrdung(data) {
    document.getElementById('gef_action').value = 'update';
    document.getElementById('gef_id').value = data.id;
    document.getElementById('modalTitle').textContent = 'Gefährdung bearbeiten';
    document.getElementById('kategorie_id').value = data.kategorie_id || '';
    document.getElementById('faktor_id').value = data.faktor_id || '';
    document.getElementById('titel').value = data.titel;
    document.getElementById('beschreibung').value = data.beschreibung;
    document.getElementById('typische_massnahmen').value = data.typische_massnahmen || '';
    document.getElementById('gesetzliche_grundlage').value = data.gesetzliche_grundlage || '';

    new bootstrap.Modal(document.getElementById('gefaehrdungModal')).show();
}

document.getElementById('gefaehrdungModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('gef_action').value = 'create';
    document.getElementById('gef_id').value = '';
    document.getElementById('modalTitle').textContent = 'Neue Gefährdung';
    this.querySelector('form').reset();
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
