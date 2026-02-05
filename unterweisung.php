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

// Berechtigung prüfen
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
                setFlashMessage('success', 'Baustein hinzugefügt');
            }
            break;

        case 'delete_baustein':
            if ($isAdmin) {
                $db->delete('unterweisungs_bausteine', 'id = ?', [$_POST['baustein_id']]);
                setFlashMessage('success', 'Baustein gelöscht');
            }
            break;

        case 'edit_baustein':
            if ($isAdmin) {
                $bildUrl = null;
                $bausteinId = $_POST['baustein_id'] ?? null;

                // Bestehenden Baustein laden
                $bestehendesBild = $db->fetchOne("SELECT bild_url FROM unterweisungs_bausteine WHERE id = ?", [$bausteinId]);

                // Bild löschen?
                if (isset($_POST['delete_bild']) && $_POST['delete_bild'] == '1') {
                    $bildUrl = null;
                    // Altes Bild physisch löschen (optional)
                    if ($bestehendesBild['bild_url']) {
                        $oldFile = __DIR__ . str_replace(BASE_URL, '', $bestehendesBild['bild_url']);
                        if (file_exists($oldFile)) {
                            @unlink($oldFile);
                        }
                    }
                }
                // Neues Bild hochladen
                elseif (isset($_FILES['bild']) && $_FILES['bild']['error'] === UPLOAD_ERR_OK) {
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
            // Prüfen ob bereits unterschrieben - dann nur Admin darf ändern
            if ($unterweisung['durchfuehrer_unterschrift'] && !$isAdmin) {
                setFlashMessage('error', 'Nach Unterschrift kann nur ein Admin die Daten ändern');
                break;
            }
            $db->update('projekt_unterweisungen', [
                'titel' => $_POST['titel'] ?? 'Sicherheitsunterweisung',
                'durchgefuehrt_von' => $_POST['durchgefuehrt_von'] ?? null,
                'durchgefuehrt_am' => $_POST['durchgefuehrt_am'] ?: null
            ], 'id = :id', ['id' => $unterweisungId]);
            setFlashMessage('success', 'Einstellungen gespeichert');
            break;

        case 'sign_durchfuehrer':
            // Durchführer unterschreibt
            $signatur = $_POST['signatur'] ?? null;
            if ($signatur) {
                $db->update('projekt_unterweisungen', [
                    'durchfuehrer_unterschrift' => $signatur,
                    'durchfuehrer_unterschrieben_am' => date('Y-m-d H:i:s')
                ], 'id = :id', ['id' => $unterweisungId]);
                setFlashMessage('success', 'Unterschrift gespeichert');
            }
            break;

        case 'delete_durchfuehrer_unterschrift':
            // Nur Admin darf Durchführer-Unterschrift löschen
            if ($isAdmin) {
                $db->update('projekt_unterweisungen', [
                    'durchfuehrer_unterschrift' => null,
                    'durchfuehrer_unterschrieben_am' => null
                ], 'id = :id', ['id' => $unterweisungId]);
                setFlashMessage('success', 'Unterschrift gelöscht');
            }
            break;

        case 'save_bausteine':
            // Alle alten Bausteine löschen
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
            setFlashMessage('success', 'Teilnehmer hinzugefügt');
            break;

        case 'delete_teilnehmer':
            $db->delete('unterweisung_teilnehmer', 'id = ? AND unterweisung_id = ?', [$_POST['teilnehmer_id'], $unterweisungId]);
            setFlashMessage('success', 'Teilnehmer entfernt');
            break;

        case 'edit_teilnehmer':
            // Nur bearbeiten wenn keine Unterschrift vorhanden
            $teilnehmerCheck = $db->fetchOne(
                "SELECT unterschrift FROM unterweisung_teilnehmer WHERE id = ? AND unterweisung_id = ?",
                [$_POST['teilnehmer_id'], $unterweisungId]
            );
            if ($teilnehmerCheck && !$teilnehmerCheck['unterschrift']) {
                $db->update('unterweisung_teilnehmer', [
                    'vorname' => $_POST['vorname'] ?? '',
                    'nachname' => $_POST['nachname'] ?? '',
                    'firma' => $_POST['firma'] ?? null
                ], 'id = :id AND unterweisung_id = :uid', [
                    'id' => $_POST['teilnehmer_id'],
                    'uid' => $unterweisungId
                ]);
                setFlashMessage('success', 'Teilnehmer aktualisiert');
            } else {
                setFlashMessage('error', 'Teilnehmer mit Unterschrift kann nicht bearbeitet werden');
            }
            break;

        case 'delete_unterschrift':
            // Bearbeiter und Admin dürfen Unterschriften löschen
            if ($canEdit) {
                $db->update('unterweisung_teilnehmer', [
                    'unterschrift' => null,
                    'unterschrieben_am' => null
                ], 'id = :id AND unterweisung_id = :uid', [
                    'id' => $_POST['teilnehmer_id'],
                    'uid' => $unterweisungId
                ]);
                setFlashMessage('success', 'Unterschrift gelöscht');
            }
            break;

        case 'add_projekt_baustein':
            // Bearbeiter und Admin können projekt-spezifische Bausteine hinzufügen
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

            // Baustein als projektspezifisch speichern
            $newBausteinId = $db->insert('unterweisungs_bausteine', [
                'kategorie' => 'Projektspezifisch',
                'titel' => $_POST['titel'] ?? '',
                'inhalt' => $_POST['inhalt'] ?? '',
                'bild_url' => $bildUrl,
                'sortierung' => 200,
                'aktiv' => 1,
                'projekt_id' => $projektId  // Nur für dieses Projekt
            ]);

            // Direkt zur Unterweisung hinzufügen
            $maxSort = $db->fetchOne("SELECT MAX(sortierung) as max FROM unterweisung_bausteine WHERE unterweisung_id = ?", [$unterweisungId]);
            $db->insert('unterweisung_bausteine', [
                'unterweisung_id' => $unterweisungId,
                'baustein_id' => $newBausteinId,
                'sortierung' => ($maxSort['max'] ?? 0) + 1
            ]);

            setFlashMessage('success', 'Projektspezifischer Inhalt hinzugefügt');
            break;

        case 'import_csv':
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
                $imported = 0;
                $firstRow = true;

                while (($data = fgetcsv($handle, 1000, ';')) !== false) {
                    // Erste Zeile überspringen wenn Header
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

        case 'update_sortierung':
            // AJAX-Aufruf für Sortierung - nur Admin
            header('Content-Type: application/json');
            if (!$isAdmin) {
                echo json_encode(['success' => false, 'error' => 'Keine Berechtigung']);
                exit;
            }
            $sortierung = $_POST['sortierung'] ?? [];
            if (is_array($sortierung)) {
                $pos = 1;
                foreach ($sortierung as $bausteinId) {
                    $db->update('unterweisung_bausteine', [
                        'sortierung' => $pos
                    ], 'unterweisung_id = :uid AND baustein_id = :bid', [
                        'uid' => $unterweisungId,
                        'bid' => $bausteinId
                    ]);
                    $pos++;
                }
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Ungültige Daten']);
            }
            exit;
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

// Ausgewählte Bausteine laden (mit Details für Sortierung)
$ausgewaehlteBausteine = $db->fetchAll("
    SELECT ub.baustein_id, ub.sortierung, b.titel, b.kategorie, b.bild_url
    FROM unterweisung_bausteine ub
    JOIN unterweisungs_bausteine b ON ub.baustein_id = b.id
    WHERE ub.unterweisung_id = ?
    ORDER BY ub.sortierung
", [$unterweisungId]);
$ausgewaehlteIds = array_column($ausgewaehlteBausteine, 'baustein_id');

// Teilnehmer laden (nach Nachname sortiert)
$teilnehmer = $db->fetchAll("
    SELECT * FROM unterweisung_teilnehmer
    WHERE unterweisung_id = ?
    ORDER BY nachname, vorname
", [$unterweisungId]);

// Aktueller Benutzer (für "Durchgeführt von")
$aktuellerBenutzer = $db->fetchOne("SELECT vorname, nachname FROM benutzer WHERE id = ?", [$userId]);
$aktuellerBenutzerName = $aktuellerBenutzer ? $aktuellerBenutzer['vorname'] . ' ' . $aktuellerBenutzer['nachname'] : '';

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
                <i class="bi bi-arrow-left me-2"></i>Zurück zum Projekt
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
            <?php
            $hatDurchfuehrerUnterschrift = !empty($unterweisung['durchfuehrer_unterschrift']);
            $settingsLocked = $hatDurchfuehrerUnterschrift && !$isAdmin;
            ?>
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-gear me-2"></i>Einstellungen</h5>
                    <?php if ($hatDurchfuehrerUnterschrift): ?>
                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Unterschrieben</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($settingsLocked): ?>
                    <div class="alert alert-info small mb-3">
                        <i class="bi bi-lock me-1"></i>
                        Die Einstellungen sind nach der Unterschrift gesperrt. Nur ein Admin kann sie ändern.
                    </div>
                    <?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_settings">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Durchgeführt von</label>
                                <input type="text" class="form-control" name="durchgefuehrt_von"
                                       value="<?= sanitize($unterweisung['durchgefuehrt_von'] ?? $aktuellerBenutzerName) ?>"
                                       <?= (!$canEdit || $settingsLocked) ? 'disabled' : '' ?>>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Datum</label>
                                <input type="date" class="form-control" name="durchgefuehrt_am"
                                       value="<?= $unterweisung['durchgefuehrt_am'] ?? date('Y-m-d') ?>"
                                       <?= (!$canEdit || $settingsLocked) ? 'disabled' : '' ?>>
                            </div>
                        </div>
                        <?php if ($canEdit && !$settingsLocked): ?>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check me-2"></i>Speichern
                        </button>
                        <?php endif; ?>
                    </form>

                    <hr class="my-3">

                    <!-- Unterschrift des Durchführenden -->
                    <div class="mt-3">
                        <label class="form-label fw-bold">Unterschrift des Durchführenden</label>
                        <?php if ($hatDurchfuehrerUnterschrift): ?>
                        <div class="border rounded p-3 bg-light">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <img src="<?= $unterweisung['durchfuehrer_unterschrift'] ?>" alt="Unterschrift" style="max-height: 80px;">
                                    <p class="text-muted small mb-0 mt-2">
                                        Unterschrieben am <?= date('d.m.Y \u\m H:i', strtotime($unterweisung['durchfuehrer_unterschrieben_am'])) ?> Uhr
                                    </p>
                                </div>
                                <?php if ($isAdmin): ?>
                                <form method="POST" onsubmit="return confirm('Unterschrift wirklich löschen?')">
                                    <input type="hidden" name="action" value="delete_durchfuehrer_unterschrift">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <?php if ($canEdit): ?>
                        <div class="border rounded p-3 bg-light">
                            <p class="text-muted small mb-2">
                                <i class="bi bi-info-circle me-1"></i>
                                Nach der Unterschrift werden Name und Datum gesperrt.
                            </p>
                            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#signDurchfuehrerModal">
                                <i class="bi bi-pen me-2"></i>Jetzt unterschreiben
                            </button>
                        </div>
                        <?php else: ?>
                        <p class="text-muted">Noch nicht unterschrieben</p>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Bausteine auswählen -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Inhalte auswählen</h5>
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
                                            <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-1" title="Löschen"
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
                                Alle auswählen
                            </button>
                            <button type="button" class="btn btn-outline-secondary ms-2" onclick="selectAll(false)">
                                Alle abwählen
                            </button>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Reihenfolge der ausgewählten Bausteine - nur Admin -->
            <?php if (!empty($ausgewaehlteBausteine) && $isAdmin): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-sort-down me-2"></i>Reihenfolge ändern</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        Ziehen Sie die Elemente, um die Reihenfolge zu ändern. Die Änderung wird automatisch gespeichert.
                    </p>
                    <ul class="list-group" id="sortableBausteine">
                        <?php foreach ($ausgewaehlteBausteine as $ab): ?>
                        <li class="list-group-item d-flex align-items-center sortable-item" data-id="<?= $ab['baustein_id'] ?>">
                            <i class="bi bi-grip-vertical me-3 text-muted drag-handle" style="cursor: grab;"></i>
                            <?php if ($ab['bild_url']): ?>
                            <img src="<?= sanitize($ab['bild_url']) ?>" alt="" class="me-2" style="width: 24px; height: 24px; object-fit: contain;">
                            <?php endif; ?>
                            <span class="badge bg-secondary me-2"><?= sanitize($ab['kategorie']) ?></span>
                            <span><?= sanitize($ab['titel']) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <div id="sortSaveStatus" class="mt-2 small text-success" style="display: none;">
                        <i class="bi bi-check-circle me-1"></i>Reihenfolge gespeichert
                    </div>
                </div>
            </div>
            <?php endif; ?>
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
                    <!-- Manuell hinzufügen -->
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
                                <button type="submit" class="btn btn-sm btn-success w-100" title="Hinzufügen">
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
                                        <a href="<?= BASE_URL ?>/signatur.php?id=<?= $unterweisungId ?>" class="badge bg-warning text-dark text-decoration-none" target="_blank" title="Jetzt unterschreiben">
                                            <i class="bi bi-pen me-1"></i>Offen
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($canEdit): ?>
                                    <td class="text-nowrap">
                                        <?php if (!$t['unterschrift']): ?>
                                        <button type="button" class="btn btn-sm btn-link text-primary p-0" title="Bearbeiten"
                                                onclick="editTeilnehmer(<?= $t['id'] ?>, '<?= addslashes(sanitize($t['vorname'])) ?>', '<?= addslashes(sanitize($t['nachname'])) ?>', '<?= addslashes(sanitize($t['firma'] ?? '')) ?>')">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php endif; ?>
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
                        <form method="POST" onsubmit="return confirm('Alle Teilnehmer wirklich löschen?')">
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

<!-- Modal: Eigenen Baustein hinzufügen -->
<?php if ($isAdmin): ?>
<div class="modal fade" id="addBausteinModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_baustein">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Eigenen Inhalt hinzufügen</h5>
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
                        <small class="text-muted">Für Aufzählungen verwenden Sie • am Zeilenanfang</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Piktogramm / Bild (optional)</label>
                        <input type="file" class="form-control" name="bild" accept="image/*">
                        <small class="text-muted">Empfohlene Größe: 100x100 Pixel, PNG oder JPG</small>
                    </div>
                    <div id="bildPreview" class="mb-3" style="display: none;">
                        <label class="form-label">Vorschau:</label><br>
                        <img id="previewImg" src="" alt="Vorschau" style="max-width: 100px; max-height: 100px; border: 1px solid #ddd; padding: 5px;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-plus-lg me-2"></i>Hinzufügen
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
                        <small class="text-muted">Für Aufzählungen verwenden Sie • am Zeilenanfang</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Aktuelles Piktogramm</label>
                        <div id="editCurrentBild" class="mb-2"></div>
                        <div id="editDeleteBildDiv" class="form-check" style="display: none;">
                            <input class="form-check-input" type="checkbox" name="delete_bild" value="1" id="editDeleteBild">
                            <label class="form-check-label text-danger" for="editDeleteBild">
                                <i class="bi bi-trash me-1"></i>Piktogramm löschen
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Neues Piktogramm (optional)</label>
                        <input type="file" class="form-control" name="bild" accept="image/*" id="editBildInput">
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

<!-- Modal: Durchführer Unterschrift -->
<?php if ($canEdit && !$hatDurchfuehrerUnterschrift): ?>
<div class="modal fade" id="signDurchfuehrerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-pen me-2"></i>Unterweisung unterschreiben</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">
                    <strong>Durchgeführt von:</strong> <?= sanitize($unterweisung['durchgefuehrt_von'] ?? $aktuellerBenutzerName) ?><br>
                    <strong>Datum:</strong> <?= $unterweisung['durchgefuehrt_am'] ? date('d.m.Y', strtotime($unterweisung['durchgefuehrt_am'])) : date('d.m.Y') ?>
                </p>
                <div class="alert alert-warning small">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Nach der Unterschrift können Name und Datum nicht mehr geändert werden (nur durch Admin).
                </div>
                <label class="form-label">Ihre Unterschrift:</label>
                <canvas id="durchfuehrerCanvas" style="width: 100%; height: 150px; border: 2px dashed #ccc; border-radius: 8px; cursor: crosshair; touch-action: none;"></canvas>
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearDurchfuehrerSignature()">
                        <i class="bi bi-eraser me-1"></i>Löschen
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-warning" onclick="saveDurchfuehrerSignature()">
                    <i class="bi bi-check-lg me-2"></i>Unterschreiben
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal: Projektspezifischen Baustein hinzufügen -->
<?php if ($canEdit): ?>
<div class="modal fade" id="addProjektBausteinModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_projekt_baustein">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Projektspezifischen Inhalt hinzufügen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small">
                        <i class="bi bi-info-circle me-1"></i>
                        Dieser Inhalt wird nur für dieses Projekt erstellt und ist nicht in anderen Projekten sichtbar.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Titel *</label>
                        <input type="text" class="form-control" name="titel" required placeholder="z.B. Spezielle Baustellenregeln">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Inhalt / Beschreibung *</label>
                        <textarea class="form-control" name="inhalt" rows="6" required placeholder="• Punkt 1&#10;• Punkt 2&#10;• Punkt 3"></textarea>
                        <small class="text-muted">Für Aufzählungen verwenden Sie • am Zeilenanfang</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Piktogramm / Bild (optional)</label>
                        <input type="file" class="form-control" name="bild" accept="image/*">
                        <small class="text-muted">Empfohlene Größe: 100x100 Pixel, PNG oder JPG</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-plus-lg me-2"></i>Hinzufügen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal: Teilnehmer bearbeiten -->
<?php if ($canEdit): ?>
<div class="modal fade" id="editTeilnehmerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit_teilnehmer">
                <input type="hidden" name="teilnehmer_id" id="editTeilnehmerId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Teilnehmer bearbeiten</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Vorname *</label>
                        <input type="text" class="form-control" name="vorname" id="editTeilnehmerVorname" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nachname *</label>
                        <input type="text" class="form-control" name="nachname" id="editTeilnehmerNachname" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Firma</label>
                        <input type="text" class="form-control" name="firma" id="editTeilnehmerFirma">
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

<!-- Verstecktes Formular für Baustein-Löschung (außerhalb des Hauptformulars) -->
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
                <?php if ($canEdit): ?>
                <form method="POST" id="deleteSignatureForm" onsubmit="return confirm('Unterschrift wirklich löschen?')">
                    <input type="hidden" name="action" value="delete_unterschrift">
                    <input type="hidden" name="teilnehmer_id" id="sigTeilnehmerId">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-2"></i>Unterschrift löschen
                    </button>
                </form>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>

<script>
// Durchführer-Unterschrift Canvas
let dfCanvas, dfCtx, dfIsDrawing = false, dfLastX = 0, dfLastY = 0;

document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('signDurchfuehrerModal');
    if (modal) {
        modal.addEventListener('shown.bs.modal', initDurchfuehrerCanvas);
    }
});

function initDurchfuehrerCanvas() {
    dfCanvas = document.getElementById('durchfuehrerCanvas');
    if (!dfCanvas) return;

    dfCtx = dfCanvas.getContext('2d');
    const rect = dfCanvas.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 1;

    dfCanvas.width = rect.width * dpr;
    dfCanvas.height = rect.height * dpr;
    dfCanvas.style.width = rect.width + 'px';
    dfCanvas.style.height = rect.height + 'px';
    dfCtx.scale(dpr, dpr);

    dfCtx.fillStyle = '#fff';
    dfCtx.fillRect(0, 0, rect.width, rect.height);
    dfCtx.strokeStyle = '#000';
    dfCtx.lineWidth = 2;
    dfCtx.lineCap = 'round';

    // Touch Events
    dfCanvas.addEventListener('touchstart', dfHandleTouchStart, { passive: false });
    dfCanvas.addEventListener('touchmove', dfHandleTouchMove, { passive: false });
    dfCanvas.addEventListener('touchend', () => dfIsDrawing = false);

    // Mouse Events
    dfCanvas.addEventListener('mousedown', dfHandleMouseDown);
    dfCanvas.addEventListener('mousemove', dfHandleMouseMove);
    dfCanvas.addEventListener('mouseup', () => dfIsDrawing = false);
    dfCanvas.addEventListener('mouseleave', () => dfIsDrawing = false);
}

function dfHandleTouchStart(e) {
    e.preventDefault();
    dfIsDrawing = true;
    const pos = dfGetTouchPos(e);
    dfLastX = pos.x;
    dfLastY = pos.y;
}

function dfHandleTouchMove(e) {
    if (!dfIsDrawing) return;
    e.preventDefault();
    const pos = dfGetTouchPos(e);
    dfCtx.beginPath();
    dfCtx.moveTo(dfLastX, dfLastY);
    dfCtx.lineTo(pos.x, pos.y);
    dfCtx.stroke();
    dfLastX = pos.x;
    dfLastY = pos.y;
}

function dfGetTouchPos(e) {
    const rect = dfCanvas.getBoundingClientRect();
    const touch = e.touches[0];
    return { x: touch.clientX - rect.left, y: touch.clientY - rect.top };
}

function dfHandleMouseDown(e) {
    dfIsDrawing = true;
    const rect = dfCanvas.getBoundingClientRect();
    dfLastX = e.clientX - rect.left;
    dfLastY = e.clientY - rect.top;
}

function dfHandleMouseMove(e) {
    if (!dfIsDrawing) return;
    const rect = dfCanvas.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    dfCtx.beginPath();
    dfCtx.moveTo(dfLastX, dfLastY);
    dfCtx.lineTo(x, y);
    dfCtx.stroke();
    dfLastX = x;
    dfLastY = y;
}

function clearDurchfuehrerSignature() {
    if (!dfCanvas || !dfCtx) return;
    const rect = dfCanvas.getBoundingClientRect();
    dfCtx.fillStyle = '#fff';
    dfCtx.fillRect(0, 0, rect.width, rect.height);
    dfCtx.strokeStyle = '#000';
}

function saveDurchfuehrerSignature() {
    if (!dfCanvas || !dfCtx) return;

    // Prüfen ob unterschrieben
    const imageData = dfCtx.getImageData(0, 0, dfCanvas.width, dfCanvas.height);
    let hasSignature = false;
    for (let i = 0; i < imageData.data.length; i += 4) {
        if (imageData.data[i] < 250 || imageData.data[i+1] < 250 || imageData.data[i+2] < 250) {
            hasSignature = true;
            break;
        }
    }

    if (!hasSignature) {
        alert('Bitte unterschreiben Sie zuerst.');
        return;
    }

    const signatureData = dfCanvas.toDataURL('image/png');

    // Formular erstellen und absenden
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="sign_durchfuehrer">
        <input type="hidden" name="signatur" value="${signatureData}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function showSignature(name, date, imageData, teilnehmerId) {
    document.getElementById('sigName').textContent = name;
    document.getElementById('sigDate').textContent = date;
    document.getElementById('sigImage').src = imageData;
    document.getElementById('sigTeilnehmerId').value = teilnehmerId;
    new bootstrap.Modal(document.getElementById('signatureModal')).show();
}

function deleteBaustein(bausteinId) {
    if (confirm('Baustein wirklich löschen?')) {
        document.getElementById('deleteBausteinId').value = bausteinId;
        document.getElementById('deleteBausteinForm').submit();
    }
}

function editTeilnehmer(id, vorname, nachname, firma) {
    document.getElementById('editTeilnehmerId').value = id;
    document.getElementById('editTeilnehmerVorname').value = vorname;
    document.getElementById('editTeilnehmerNachname').value = nachname;
    document.getElementById('editTeilnehmerFirma').value = firma;
    new bootstrap.Modal(document.getElementById('editTeilnehmerModal')).show();
}

function editBaustein(id, kategorie, titel, inhalt, bildUrl) {
    document.getElementById('editBausteinId').value = id;
    document.getElementById('editKategorieSelect').value = kategorie;
    document.getElementById('editTitel').value = titel;
    document.getElementById('editInhalt').value = inhalt;

    const currentBildDiv = document.getElementById('editCurrentBild');
    const deleteBildDiv = document.getElementById('editDeleteBildDiv');
    const deleteBildCheckbox = document.getElementById('editDeleteBild');
    const bildInput = document.getElementById('editBildInput');

    // Checkbox zurücksetzen
    deleteBildCheckbox.checked = false;
    bildInput.value = '';

    if (bildUrl) {
        currentBildDiv.innerHTML = `<img src="${bildUrl}" alt="Aktuelles Piktogramm" style="max-width: 80px; max-height: 80px; border: 1px solid #ddd; padding: 5px;">`;
        deleteBildDiv.style.display = 'block';
    } else {
        currentBildDiv.innerHTML = '<span class="text-muted">Kein Bild vorhanden</span>';
        deleteBildDiv.style.display = 'none';
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

    // Bei Form-Submit: Wenn neue Kategorie, dann Wert übernehmen
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

// Sortierung der Bausteine mit Drag & Drop
const sortableList = document.getElementById('sortableBausteine');
if (sortableList) {
    let draggedItem = null;

    sortableList.querySelectorAll('.sortable-item').forEach(item => {
        item.draggable = true;

        item.addEventListener('dragstart', function(e) {
            draggedItem = this;
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        item.addEventListener('dragend', function() {
            this.classList.remove('dragging');
            draggedItem = null;
            saveSortierung();
        });

        item.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';

            const bounding = this.getBoundingClientRect();
            const offset = e.clientY - bounding.top;

            if (offset > bounding.height / 2) {
                this.parentNode.insertBefore(draggedItem, this.nextSibling);
            } else {
                this.parentNode.insertBefore(draggedItem, this);
            }
        });

        item.addEventListener('dragenter', function(e) {
            e.preventDefault();
            this.classList.add('drag-over');
        });

        item.addEventListener('dragleave', function() {
            this.classList.remove('drag-over');
        });

        item.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');
        });
    });

    // Touch-Events für Mobile
    let touchStartY = 0;
    let currentTouchItem = null;

    sortableList.querySelectorAll('.sortable-item').forEach(item => {
        item.addEventListener('touchstart', function(e) {
            currentTouchItem = this;
            touchStartY = e.touches[0].clientY;
            this.classList.add('dragging');
        }, { passive: true });

        item.addEventListener('touchmove', function(e) {
            if (!currentTouchItem) return;

            const touch = e.touches[0];
            const items = Array.from(sortableList.querySelectorAll('.sortable-item'));

            items.forEach(other => {
                if (other === currentTouchItem) return;
                const rect = other.getBoundingClientRect();
                if (touch.clientY > rect.top && touch.clientY < rect.bottom) {
                    const offset = touch.clientY - rect.top;
                    if (offset > rect.height / 2) {
                        sortableList.insertBefore(currentTouchItem, other.nextSibling);
                    } else {
                        sortableList.insertBefore(currentTouchItem, other);
                    }
                }
            });
        }, { passive: true });

        item.addEventListener('touchend', function() {
            if (currentTouchItem) {
                currentTouchItem.classList.remove('dragging');
                saveSortierung();
            }
            currentTouchItem = null;
        });
    });

    function saveSortierung() {
        const items = sortableList.querySelectorAll('.sortable-item');
        const sortierung = Array.from(items).map(item => item.dataset.id);

        const formData = new FormData();
        formData.append('action', 'update_sortierung');
        sortierung.forEach(id => formData.append('sortierung[]', id));

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const status = document.getElementById('sortSaveStatus');
                status.style.display = 'block';
                setTimeout(() => status.style.display = 'none', 2000);
            }
        })
        .catch(err => console.error('Fehler beim Speichern:', err));
    }
}
</script>

<style>
/* Drag & Drop Styles */
.sortable-item {
    transition: transform 0.2s, box-shadow 0.2s;
    user-select: none;
}
.sortable-item:hover {
    background-color: #f8f9fa;
}
.sortable-item.dragging {
    opacity: 0.5;
    background-color: #e9ecef;
    box-shadow: 0 2px 10px rgba(0,0,0,0.15);
}
.sortable-item.drag-over {
    border-top: 2px solid #0d6efd;
}
.drag-handle:hover {
    color: #0d6efd !important;
}
</style>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
