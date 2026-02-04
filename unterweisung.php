<?php
/**
 * Sicherheitsunterweisung - Verwaltung und Konfiguration
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

requireLogin();

$projektId = $_GET['projekt_id'] ?? null;

if (!$projektId) {
    setFlashMessage('error', 'Projekt-ID erforderlich');
    redirect('projekte.php');
}

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$isAdmin = hasRole(ROLE_ADMIN);

// Projekt laden
$projekt = $db->fetchOne("SELECT * FROM projekte WHERE id = ?", [$projektId]);
if (!$projekt) {
    setFlashMessage('error', 'Projekt nicht gefunden');
    redirect('projekte.php');
}

// Berechtigung pruefen
if (!$isAdmin) {
    $access = $db->fetchOne(
        "SELECT berechtigung FROM benutzer_projekte WHERE benutzer_id = ? AND projekt_id = ?",
        [$userId, $projektId]
    );
    if (!$access) {
        setFlashMessage('error', 'Keine Berechtigung');
        redirect('projekte.php');
    }
}

$canEdit = $isAdmin || ($access['berechtigung'] ?? '') === 'bearbeiten';

// Unterweisung laden oder erstellen
$unterweisung = $db->fetchOne("SELECT * FROM projekt_unterweisungen WHERE projekt_id = ?", [$projektId]);

if (!$unterweisung && $canEdit) {
    // Automatisch erstellen
    $unterweisungId = $db->insert('projekt_unterweisungen', [
        'projekt_id' => $projektId,
        'titel' => 'Sicherheitsunterweisung',
        'erstellt_von' => $userId
    ]);
    $unterweisung = $db->fetchOne("SELECT * FROM projekt_unterweisungen WHERE id = ?", [$unterweisungId]);
}

if (!$unterweisung) {
    setFlashMessage('error', 'Keine Unterweisung vorhanden');
    redirect('projekt.php?id=' . $projektId);
}

$unterweisungId = $unterweisung['id'];

// Aktionen verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_baustein':
            if ($isAdmin) {
                $bildUrl = null;
                // Bild hochladen
                if (isset($_FILES['bild']) && $_FILES['bild']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/uploads/piktogramme/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $ext = pathinfo($_FILES['bild']['name'], PATHINFO_EXTENSION);
                    $filename = 'piktogramm_' . time() . '_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($_FILES['bild']['tmp_name'], $uploadDir . $filename)) {
                        $bildUrl = BASE_URL . '/uploads/piktogramme/' . $filename;
                    }
                }

                $db->insert('unterweisungs_bausteine', [
                    'kategorie' => $_POST['kategorie'] ?? 'Sonstiges',
                    'titel' => $_POST['titel'] ?? '',
                    'inhalt' => $_POST['inhalt'] ?? '',
                    'bild_url' => $bildUrl,
                    'sortierung' => 100,
                    'aktiv' => 1
                ]);
                setFlashMessage('success', 'Baustein hinzugefuegt');
            }
            break;

        case 'delete_baustein':
            if ($isAdmin) {
                $db->delete('unterweisungs_bausteine', 'id = ?', [$_POST['baustein_id']]);
                setFlashMessage('success', 'Baustein geloescht');
            }
            break;

        case 'edit_baustein':
            if ($isAdmin) {
                $bildUrl = null;
                $bausteinId = $_POST['baustein_id'] ?? null;

                // Bestehenden Baustein laden
                $bestehendesBild = $db->fetchOne("SELECT bild_url FROM unterweisungs_bausteine WHERE id = ?", [$bausteinId]);

                // Neues Bild hochladen
                if (isset($_FILES['bild']) && $_FILES['bild']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/uploads/piktogramme/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $ext = pathinfo($_FILES['bild']['name'], PATHINFO_EXTENSION);
                    $filename = 'piktogramm_' . time() . '_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($_FILES['bild']['tmp_name'], $uploadDir . $filename)) {
                        $bildUrl = BASE_URL . '/uploads/piktogramme/' . $filename;
                    }
                } else {
                    // Bestehendes Bild behalten
                    $bildUrl = $bestehendesBild['bild_url'] ?? null;
                }

                $db->update('unterweisungs_bausteine', [
                    'kategorie' => $_POST['kategorie'] ?? 'Sonstiges',
                    'titel' => $_POST['titel'] ?? '',
                    'inhalt' => $_POST['inhalt'] ?? '',
                    'bild_url' => $bildUrl
                ], 'id = :id', ['id' => $bausteinId]);

                setFlashMessage('success', 'Baustein aktualisiert');
            }
            break;

        case 'update_settings':
            $db->update('projekt_unterweisungen', [
                'titel' => $_POST['titel'] ?? 'Sicherheitsunterweisung',
                'durchgefuehrt_von' => $_POST['durchgefuehrt_von'] ?? null,
                'durchgefuehrt_am' => $_POST['durchgefuehrt_am'] ?: null
            ], 'id = :id', ['id' => $unterweisungId]);
            setFlashMessage('success', 'Einstellungen gespeichert');
            break;

        case 'save_bausteine':
            // Alle alten Bausteine loeschen
            $db->delete('unterweisung_bausteine', 'unterweisung_id = ?', [$unterweisungId]);

            // Neue Bausteine speichern
            $bausteinIds = $_POST['bausteine'] ?? [];
            $sortierung = 1;
            foreach ($bausteinIds as $bausteinId) {
                $db->insert('unterweisung_bausteine', [
                    'unterweisung_id' => $unterweisungId,
                    'baustein_id' => $bausteinId,
                    'sortierung' => $sortierung++
                ]);
            }
            setFlashMessage('success', count($bausteinIds) . ' Bausteine gespeichert');
            break;

        case 'add_teilnehmer':
            $db->insert('unterweisung_teilnehmer', [
                'unterweisung_id' => $unterweisungId,
                'vorname' => $_POST['vorname'] ?? '',
                'nachname' => $_POST['nachname'] ?? '',
                'firma' => $_POST['firma'] ?? null
            ]);
            setFlashMessage('success', 'Teilnehmer hinzugefuegt');
            break;

        case 'delete_teilnehmer':
            $db->delete('unterweisung_teilnehmer', 'id = ? AND unterweisung_id = ?', [$_POST['teilnehmer_id'], $unterweisungId]);
            setFlashMessage('success', 'Teilnehmer entfernt');
            break;

        case 'delete_unterschrift':
            if ($isAdmin) {
                $db->update('unterweisung_teilnehmer', [
                    'unterschrift' => null,
                    'unterschrieben_am' => null
                ], 'id = :id AND unterweisung_id = :uid', [
                    'id' => $_POST['teilnehmer_id'],
                    'uid' => $unterweisungId
                ]);
                setFlashMessage('success', 'Unterschrift geloescht');
            }
            break;

        case 'add_projekt_baustein':
            // Bearbeiter und Admin koennen projekt-spezifische Bausteine hinzufuegen
            $bildUrl = null;
            if (isset($_FILES['bild']) && $_FILES['bild']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/uploads/piktogramme/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $ext = pathinfo($_FILES['bild']['name'], PATHINFO_EXTENSION);
                $filename = 'piktogramm_' . time() . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['bild']['tmp_name'], $uploadDir . $filename)) {
                    $bildUrl = BASE_URL . '/uploads/piktogramme/' . $filename;
                }
            }

            // Baustein als projekt-spezifisch speichern
            $newBausteinId = $db->insert('unterweisungs_bausteine', [
                'kategorie' => 'Projektspezifisch',
                'titel' => $_POST['titel'] ?? '',
                'inhalt' => $_POST['inhalt'] ?? '',
                'bild_url' => $bildUrl,
                'sortierung' => 200,
                'aktiv' => 1,
                'projekt_id' => $projektId  // Nur fuer dieses Projekt
            ]);

            // Direkt zur Unterweisung hinzufuegen
            $maxSort = $db->fetchOne("SELECT MAX(sortierung) as max FROM unterweisung_bausteine WHERE unterweisung_id = ?", [$unterweisungId]);
            $db->insert('unterweisung_bausteine', [
                'unterweisung_id' => $unterweisungId,
                'baustein_id' => $newBausteinId,
                'sortierung' => ($maxSort['max'] ?? 0) + 1
            ]);

            setFlashMessage('success', 'Projektspezifischer Inhalt hinzugefuegt');
            break;

        case 'import_csv':
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
                $imported = 0;
                $firstRow = true;

                while (($data = fgetcsv($handle, 1000, ';')) !== false) {
                    // Erste Zeile ueberspringen wenn Header
                    if ($firstRow && (stripos($data[0], 'name') !== false || stripos($data[0], 'vorname') !== false)) {
                        $firstRow = false;
                        continue;
                    }
                    $firstRow = false;

                    if (count($data) >= 2 && !empty(trim($data[0])) && !empty(trim($data[1]))) {
                        $db->insert('unterweisung_teilnehmer', [
                            'unterweisung_id' => $unterweisungId,
                            'vorname' => trim($data[0]),
                            'nachname' => trim($data[1]),
                            'firma' => isset($data[2]) ? trim($data[2]) : null
                        ]);
                        $imported++;
                    }
                }
                fclose($handle);
                setFlashMessage('success', $imported . ' Teilnehmer importiert');
            }
            break;

        case 'clear_teilnehmer':
            $db->delete('unterweisung_teilnehmer', 'unterweisung_id = ?', [$unterweisungId]);
            setFlashMessage('success', 'Alle Teilnehmer entfernt');
            break;
    }

    redirect('unterweisung.php?projekt_id=' . $projektId);
}

// Alle Bausteine laden (globale + projekt-spezifische)
$alleBausteine = $db->fetchAll("
    SELECT * FROM unterweisungs_bausteine
    WHERE aktiv = 1 AND (projekt_id IS NULL OR projekt_id = ?)
    ORDER BY sortierung, kategorie, titel
", [$projektId]);

// Bausteine nach Kategorie gruppieren
$bausteineNachKategorie = [];
foreach ($alleBausteine as $b) {
    if (!isset($bausteineNachKategorie[$b['kategorie']])) {
        $bausteineNachKategorie[$b['kategorie']] = [];
    }
    $bausteineNachKategorie[$b['kategorie']][] = $b;
}

// Ausgewaehlte Bausteine laden
$ausgewaehlteBausteine = $db->fetchAll("
    SELECT baustein_id FROM unterweisung_bausteine
    WHERE unterweisung_id = ?
    ORDER BY sortierung
", [$unterweisungId]);
$ausgewaehlteIds = array_column($ausgewaehlteBausteine, 'baustein_id');

// Teilnehmer laden (nach Nachname sortiert)
$teilnehmer = $db->fetchAll("
    SELECT * FROM unterweisung_teilnehmer
    WHERE unterweisung_id = ?
    ORDER BY nachname, vorname
", [$unterweisungId]);

// Ersteller
$ersteller = $db->fetchOne("SELECT vorname, nachname FROM benutzer WHERE id = ?", [$projekt['erstellt_von']]);
$erstellerName = $ersteller ? $ersteller['vorname'] . ' ' . $ersteller['nachname'] : '';

$pageTitle = 'Sicherheitsunterweisung - ' . $projekt['name'];
require_once __DIR__ . '/templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="bi bi-clipboard-check me-2"></i>Sicherheitsunterweisung
            </h1>
            <p class="text-muted mb-0">
                Projekt: <?= sanitize($projekt['name']) ?> | <?= sanitize($projekt['location']) ?>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>/projekt.php?id=<?= $projektId ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Zurueck zum Projekt
            </a>
            <a href="<?= BASE_URL ?>/api/export_unterweisung.php?id=<?= $unterweisungId ?>&type=unterweisung" class="btn btn-success" target="_blank">
                <i class="bi bi-file-pdf me-2"></i>Unterweisung drucken
            </a>
            <a href="<?= BASE_URL ?>/api/export_unterweisung.php?id=<?= $unterweisungId ?>&type=teilnehmerliste" class="btn btn-primary" target="_blank">
                <i class="bi bi-file-pdf me-2"></i>Teilnehmerliste drucken
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Linke Spalte: Einstellungen und Bausteine -->
        <div class="col-lg-7 mb-4">
            <!-- Einstellungen -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-gear me-2"></i>Einstellungen</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_settings">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Durchgefuehrt von</label>
                                <input type="text" class="form-control" name="durchgefuehrt_von"
                                       value="<?= sanitize($unterweisung['durchgefuehrt_von'] ?? $erstellerName) ?>"
                                       <?= !$canEdit ? 'disabled' : '' ?>>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Datum</label>
                                <input type="date" class="form-control" name="durchgefuehrt_am"
                                       value="<?= $unterweisung['durchgefuehrt_am'] ?? date('Y-m-d') ?>"
                                       <?= !$canEdit ? 'disabled' : '' ?>>
                            </div>
                        </div>
                        <?php if ($canEdit): ?>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check me-2"></i>Speichern
                        </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Bausteine auswaehlen -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Inhalte auswaehlen</h5>
                    <div class="d-flex gap-2">
                        <?php if ($canEdit): ?>
                        <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#addProjektBausteinModal">
                            <i class="bi bi-plus-lg me-1"></i>Projektinhalt
                        </button>
                        <?php endif; ?>
                        <?php if ($isAdmin): ?>
                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addBausteinModal">
                            <i class="bi bi-plus-lg me-1"></i>Globaler Inhalt
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" id="bausteineForm">
                        <input type="hidden" name="action" value="save_bausteine">

                        <div class="accordion" id="bausteineAccordion">
                            <?php $accIndex = 0; foreach ($bausteineNachKategorie as $kategorie => $bausteine): $accIndex++; ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button <?= $accIndex > 1 ? 'collapsed' : '' ?>" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#kat<?= $accIndex ?>">
                                        <?= sanitize($kategorie) ?>
                                        <span class="badge bg-primary ms-2 kat-count" data-kat="<?= $accIndex ?>">
                                            <?= count(array_filter($bausteine, fn($b) => in_array($b['id'], $ausgewaehlteIds))) ?>
                                        </span>
                                    </button>
                                </h2>
                                <div id="kat<?= $accIndex ?>" class="accordion-collapse collapse <?= $accIndex === 1 ? 'show' : '' ?>">
                                    <div class="accordion-body">
                                        <?php foreach ($bausteine as $b): ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input baustein-check" type="checkbox"
                                                   name="bausteine[]" value="<?= $b['id'] ?>"
                                                   id="baustein<?= $b['id'] ?>"
                                                   data-kat="<?= $accIndex ?>"
                                                   <?= in_array($b['id'], $ausgewaehlteIds) ? 'checked' : '' ?>
                                                   <?= !$canEdit ? 'disabled' : '' ?>>
                                            <label class="form-check-label d-flex align-items-center" for="baustein<?= $b['id'] ?>">
                                                <?php if ($b['bild_url']): ?>
                                                <img src="<?= sanitize($b['bild_url']) ?>" alt="Piktogramm" class="me-2" style="width: 28px; height: 28px; object-fit: contain;">
                                                <?php endif; ?>
                                                <strong><?= sanitize($b['titel']) ?></strong>
                                            </label>
                                            <button type="button" class="btn btn-sm btn-link p-0 ms-2"
                                                    data-bs-toggle="collapse" data-bs-target="#preview<?= $b['id'] ?>">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <?php if ($isAdmin): ?>
                                            <button type="button" class="btn btn-sm btn-link text-primary p-0 ms-1" title="Bearbeiten"
                                                    onclick="editBaustein(<?= $b['id'] ?>, '<?= addslashes(sanitize($b['kategorie'])) ?>', '<?= addslashes(sanitize($b['titel'])) ?>', `<?= addslashes(sanitize($b['inhalt'])) ?>`, '<?= addslashes(sanitize($b['bild_url'] ?? '')) ?>')">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-1" title="Loeschen"
                                                    onclick="deleteBaustein(<?= $b['id'] ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                            <div class="collapse mt-2" id="preview<?= $b['id'] ?>">
                                                <div class="card card-body bg-light small d-flex flex-row">
                                                    <?php if ($b['bild_url']): ?>
                                                    <img src="<?= sanitize($b['bild_url']) ?>" alt="Piktogramm" class="me-3" style="max-width: 200px; max-height: 200px; object-fit: contain;">
                                                    <?php endif; ?>
                                                    <div><?= nl2br(sanitize($b['inhalt'])) ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($canEdit): ?>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-save me-2"></i>Auswahl speichern
                            </button>
                            <button type="button" class="btn btn-outline-secondary ms-2" onclick="selectAll(true)">
                                Alle auswaehlen
                            </button>
                            <button type="button" class="btn btn-outline-secondary ms-2" onclick="selectAll(false)">
                                Alle abwaehlen
                            </button>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- Rechte Spalte: Teilnehmerliste -->
        <div class="col-lg-5 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-people me-2"></i>Teilnehmerliste (<?= count($teilnehmer) ?>)</h5>
                    <a href="<?= BASE_URL ?>/signatur.php?id=<?= $unterweisungId ?>" class="btn btn-sm btn-success" target="_blank">
                        <i class="bi bi-pencil-square me-1"></i>Digital unterschreiben
                    </a>
                </div>
                <div class="card-body">
                    <?php if ($canEdit): ?>
                    <!-- Manuell hinzufuegen -->
                    <form method="POST" class="mb-3">
                        <input type="hidden" name="action" value="add_teilnehmer">
                        <div class="row g-2">
                            <div class="col-4">
                                <input type="text" class="form-control form-control-sm" name="vorname" placeholder="Vorname" required>
                            </div>
                            <div class="col-4">
                                <input type="text" class="form-control form-control-sm" name="nachname" placeholder="Nachname" required>
                            </div>
                            <div class="col-3">
                                <input type="text" class="form-control form-control-sm" name="firma" placeholder="Firma">
                            </div>
                            <div class="col-1">
                                <button type="submit" class="btn btn-sm btn-success w-100" title="Hinzufuegen">
                                    <i class="bi bi-plus"></i>
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- CSV Import -->
                    <div class="mb-3">
                        <form method="POST" enctype="multipart/form-data" class="d-flex gap-2">
                            <input type="hidden" name="action" value="import_csv">
                            <input type="file" class="form-control form-control-sm" name="csv_file" accept=".csv" required>
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-upload me-1"></i>CSV Import
                            </button>
                        </form>
                        <small class="text-muted">Format: Vorname;Nachname;Firma (optional)</small>
                    </div>

                    <hr>
                    <?php endif; ?>

                    <!-- Teilnehmerliste -->
                    <?php if (empty($teilnehmer)): ?>
                    <p class="text-muted text-center">Noch keine Teilnehmer erfasst</p>
                    <?php else: ?>
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Name</th>
                                    <th>Firma</th>
                                    <th class="text-center">Unterschrift</th>
                                    <?php if ($canEdit): ?><th></th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($teilnehmer as $t): ?>
                                <tr>
                                    <td><?= sanitize($t['nachname']) ?>, <?= sanitize($t['vorname']) ?></td>
                                    <td class="small text-muted"><?= sanitize($t['firma'] ?? '-') ?></td>
                                    <td class="text-center">
                                        <?php if ($t['unterschrift']): ?>
                                        <button type="button" class="btn btn-sm btn-success p-1"
                                                onclick="showSignature('<?= sanitize($t['vorname'] . ' ' . $t['nachname']) ?>', '<?= date('d.m.Y H:i', strtotime($t['unterschrieben_am'])) ?>', '<?= $t['unterschrift'] ?>', <?= $t['id'] ?>)"
                                                title="Unterschrift anzeigen">
                                            <i class="bi bi-check"></i> <?= date('d.m.', strtotime($t['unterschrieben_am'])) ?>
                                        </button>
                                        <?php else: ?>
                                        <span class="badge bg-warning text-dark">Offen</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($canEdit): ?>
                                    <td>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Teilnehmer entfernen?')">
                                            <input type="hidden" name="action" value="delete_teilnehmer">
                                            <input type="hidden" name="teilnehmer_id" value="<?= $t['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-link text-danger p-0">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($canEdit): ?>
                    <div class="mt-3">
                        <form method="POST" onsubmit="return confirm('Alle Teilnehmer wirklich loeschen?')">
                            <input type="hidden" name="action" value="clear_teilnehmer">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash me-1"></i>Alle entfernen
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Eigenen Baustein hinzufuegen -->
<?php if ($isAdmin): ?>
<div class="modal fade" id="addBausteinModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_baustein">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Eigenen Inhalt hinzufuegen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Kategorie</label>
                        <select class="form-select" name="kategorie" id="kategorieSelect">
                            <?php
                            $kategorien = array_keys($bausteineNachKategorie);
                            foreach ($kategorien as $kat): ?>
                            <option value="<?= sanitize($kat) ?>"><?= sanitize($kat) ?></option>
                            <?php endforeach; ?>
                            <option value="__neu__">+ Neue Kategorie...</option>
                        </select>
                    </div>
                    <div class="mb-3" id="neueKategorieDiv" style="display: none;">
                        <label class="form-label">Neue Kategorie</label>
                        <input type="text" class="form-control" id="neueKategorie" placeholder="z.B. Spezielle Gefahren">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Titel *</label>
                        <input type="text" class="form-control" name="titel" required placeholder="z.B. Arbeiten mit Chemikalien">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Inhalt / Beschreibung *</label>
                        <textarea class="form-control" name="inhalt" rows="6" required placeholder="• Punkt 1&#10;• Punkt 2&#10;• Punkt 3"></textarea>
                        <small class="text-muted">Fuer Aufzaehlungen verwenden Sie • am Zeilenanfang</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Piktogramm / Bild (optional)</label>
                        <input type="file" class="form-control" name="bild" accept="image/*">
                        <small class="text-muted">Empfohlene Groesse: 100x100 Pixel, PNG oder JPG</small>
                    </div>
                    <div id="bildPreview" class="mb-3" style="display: none;">
                        <label class="form-label">Vorschau:</label><br>
                        <img id="previewImg" src="" alt="Vorschau" style="max-width: 100px; max-height: 100px; border: 1px solid #ddd; padding: 5px;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-plus-lg me-2"></i>Hinzufuegen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Modal: Baustein bearbeiten -->
<div class="modal fade" id="editBausteinModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_baustein">
                <input type="hidden" name="baustein_id" id="editBausteinId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Baustein bearbeiten</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Kategorie</label>
                        <select class="form-select" name="kategorie" id="editKategorieSelect">
                            <?php foreach ($kategorien as $kat): ?>
                            <option value="<?= sanitize($kat) ?>"><?= sanitize($kat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Titel *</label>
                        <input type="text" class="form-control" name="titel" id="editTitel" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Inhalt / Beschreibung *</label>
                        <textarea class="form-control" name="inhalt" id="editInhalt" rows="6" required></textarea>
                        <small class="text-muted">Fuer Aufzaehlungen verwenden Sie • am Zeilenanfang</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Aktuelles Piktogramm</label>
                        <div id="editCurrentBild" class="mb-2"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Neues Piktogramm (optional)</label>
                        <input type="file" class="form-control" name="bild" accept="image/*">
                        <small class="text-muted">Leer lassen um bestehendes Bild zu behalten</small>
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
<?php endif; ?>

<!-- Modal: Projektspezifischen Baustein hinzufuegen -->
<?php if ($canEdit): ?>
<div class="modal fade" id="addProjektBausteinModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_projekt_baustein">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Projektspezifischen Inhalt hinzufuegen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small">
                        <i class="bi bi-info-circle me-1"></i>
                        Dieser Inhalt wird nur fuer dieses Projekt erstellt und ist nicht in anderen Projekten sichtbar.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Titel *</label>
                        <input type="text" class="form-control" name="titel" required placeholder="z.B. Spezielle Baustellenregeln">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Inhalt / Beschreibung *</label>
                        <textarea class="form-control" name="inhalt" rows="6" required placeholder="• Punkt 1&#10;• Punkt 2&#10;• Punkt 3"></textarea>
                        <small class="text-muted">Fuer Aufzaehlungen verwenden Sie • am Zeilenanfang</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Piktogramm / Bild (optional)</label>
                        <input type="file" class="form-control" name="bild" accept="image/*">
                        <small class="text-muted">Empfohlene Groesse: 100x100 Pixel, PNG oder JPG</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-plus-lg me-2"></i>Hinzufuegen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Verstecktes Formular fuer Baustein-Loeschung (ausserhalb des Hauptformulars) -->
<?php if ($isAdmin): ?>
<form method="POST" id="deleteBausteinForm" style="display: none;">
    <input type="hidden" name="action" value="delete_baustein">
    <input type="hidden" name="baustein_id" id="deleteBausteinId">
</form>
<?php endif; ?>

<!-- Modal: Unterschrift anzeigen -->
<div class="modal fade" id="signatureModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pen me-2"></i>Unterschrift</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <p class="mb-2"><strong id="sigName"></strong></p>
                <p class="text-muted small mb-3">Unterschrieben am: <span id="sigDate"></span></p>
                <div class="border rounded p-3 bg-light">
                    <img id="sigImage" src="" alt="Unterschrift" style="max-width: 100%; max-height: 200px;">
                </div>
            </div>
            <div class="modal-footer">
                <?php if ($isAdmin): ?>
                <form method="POST" id="deleteSignatureForm" onsubmit="return confirm('Unterschrift wirklich loeschen?')">
                    <input type="hidden" name="action" value="delete_unterschrift">
                    <input type="hidden" name="teilnehmer_id" id="sigTeilnehmerId">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-2"></i>Unterschrift loeschen
                    </button>
                </form>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schliessen</button>
            </div>
        </div>
    </div>
</div>

<script>
function showSignature(name, date, imageData, teilnehmerId) {
    document.getElementById('sigName').textContent = name;
    document.getElementById('sigDate').textContent = date;
    document.getElementById('sigImage').src = imageData;
    document.getElementById('sigTeilnehmerId').value = teilnehmerId;
    new bootstrap.Modal(document.getElementById('signatureModal')).show();
}

function deleteBaustein(bausteinId) {
    if (confirm('Baustein wirklich loeschen?')) {
        document.getElementById('deleteBausteinId').value = bausteinId;
        document.getElementById('deleteBausteinForm').submit();
    }
}

function editBaustein(id, kategorie, titel, inhalt, bildUrl) {
    document.getElementById('editBausteinId').value = id;
    document.getElementById('editKategorieSelect').value = kategorie;
    document.getElementById('editTitel').value = titel;
    document.getElementById('editInhalt').value = inhalt;

    const currentBildDiv = document.getElementById('editCurrentBild');
    if (bildUrl) {
        currentBildDiv.innerHTML = `<img src="${bildUrl}" alt="Aktuelles Piktogramm" style="max-width: 80px; max-height: 80px; border: 1px solid #ddd; padding: 5px;">`;
    } else {
        currentBildDiv.innerHTML = '<span class="text-muted">Kein Bild vorhanden</span>';
    }

    new bootstrap.Modal(document.getElementById('editBausteinModal')).show();
}

function selectAll(checked) {
    document.querySelectorAll('.baustein-check').forEach(cb => {
        cb.checked = checked;
    });
    updateCounts();
}

function updateCounts() {
    document.querySelectorAll('.kat-count').forEach(badge => {
        const kat = badge.dataset.kat;
        const count = document.querySelectorAll(`.baustein-check[data-kat="${kat}"]:checked`).length;
        badge.textContent = count;
    });
}

document.querySelectorAll('.baustein-check').forEach(cb => {
    cb.addEventListener('change', updateCounts);
});

// Kategorie-Auswahl
const kategorieSelect = document.getElementById('kategorieSelect');
const neueKategorieDiv = document.getElementById('neueKategorieDiv');
const neueKategorieInput = document.getElementById('neueKategorie');

if (kategorieSelect) {
    kategorieSelect.addEventListener('change', function() {
        if (this.value === '__neu__') {
            neueKategorieDiv.style.display = 'block';
            neueKategorieInput.required = true;
        } else {
            neueKategorieDiv.style.display = 'none';
            neueKategorieInput.required = false;
        }
    });

    // Bei Form-Submit: Wenn neue Kategorie, dann Wert uebernehmen
    document.querySelector('#addBausteinModal form')?.addEventListener('submit', function(e) {
        if (kategorieSelect.value === '__neu__' && neueKategorieInput.value) {
            kategorieSelect.innerHTML += `<option value="${neueKategorieInput.value}" selected>${neueKategorieInput.value}</option>`;
            kategorieSelect.value = neueKategorieInput.value;
        }
    });
}

// Bild-Vorschau
const bildInput = document.querySelector('input[name="bild"]');
const bildPreview = document.getElementById('bildPreview');
const previewImg = document.getElementById('previewImg');

if (bildInput) {
    bildInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                bildPreview.style.display = 'block';
            };
            reader.readAsDataURL(this.files[0]);
        } else {
            bildPreview.style.display = 'none';
        }
    });
}
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
