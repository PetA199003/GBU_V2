<?php
/**
 * Meine Projekte - Ansicht für Benutzer
 * Bearbeiter können auch Projekte anlegen und Kollegen aus dem gleichen Unternehmen zuweisen
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

requireLogin();

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$isAdmin = hasRole(ROLE_ADMIN);
$isEditor = hasRole(ROLE_EDITOR);

// Sicherstellen, dass firma_id Spalte in projekte existiert
try {
    $colExists = $db->fetchOne("SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projekte' AND COLUMN_NAME = 'firma_id'");
    if ($colExists['cnt'] == 0) {
        $db->query("ALTER TABLE projekte ADD COLUMN firma_id INT UNSIGNED DEFAULT NULL AFTER id");
    }
} catch (Exception $e) {
    // Ignorieren
}

// Aktuellen Benutzer mit Firma laden
$currentUser = $db->fetchOne("SELECT * FROM benutzer WHERE id = ?", [$userId]);
$userFirmaId = $currentUser['firma_id'] ?? null;

// Kollegen aus dem gleichen Unternehmen laden (für alle Benutzer mit Firma)
$kollegen = [];
if ($userFirmaId) {
    $kollegen = $db->fetchAll("
        SELECT id, vorname, nachname, benutzername, rolle
        FROM benutzer
        WHERE firma_id = ? AND aktiv = 1 AND id != ?
        ORDER BY nachname, vorname
    ", [$userFirmaId, $userId]);
}

// Tags laden
$tags = $db->fetchAll("SELECT * FROM gefaehrdung_tags ORDER BY sortierung");

// Aktion verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
            // Nur Bearbeiter/Admin dürfen neue Projekte erstellen
            if (!$isEditor && !$isAdmin) {
                setFlashMessage('error', 'Sie haben keine Berechtigung, Projekte zu erstellen.');
                redirect('projekte.php');
            }
            $data = [
                'name' => $_POST['name'] ?? '',
                'location' => $_POST['location'] ?? '',
                'zeitraum_von' => $_POST['zeitraum_von'] ?? null,
                'zeitraum_bis' => $_POST['zeitraum_bis'] ?? null,
                'aufbau_datum' => $_POST['aufbau_datum'] ?: null,
                'abbau_datum' => $_POST['abbau_datum'] ?: null,
                'indoor_outdoor' => $_POST['indoor_outdoor'] ?? 'indoor',
                'beschreibung' => $_POST['beschreibung'] ?: null,
                'status' => $_POST['status'] ?? 'geplant',
                'erstellt_von' => $userId
            ];

            // Firma vom Benutzer übernehmen
            if ($userFirmaId) {
                $data['firma_id'] = $userFirmaId;
            }

            if (empty($data['name']) || empty($data['location'])) {
                setFlashMessage('error', 'Name und Location sind Pflichtfelder.');
            } else {
                $projektId = $db->insert('projekte', $data);

                // Tags speichern
                if (!empty($_POST['tags']) && is_array($_POST['tags'])) {
                    foreach ($_POST['tags'] as $tagId) {
                        $db->query("INSERT IGNORE INTO projekt_tags (projekt_id, tag_id) VALUES (?, ?)", [$projektId, $tagId]);
                    }
                }

                // Indoor/Outdoor-Tags automatisch setzen
                $autoTags = [];
                if ($data['indoor_outdoor'] === 'indoor' || $data['indoor_outdoor'] === 'beides') {
                    $indoorTag = $db->fetchOne("SELECT id FROM gefaehrdung_tags WHERE name = 'indoor'");
                    if ($indoorTag) $autoTags[] = $indoorTag['id'];
                }
                if ($data['indoor_outdoor'] === 'outdoor' || $data['indoor_outdoor'] === 'beides') {
                    $outdoorTag = $db->fetchOne("SELECT id FROM gefaehrdung_tags WHERE name = 'outdoor'");
                    if ($outdoorTag) $autoTags[] = $outdoorTag['id'];
                }
                $standardTag = $db->fetchOne("SELECT id FROM gefaehrdung_tags WHERE name = 'standard'");
                if ($standardTag) $autoTags[] = $standardTag['id'];

                foreach ($autoTags as $tagId) {
                    $db->query("INSERT IGNORE INTO projekt_tags (projekt_id, tag_id) VALUES (?, ?)", [$projektId, $tagId]);
                }

                // Ersteller automatisch zuweisen
                $db->insert('benutzer_projekte', [
                    'benutzer_id' => $userId,
                    'projekt_id' => $projektId,
                    'berechtigung' => 'bearbeiten',
                    'zugewiesen_von' => $userId
                ]);

                // Standard-Gefährdungen automatisch hinzufügen
                $standardGefaehrdungen = $db->fetchAll("SELECT * FROM gefaehrdung_bibliothek WHERE ist_standard = 1");
                $addedCount = 0;
                foreach ($standardGefaehrdungen as $gef) {
                    $db->insert('projekt_gefaehrdungen', [
                        'projekt_id' => $projektId,
                        'gefaehrdung_bibliothek_id' => $gef['id'],
                        'gefaehrdungsart_id' => $gef['gefaehrdungsart_id'] ?? null,
                        'kategorie_id' => $gef['kategorie_id'] ?? null,
                        'unterkategorie_id' => $gef['unterkategorie_id'] ?? null,
                        'titel' => $gef['titel'],
                        'beschreibung' => $gef['beschreibung'],
                        'schadenschwere' => $gef['standard_schadenschwere'] ?? 2,
                        'wahrscheinlichkeit' => $gef['standard_wahrscheinlichkeit'] ?? 2,
                        'stop_s' => $gef['stop_s'] ?? 0,
                        'stop_t' => $gef['stop_t'] ?? 0,
                        'stop_o' => $gef['stop_o'] ?? 0,
                        'stop_p' => $gef['stop_p'] ?? 0,
                        'massnahmen' => $gef['typische_massnahmen'],
                        'erstellt_von' => $userId
                    ]);
                    $addedCount++;
                }

                $msg = 'Projekt wurde erstellt.';
                if ($addedCount > 0) {
                    $msg .= " $addedCount Standard-Gefährdung(en) wurden automatisch hinzugefügt.";
                }
                setFlashMessage('success', $msg);
            }
            break;

        case 'assign_user':
            $projektId = $_POST['projekt_id'];
            $benutzerId = $_POST['benutzer_id'];
            $berechtigung = $_POST['berechtigung'] ?? 'ansehen';

            // Prüfen ob Benutzer Zugriff auf das Projekt hat (Bearbeiter-Berechtigung)
            $hasAccess = $db->fetchOne("
                SELECT id FROM benutzer_projekte WHERE projekt_id = ? AND benutzer_id = ? AND berechtigung = 'bearbeiten'
            ", [$projektId, $userId]);

            // Prüfen ob Benutzer der Ersteller des Projekts ist
            $projektData = $db->fetchOne("SELECT erstellt_von FROM projekte WHERE id = ?", [$projektId]);
            $isOwner = $projektData && $projektData['erstellt_von'] == $userId;

            // Prüfen ob zugewiesener Benutzer aus gleichem Unternehmen ist
            $targetUser = $db->fetchOne("SELECT firma_id FROM benutzer WHERE id = ?", [$benutzerId]);

            if (($hasAccess || $isOwner || $isAdmin) && ($targetUser['firma_id'] == $userFirmaId || $isAdmin)) {
                $exists = $db->fetchOne("SELECT id FROM benutzer_projekte WHERE benutzer_id = ? AND projekt_id = ?", [$benutzerId, $projektId]);

                if (!$exists) {
                    $db->insert('benutzer_projekte', [
                        'benutzer_id' => $benutzerId,
                        'projekt_id' => $projektId,
                        'berechtigung' => $berechtigung,
                        'zugewiesen_von' => $userId
                    ]);
                    setFlashMessage('success', 'Benutzer wurde dem Projekt zugewiesen.');
                } else {
                    $db->update('benutzer_projekte', ['berechtigung' => $berechtigung],
                        'benutzer_id = :bid AND projekt_id = :pid', ['bid' => $benutzerId, 'pid' => $projektId]);
                    setFlashMessage('success', 'Berechtigung wurde aktualisiert.');
                }
            }
            break;

        case 'remove_user':
            $projektId = $_POST['projekt_id'];
            $benutzerId = $_POST['benutzer_id'];

            // Nicht sich selbst entfernen
            if ($benutzerId != $userId) {
                $hasAccess = $db->fetchOne("
                    SELECT id FROM benutzer_projekte WHERE projekt_id = ? AND benutzer_id = ? AND berechtigung = 'bearbeiten'
                ", [$projektId, $userId]);

                // Prüfen ob Benutzer der Ersteller des Projekts ist
                $projektData = $db->fetchOne("SELECT erstellt_von FROM projekte WHERE id = ?", [$projektId]);
                $isOwner = $projektData && $projektData['erstellt_von'] == $userId;

                if ($hasAccess || $isOwner || $isAdmin) {
                    $db->delete('benutzer_projekte', 'benutzer_id = ? AND projekt_id = ?', [$benutzerId, $projektId]);
                    setFlashMessage('success', 'Benutzer wurde vom Projekt entfernt.');
                }
            }
            break;

        case 'delete':
            $projektId = $_POST['projekt_id'];

            // Prüfen ob Benutzer der Ersteller des Projekts ist oder Admin
            $projekt = $db->fetchOne("SELECT erstellt_von FROM projekte WHERE id = ?", [$projektId]);

            if ($projekt && ($projekt['erstellt_von'] == $userId || $isAdmin)) {
                // Alle verknüpften Daten löschen
                $db->delete('projekt_gefaehrdungen', 'projekt_id = ?', [$projektId]);
                $db->delete('projekt_tags', 'projekt_id = ?', [$projektId]);
                $db->delete('benutzer_projekte', 'projekt_id = ?', [$projektId]);

                // Unterweisungen löschen (falls Tabelle existiert)
                try {
                    $db->delete('unterweisungen', 'projekt_id = ?', [$projektId]);
                } catch (Exception $e) {
                    // Tabelle existiert möglicherweise nicht
                }

                // Projekt löschen
                $db->delete('projekte', 'id = ?', [$projektId]);
                setFlashMessage('success', 'Projekt wurde gelöscht.');
            } else {
                setFlashMessage('error', 'Sie können nur Ihre eigenen Projekte löschen.');
            }
            break;
    }

    redirect('projekte.php');
}

// Projekte des Benutzers laden - ohne archivierte
// Admin sieht alle, andere nur zugewiesene (und bei gleichem Unternehmen)
if ($isAdmin) {
    $projekte = $db->fetchAll("
        SELECT p.*,
               CONCAT(b.vorname, ' ', b.nachname) as erstellt_von_name,
               (SELECT COUNT(*) FROM projekt_gefaehrdungen WHERE projekt_id = p.id) as gefaehrdungen_count,
               'bearbeiten' as berechtigung,
               f.name as firma_name
        FROM projekte p
        LEFT JOIN benutzer b ON p.erstellt_von = b.id
        LEFT JOIN firmen f ON p.firma_id = f.id
        WHERE p.status != 'archiviert'
        ORDER BY p.status = 'aktiv' DESC, p.zeitraum_von DESC
    ");
} else {
    $projekte = $db->fetchAll("
        SELECT p.*,
               CONCAT(b.vorname, ' ', b.nachname) as erstellt_von_name,
               (SELECT COUNT(*) FROM projekt_gefaehrdungen WHERE projekt_id = p.id) as gefaehrdungen_count,
               bp.berechtigung,
               f.name as firma_name
        FROM projekte p
        JOIN benutzer_projekte bp ON p.id = bp.projekt_id
        LEFT JOIN benutzer b ON p.erstellt_von = b.id
        LEFT JOIN firmen f ON p.firma_id = f.id
        WHERE bp.benutzer_id = ? AND p.status != 'archiviert'
        ORDER BY p.status = 'aktiv' DESC, p.zeitraum_von DESC
    ", [$userId]);
}

$pageTitle = 'Meine Projekte';
require_once __DIR__ . '/templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="bi bi-folder me-2"></i>Meine Projekte
            </h1>
            <p class="text-muted mb-0">
                Projekte, die Ihnen zugewiesen sind
                <?php if ($userFirmaId):
                    $firmaName = $db->fetchOne("SELECT name FROM firmen WHERE id = ?", [$userFirmaId]);
                ?>
                <span class="badge bg-info ms-2"><?= sanitize($firmaName['name'] ?? '') ?></span>
                <?php endif; ?>
            </p>
        </div>
        <div class="btn-group">
            <?php if ($isEditor || $isAdmin): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#projektModal">
                <i class="bi bi-plus-lg me-2"></i>Neues Projekt
            </button>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
            <a href="<?= BASE_URL ?>/admin/projekte.php" class="btn btn-outline-primary">
                <i class="bi bi-gear me-2"></i>Alle Projekte
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($projekte)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-folder-x display-4 text-muted"></i>
            <h5 class="mt-3">Keine Projekte zugewiesen</h5>
            <p class="text-muted">Sie haben noch keine Projekte zugewiesen bekommen.<br>Bitte wenden Sie sich an einen Administrator.</p>
        </div>
    </div>
    <?php else: ?>
    <div class="row">
        <?php foreach ($projekte as $p): ?>
        <?php include __DIR__ . '/templates/_projekt_card.php'; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal: Benutzer zuweisen (für alle mit Kollegen) -->
<?php if (!empty($kollegen)): ?>
<div class="modal fade" id="zuweisungModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="assign_user">
                <input type="hidden" name="projekt_id" id="zuweisung_projekt_id">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Kollegen zuweisen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Sie können nur Kollegen aus Ihrem Unternehmen zuweisen.</p>

                    <div class="mb-3">
                        <label class="form-label">Kollege auswählen *</label>
                        <select class="form-select" name="benutzer_id" required>
                            <option value="">-- Bitte wählen --</option>
                            <?php foreach ($kollegen as $kollege): ?>
                            <option value="<?= $kollege['id'] ?>">
                                <?= sanitize($kollege['vorname'] . ' ' . $kollege['nachname']) ?>
                                (<?= $kollege['rolle'] == ROLE_ADMIN ? 'Admin' : ($kollege['rolle'] == ROLE_EDITOR ? 'Bearbeiter' : 'Betrachter') ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Berechtigung *</label>
                        <select class="form-select" name="berechtigung" required>
                            <option value="ansehen">Nur ansehen</option>
                            <option value="bearbeiten">Bearbeiten</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Zuweisen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openZuweisungModal(projektId) {
    document.getElementById('zuweisung_projekt_id').value = projektId;
    new bootstrap.Modal(document.getElementById('zuweisungModal')).show();
}
</script>
<?php endif; ?>

<?php if ($isEditor || $isAdmin): ?>
<!-- Modal: Neues Projekt -->
<div class="modal fade" id="projektModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-folder-plus me-2"></i>Neues Projekt</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Projektname *</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="geplant">Geplant</option>
                                <option value="aktiv">Aktiv</option>
                                <option value="abgeschlossen">Abgeschlossen</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Location *</label>
                            <input type="text" class="form-control" name="location" required placeholder="z.B. Messe Zürich, Halle 3">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Indoor/Outdoor *</label>
                            <select class="form-select" name="indoor_outdoor" required>
                                <option value="indoor">Indoor</option>
                                <option value="outdoor">Outdoor</option>
                                <option value="beides">Beides</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Zeitraum von *</label>
                            <input type="date" class="form-control" name="zeitraum_von" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Zeitraum bis *</label>
                            <input type="date" class="form-control" name="zeitraum_bis" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Aufbau-Datum</label>
                            <input type="date" class="form-control" name="aufbau_datum">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Abbau-Datum</label>
                            <input type="date" class="form-control" name="abbau_datum">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Zusätzliche Tags</label>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($tags as $tag): ?>
                            <?php if (!in_array($tag['name'], ['indoor', 'outdoor', 'standard'])): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="tags[]" value="<?= $tag['id'] ?>" id="ptag_<?= $tag['id'] ?>">
                                <label class="form-check-label" for="ptag_<?= $tag['id'] ?>">
                                    <span class="badge" style="background-color: <?= $tag['farbe'] ?>"><?= sanitize($tag['name']) ?></span>
                                </label>
                            </div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Beschreibung</label>
                        <textarea class="form-control" name="beschreibung" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Projekt erstellen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
