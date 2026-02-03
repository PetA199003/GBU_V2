<?php
/**
 * Kategorien-Verwaltung (Admin)
 * Arbeits-Kategorien und Unterkategorien verwalten
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

requireRole(ROLE_ADMIN);

$db = Database::getInstance();

// Aktion verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create_kategorie':
            $maxNummer = $db->fetchOne("SELECT MAX(nummer) as max FROM arbeits_kategorien WHERE ist_global = 1");
            $neueNummer = ($maxNummer['max'] ?? 0) + 1;

            $db->insert('arbeits_kategorien', [
                'nummer' => $neueNummer,
                'name' => $_POST['name'],
                'beschreibung' => $_POST['beschreibung'] ?: null,
                'ist_global' => 1,
                'erstellt_von' => $_SESSION['user_id']
            ]);
            setFlashMessage('success', 'Kategorie wurde erstellt.');
            break;

        case 'update_kategorie':
            $db->update('arbeits_kategorien', [
                'name' => $_POST['name'],
                'beschreibung' => $_POST['beschreibung'] ?: null
            ], 'id = :id', ['id' => $_POST['id']]);
            setFlashMessage('success', 'Kategorie wurde aktualisiert.');
            break;

        case 'delete_kategorie':
            $id = $_POST['id'];
            // Prüfen ob Unterkategorien existieren
            $count = $db->fetchOne("SELECT COUNT(*) as cnt FROM arbeits_unterkategorien WHERE kategorie_id = ?", [$id])['cnt'];
            if ($count > 0) {
                setFlashMessage('error', 'Kategorie hat noch Unterkategorien und kann nicht gelöscht werden.');
            } else {
                $db->delete('arbeits_kategorien', 'id = ?', [$id]);
                setFlashMessage('success', 'Kategorie wurde gelöscht.');
            }
            break;

        case 'create_unterkategorie':
            $katId = $_POST['kategorie_id'];
            $maxNummer = $db->fetchOne("SELECT MAX(nummer) as max FROM arbeits_unterkategorien WHERE kategorie_id = ?", [$katId]);
            $neueNummer = ($maxNummer['max'] ?? 0) + 1;

            $db->insert('arbeits_unterkategorien', [
                'kategorie_id' => $katId,
                'nummer' => $neueNummer,
                'name' => $_POST['name'],
                'beschreibung' => $_POST['beschreibung'] ?: null,
                'erstellt_von' => $_SESSION['user_id']
            ]);
            setFlashMessage('success', 'Unterkategorie wurde erstellt.');
            break;

        case 'update_unterkategorie':
            $db->update('arbeits_unterkategorien', [
                'name' => $_POST['name'],
                'beschreibung' => $_POST['beschreibung'] ?: null
            ], 'id = :id', ['id' => $_POST['id']]);
            setFlashMessage('success', 'Unterkategorie wurde aktualisiert.');
            break;

        case 'delete_unterkategorie':
            $db->delete('arbeits_unterkategorien', 'id = ?', [$_POST['id']]);
            setFlashMessage('success', 'Unterkategorie wurde gelöscht.');
            break;

        case 'reorder':
            // Reihenfolge ändern
            $kategorieId = $_POST['kategorie_id'];
            $direction = $_POST['direction']; // up oder down

            $kat = $db->fetchOne("SELECT * FROM arbeits_kategorien WHERE id = ?", [$kategorieId]);
            if ($kat) {
                if ($direction === 'up' && $kat['nummer'] > 1) {
                    // Tausche mit vorheriger
                    $db->query("UPDATE arbeits_kategorien SET nummer = nummer + 1 WHERE nummer = ? AND ist_global = 1", [$kat['nummer'] - 1]);
                    $db->query("UPDATE arbeits_kategorien SET nummer = nummer - 1 WHERE id = ?", [$kategorieId]);
                } elseif ($direction === 'down') {
                    // Tausche mit nächster
                    $db->query("UPDATE arbeits_kategorien SET nummer = nummer - 1 WHERE nummer = ? AND ist_global = 1", [$kat['nummer'] + 1]);
                    $db->query("UPDATE arbeits_kategorien SET nummer = nummer + 1 WHERE id = ?", [$kategorieId]);
                }
            }
            break;
    }

    redirect('admin/kategorien.php');
}

// Kategorien mit Unterkategorien laden
$kategorien = $db->fetchAll("
    SELECT ak.*,
           (SELECT COUNT(*) FROM arbeits_unterkategorien WHERE kategorie_id = ak.id) as uk_count,
           (SELECT COUNT(*) FROM projekt_gefaehrdungen WHERE kategorie_id = ak.id) as verwendung_count
    FROM arbeits_kategorien ak
    WHERE ak.ist_global = 1
    ORDER BY ak.nummer
");

// Unterkategorien pro Kategorie laden
$unterkategorienMap = [];
$unterkategorien = $db->fetchAll("
    SELECT auk.*, ak.nummer as kat_nummer
    FROM arbeits_unterkategorien auk
    JOIN arbeits_kategorien ak ON auk.kategorie_id = ak.id
    WHERE ak.ist_global = 1
    ORDER BY ak.nummer, auk.nummer
");
foreach ($unterkategorien as $uk) {
    $unterkategorienMap[$uk['kategorie_id']][] = $uk;
}

$pageTitle = 'Kategorien-Verwaltung';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="bi bi-tags me-2"></i>Kategorien-Verwaltung
            </h1>
            <p class="text-muted mb-0">Arbeits-Kategorien für Gefährdungsbeurteilungen</p>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kategorieModal">
            <i class="bi bi-plus-lg me-2"></i>Neue Kategorie
        </button>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Kategorien</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($kategorien)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-tags display-6 mb-2"></i>
                        <p>Noch keine Kategorien vorhanden.</p>
                    </div>
                    <?php else: ?>
                    <div class="accordion" id="kategorienAccordion">
                        <?php foreach ($kategorien as $kat): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#kat_<?= $kat['id'] ?>">
                                    <span class="badge bg-primary me-2"><?= $kat['nummer'] ?></span>
                                    <strong><?= sanitize($kat['name']) ?></strong>
                                    <span class="badge bg-secondary ms-2"><?= $kat['uk_count'] ?> Unterkategorien</span>
                                </button>
                            </h2>
                            <div id="kat_<?= $kat['id'] ?>" class="accordion-collapse collapse" data-bs-parent="#kategorienAccordion">
                                <div class="accordion-body">
                                    <!-- Aktionen für Kategorie -->
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <?php if ($kat['beschreibung']): ?>
                                            <small class="text-muted"><?= sanitize($kat['beschreibung']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="btn-group btn-group-sm">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="reorder">
                                                <input type="hidden" name="kategorie_id" value="<?= $kat['id'] ?>">
                                                <input type="hidden" name="direction" value="up">
                                                <button type="submit" class="btn btn-outline-secondary" title="Nach oben">
                                                    <i class="bi bi-arrow-up"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="reorder">
                                                <input type="hidden" name="kategorie_id" value="<?= $kat['id'] ?>">
                                                <input type="hidden" name="direction" value="down">
                                                <button type="submit" class="btn btn-outline-secondary" title="Nach unten">
                                                    <i class="bi bi-arrow-down"></i>
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-outline-primary"
                                                    onclick="editKategorie(<?= htmlspecialchars(json_encode($kat)) ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php if ($kat['uk_count'] == 0 && $kat['verwendung_count'] == 0): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Kategorie wirklich löschen?')">
                                                <input type="hidden" name="action" value="delete_kategorie">
                                                <input type="hidden" name="id" value="<?= $kat['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Unterkategorien -->
                                    <h6 class="mb-2">Unterkategorien:</h6>
                                    <?php if (empty($unterkategorienMap[$kat['id']])): ?>
                                    <p class="text-muted small">Keine Unterkategorien vorhanden.</p>
                                    <?php else: ?>
                                    <ul class="list-group mb-3">
                                        <?php foreach ($unterkategorienMap[$kat['id']] as $uk): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>
                                                <span class="badge bg-secondary"><?= $kat['nummer'] ?>.<?= $uk['nummer'] ?></span>
                                                <?= sanitize($uk['name']) ?>
                                            </span>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary btn-sm"
                                                        onclick="editUnterkategorie(<?= htmlspecialchars(json_encode($uk)) ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Unterkategorie wirklich löschen?')">
                                                    <input type="hidden" name="action" value="delete_unterkategorie">
                                                    <input type="hidden" name="id" value="<?= $uk['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php endif; ?>

                                    <!-- Neue Unterkategorie hinzufügen -->
                                    <form method="POST" class="row g-2">
                                        <input type="hidden" name="action" value="create_unterkategorie">
                                        <input type="hidden" name="kategorie_id" value="<?= $kat['id'] ?>">
                                        <div class="col">
                                            <input type="text" class="form-control form-control-sm" name="name"
                                                   placeholder="Neue Unterkategorie..." required>
                                        </div>
                                        <div class="col-auto">
                                            <button type="submit" class="btn btn-sm btn-success">
                                                <i class="bi bi-plus"></i> Hinzufügen
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Info-Panel -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Info</h5>
                </div>
                <div class="card-body">
                    <p class="small">
                        <strong>Kategorien</strong> strukturieren die Arbeitsbereiche in Ihren Gefährdungsbeurteilungen.
                    </p>
                    <p class="small">
                        <strong>Beispiele:</strong>
                    </p>
                    <ul class="small">
                        <li>1. Allgemein</li>
                        <li>2. Be- und Entladen</li>
                        <li>3. Licht</li>
                        <li>4. Ton</li>
                        <li>5. Rigging</li>
                    </ul>
                    <p class="small">
                        <strong>Unterkategorien</strong> erlauben eine feinere Gliederung:
                    </p>
                    <ul class="small">
                        <li>2.1 Entladen über Rampe</li>
                        <li>2.2 Beladen LKW</li>
                        <li>5.1 Arbeiten auf Truss</li>
                    </ul>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Gefährdungsarten</h5>
                </div>
                <div class="card-body">
                    <p class="small text-muted">Die 13 Gefährdungsarten sind fest definiert:</p>
                    <ol class="small mb-0">
                        <?php
                        $gefaehrdungsarten = $db->fetchAll("SELECT * FROM gefaehrdungsarten ORDER BY nummer");
                        foreach ($gefaehrdungsarten as $ga):
                        ?>
                        <li><?= sanitize($ga['name']) ?></li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Neue Kategorie -->
<div class="modal fade" id="kategorieModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" id="kat_action" value="create_kategorie">
                <input type="hidden" name="id" id="kat_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="katModalTitle">Neue Kategorie</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name *</label>
                        <input type="text" class="form-control" name="name" id="kat_name" required
                               placeholder="z.B. Pyrotechnik">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Beschreibung</label>
                        <textarea class="form-control" name="beschreibung" id="kat_beschreibung" rows="2"
                                  placeholder="Optionale Beschreibung..."></textarea>
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

<!-- Modal: Unterkategorie bearbeiten -->
<div class="modal fade" id="unterkategorieModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update_unterkategorie">
                <input type="hidden" name="id" id="uk_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title">Unterkategorie bearbeiten</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name *</label>
                        <input type="text" class="form-control" name="name" id="uk_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Beschreibung</label>
                        <textarea class="form-control" name="beschreibung" id="uk_beschreibung" rows="2"></textarea>
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
function editKategorie(data) {
    document.getElementById('kat_action').value = 'update_kategorie';
    document.getElementById('kat_id').value = data.id;
    document.getElementById('katModalTitle').textContent = 'Kategorie bearbeiten';
    document.getElementById('kat_name').value = data.name;
    document.getElementById('kat_beschreibung').value = data.beschreibung || '';
    new bootstrap.Modal(document.getElementById('kategorieModal')).show();
}

function editUnterkategorie(data) {
    document.getElementById('uk_id').value = data.id;
    document.getElementById('uk_name').value = data.name;
    document.getElementById('uk_beschreibung').value = data.beschreibung || '';
    new bootstrap.Modal(document.getElementById('unterkategorieModal')).show();
}

document.getElementById('kategorieModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('kat_action').value = 'create_kategorie';
    document.getElementById('kat_id').value = '';
    document.getElementById('katModalTitle').textContent = 'Neue Kategorie';
    this.querySelector('form').reset();
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
