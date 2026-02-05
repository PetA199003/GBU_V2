<?php
/**
 * Projektverwaltung (Admin)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

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
                'location' => $_POST['location'] ?? '',
                'zeitraum_von' => $_POST['zeitraum_von'] ?? null,
                'zeitraum_bis' => $_POST['zeitraum_bis'] ?? null,
                'aufbau_datum' => $_POST['aufbau_datum'] ?: null,
                'abbau_datum' => $_POST['abbau_datum'] ?: null,
                'indoor_outdoor' => $_POST['indoor_outdoor'] ?? 'indoor',
                'beschreibung' => $_POST['beschreibung'] ?: null,
                'status' => $_POST['status'] ?? 'geplant'
            ];

            if (empty($data['name']) || empty($data['location'])) {
                setFlashMessage('error', 'Name und Location sind Pflichtfelder.');
            } else {
                if ($action === 'create') {
                    $data['erstellt_von'] = $_SESSION['user_id'];
                    $projektId = $db->insert('projekte', $data);

                    // Tags speichern
                    if (!empty($_POST['tags']) && is_array($_POST['tags'])) {
                        foreach ($_POST['tags'] as $tagId) {
                            $db->query(
                                "INSERT IGNORE INTO projekt_tags (projekt_id, tag_id) VALUES (?, ?)",
                                [$projektId, $tagId]
                            );
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
                    // Standard-Tag immer setzen
                    $standardTag = $db->fetchOne("SELECT id FROM gefaehrdung_tags WHERE name = 'standard'");
                    if ($standardTag) $autoTags[] = $standardTag['id'];

                    foreach ($autoTags as $tagId) {
                        $db->query(
                            "INSERT IGNORE INTO projekt_tags (projekt_id, tag_id) VALUES (?, ?)",
                            [$projektId, $tagId]
                        );
                    }

                    // Standard-Gefährdungen automatisch hinzufügen (ist_standard = 1)
                    $standardGefaehrdungen = $db->fetchAll("
                        SELECT * FROM gefaehrdung_bibliothek WHERE ist_standard = 1
                    ");

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
                            'verantwortlich' => $gef['verantwortlich'] ?? null,
                            'schadenschwere_nach' => $gef['schadenschwere_nachher'] ?? null,
                            'wahrscheinlichkeit_nach' => $gef['wahrscheinlichkeit_nachher'] ?? null,
                            'erstellt_von' => $_SESSION['user_id']
                        ]);
                        $addedCount++;
                    }

                    $msg = 'Projekt wurde erstellt.';
                    if ($addedCount > 0) {
                        $msg .= " $addedCount Standard-Gefährdung(en) wurden automatisch hinzugefügt.";
                    }
                    setFlashMessage('success', $msg);
                } else {
                    $id = $_POST['id'];
                    $db->update('projekte', $data, 'id = :id', ['id' => $id]);

                    // Tags aktualisieren
                    $db->delete('projekt_tags', 'projekt_id = ?', [$id]);
                    if (!empty($_POST['tags']) && is_array($_POST['tags'])) {
                        foreach ($_POST['tags'] as $tagId) {
                            $db->query(
                                "INSERT IGNORE INTO projekt_tags (projekt_id, tag_id) VALUES (?, ?)",
                                [$id, $tagId]
                            );
                        }
                    }

                    setFlashMessage('success', 'Projekt wurde aktualisiert.');
                }
            }
            break;

        case 'delete':
            $id = $_POST['id'];
            $count = $db->fetchOne(
                "SELECT COUNT(*) as cnt FROM projekt_gefaehrdungen WHERE projekt_id = ?",
                [$id]
            );
            if ($count['cnt'] > 0) {
                setFlashMessage('error', 'Projekt kann nicht gelöscht werden, da noch Gefährdungen existieren.');
            } else {
                $db->delete('projekt_tags', 'projekt_id = ?', [$id]);
                $db->delete('benutzer_projekte', 'projekt_id = ?', [$id]);
                $db->delete('projekte', 'id = ?', [$id]);
                setFlashMessage('success', 'Projekt wurde gelöscht.');
            }
            break;

        case 'archive':
            $id = $_POST['id'];
            $db->update('projekte', ['status' => 'archiviert'], 'id = :id', ['id' => $id]);
            setFlashMessage('success', 'Projekt wurde archiviert.');
            break;

        case 'unarchive':
            $id = $_POST['id'];
            $db->update('projekte', ['status' => 'abgeschlossen'], 'id = :id', ['id' => $id]);
            setFlashMessage('success', 'Projekt wurde wiederhergestellt.');
            break;

        case 'assign_user':
            $projektId = $_POST['projekt_id'];
            $benutzerId = $_POST['benutzer_id'];
            $berechtigung = $_POST['berechtigung'] ?? 'ansehen';

            // Prüfen ob bereits zugewiesen
            $exists = $db->fetchOne(
                "SELECT id FROM benutzer_projekte WHERE benutzer_id = ? AND projekt_id = ?",
                [$benutzerId, $projektId]
            );

            if (!$exists) {
                $db->insert('benutzer_projekte', [
                    'benutzer_id' => $benutzerId,
                    'projekt_id' => $projektId,
                    'berechtigung' => $berechtigung,
                    'zugewiesen_von' => $_SESSION['user_id']
                ]);
                setFlashMessage('success', 'Benutzer wurde dem Projekt zugewiesen.');
            } else {
                // Berechtigung aktualisieren
                $db->update(
                    'benutzer_projekte',
                    ['berechtigung' => $berechtigung],
                    'benutzer_id = :bid AND projekt_id = :pid',
                    ['bid' => $benutzerId, 'pid' => $projektId]
                );
                setFlashMessage('success', 'Berechtigung wurde aktualisiert.');
            }
            break;

        case 'remove_user':
            $projektId = $_POST['projekt_id'];
            $benutzerId = $_POST['benutzer_id'];
            $db->delete('benutzer_projekte', 'benutzer_id = ? AND projekt_id = ?', [$benutzerId, $projektId]);
            setFlashMessage('success', 'Benutzer wurde vom Projekt entfernt.');
            break;

        case 'add_standard_gefaehrdungen':
            $projektId = $_POST['projekt_id'];

            // Projekt-Tags laden
            $projektTagIds = $db->fetchAll(
                "SELECT tag_id FROM projekt_tags WHERE projekt_id = ?",
                [$projektId]
            );
            $tagIds = array_column($projektTagIds, 'tag_id');

            if (empty($tagIds)) {
                setFlashMessage('warning', 'Keine Tags für dieses Projekt definiert.');
                break;
            }

            // Gefährdungen mit passenden Tags finden
            $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
            $gefaehrdungen = $db->fetchAll("
                SELECT DISTINCT gb.*
                FROM gefaehrdung_bibliothek gb
                JOIN gefaehrdung_bibliothek_tags gbt ON gb.id = gbt.gefaehrdung_id
                WHERE gbt.tag_id IN ($placeholders)
            ", $tagIds);

            $addedCount = 0;
            foreach ($gefaehrdungen as $gef) {
                // Prüfen ob bereits vorhanden
                $exists = $db->fetchOne(
                    "SELECT id FROM projekt_gefaehrdungen WHERE projekt_id = ? AND gefaehrdung_bibliothek_id = ?",
                    [$projektId, $gef['id']]
                );

                if (!$exists) {
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
                        'erstellt_von' => $_SESSION['user_id']
                    ]);
                    $addedCount++;
                }
            }

            setFlashMessage('success', "$addedCount Standard-Gefährdungen wurden hinzugefügt.");
            break;
    }

    redirect('admin/projekte.php' . (isset($_GET['archiv']) ? '?archiv=1' : ''));
}

// Filter
$showArchived = isset($_GET['archiv']) && $_GET['archiv'] == '1';

// Projekte laden
$projekte = $db->fetchAll("
    SELECT p.*,
           CONCAT(b.vorname, ' ', b.nachname) as erstellt_von_name,
           (SELECT COUNT(*) FROM benutzer_projekte WHERE projekt_id = p.id) as benutzer_count,
           (SELECT COUNT(*) FROM projekt_gefaehrdungen WHERE projekt_id = p.id) as gef_count
    FROM projekte p
    LEFT JOIN benutzer b ON p.erstellt_von = b.id
    WHERE p.status " . ($showArchived ? "= 'archiviert'" : "!= 'archiviert'") . "
    ORDER BY p.zeitraum_von DESC
");

// Anzahl archivierter Projekte
$archivedCount = $db->fetchOne("SELECT COUNT(*) as cnt FROM projekte WHERE status = 'archiviert'")['cnt'];

// Alle Benutzer laden (für Zuweisung)
$alleBenutzer = $db->fetchAll("
    SELECT id, vorname, nachname, benutzername, rolle
    FROM benutzer
    WHERE aktiv = 1
    ORDER BY nachname, vorname
");

// Tags laden
$tags = $db->fetchAll("SELECT * FROM gefaehrdung_tags ORDER BY sortierung");

$pageTitle = 'Projektverwaltung';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="bi bi-folder me-2"></i>Projektverwaltung
                <?php if ($showArchived): ?>
                <span class="badge bg-secondary">Archiv</span>
                <?php endif; ?>
            </h1>
            <p class="text-muted mb-0">Projekte erstellen und Benutzer zuweisen</p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($showArchived): ?>
            <a href="<?= BASE_URL ?>/admin/projekte.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Aktive Projekte
            </a>
            <?php else: ?>
            <?php if ($archivedCount > 0): ?>
            <a href="<?= BASE_URL ?>/admin/projekte.php?archiv=1" class="btn btn-outline-secondary">
                <i class="bi bi-archive me-2"></i>Archiv (<?= $archivedCount ?>)
            </a>
            <?php endif; ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#projektModal">
                <i class="bi bi-plus-lg me-2"></i>Neues Projekt
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($projekte)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-folder display-4 text-muted"></i>
            <h5 class="mt-3">Keine Projekte vorhanden</h5>
            <p class="text-muted">Erstellen Sie Ihr erstes Projekt.</p>
        </div>
    </div>
    <?php else: ?>

    <div class="row">
        <?php foreach ($projekte as $p): ?>
        <?php
        // Projekt-Tags laden
        $pTags = $db->fetchAll("
            SELECT gt.* FROM gefaehrdung_tags gt
            JOIN projekt_tags pt ON gt.id = pt.tag_id
            WHERE pt.projekt_id = ?
        ", [$p['id']]);
        ?>
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0"><?= sanitize($p['name']) ?></h5>
                        <small class="text-muted">
                            <i class="bi bi-geo-alt me-1"></i><?= sanitize($p['location']) ?>
                        </small>
                    </div>
                    <?php
                    $statusColors = [
                        'geplant' => 'warning text-dark',
                        'aktiv' => 'success',
                        'abgeschlossen' => 'secondary',
                        'archiviert' => 'dark'
                    ];
                    $statusColor = $statusColors[$p['status']] ?? 'secondary';
                    ?>
                    <span class="badge bg-<?= $statusColor ?>">
                        <?= ucfirst($p['status']) ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-6">
                            <small class="text-muted">Zeitraum:</small><br>
                            <strong><?= date('d.m.Y', strtotime($p['zeitraum_von'])) ?> - <?= date('d.m.Y', strtotime($p['zeitraum_bis'])) ?></strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Art:</small><br>
                            <span class="badge bg-info">
                                <?= $p['indoor_outdoor'] === 'indoor' ? 'Indoor' : ($p['indoor_outdoor'] === 'outdoor' ? 'Outdoor' : 'Beides') ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($p['aufbau_datum'] || $p['abbau_datum']): ?>
                    <div class="row mb-3">
                        <?php if ($p['aufbau_datum']): ?>
                        <div class="col-6">
                            <small class="text-muted">Aufbau:</small><br>
                            <?= date('d.m.Y', strtotime($p['aufbau_datum'])) ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($p['abbau_datum']): ?>
                        <div class="col-6">
                            <small class="text-muted">Abbau:</small><br>
                            <?= date('d.m.Y', strtotime($p['abbau_datum'])) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Projekt-Tags -->
                    <?php if (!empty($pTags)): ?>
                    <div class="mb-3">
                        <small class="text-muted">Tags:</small><br>
                        <?php foreach ($pTags as $tag): ?>
                        <span class="badge" style="background-color: <?= $tag['farbe'] ?>"><?= sanitize($tag['name']) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Zugewiesene Benutzer -->
                    <h6 class="mt-3">
                        <i class="bi bi-people me-1"></i>Zugewiesene Benutzer (<?= $p['benutzer_count'] ?>)
                    </h6>
                    <?php
                    $zugewieseneBenutzer = $db->fetchAll("
                        SELECT b.id, b.vorname, b.nachname, b.benutzername, bp.berechtigung
                        FROM benutzer b
                        JOIN benutzer_projekte bp ON b.id = bp.benutzer_id
                        WHERE bp.projekt_id = ?
                    ", [$p['id']]);
                    ?>

                    <?php if (empty($zugewieseneBenutzer)): ?>
                    <p class="text-muted small">Keine Benutzer zugewiesen</p>
                    <?php else: ?>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <?php foreach ($zugewieseneBenutzer as $bu): ?>
                        <span class="badge bg-<?= $bu['berechtigung'] === 'bearbeiten' ? 'primary' : 'secondary' ?> d-flex align-items-center gap-1">
                            <i class="bi bi-<?= $bu['berechtigung'] === 'bearbeiten' ? 'pencil' : 'eye' ?>"></i>
                            <?= sanitize($bu['vorname'] . ' ' . $bu['nachname']) ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="remove_user">
                                <input type="hidden" name="projekt_id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="benutzer_id" value="<?= $bu['id'] ?>">
                                <button type="submit" class="btn-close btn-close-white" style="font-size: 0.5rem;" title="Entfernen"></button>
                            </form>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Benutzer hinzufügen -->
                    <form method="POST" class="d-flex gap-2">
                        <input type="hidden" name="action" value="assign_user">
                        <input type="hidden" name="projekt_id" value="<?= $p['id'] ?>">
                        <select name="benutzer_id" class="form-select form-select-sm" required style="flex: 2;">
                            <option value="">Benutzer...</option>
                            <?php foreach ($alleBenutzer as $bu): ?>
                            <option value="<?= $bu['id'] ?>"><?= sanitize($bu['vorname'] . ' ' . $bu['nachname']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="berechtigung" class="form-select form-select-sm" style="flex: 1;">
                            <option value="ansehen">Ansehen</option>
                            <option value="bearbeiten">Bearbeiten</option>
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-plus"></i>
                        </button>
                    </form>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-primary"><?= $p['gef_count'] ?> Gefährdung(en)</span>
                        <a href="<?= BASE_URL ?>/projekt.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary ms-2">
                            <i class="bi bi-eye me-1"></i>Ansehen
                        </a>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <?php if ($p['status'] === 'archiviert'): ?>
                        <!-- Archiviertes Projekt: Wiederherstellen -->
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="unarchive">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button type="submit" class="btn btn-outline-success" title="Wiederherstellen">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </button>
                        </form>
                        <button type="button" class="btn btn-outline-primary"
                                onclick="editProjekt(<?= htmlspecialchars(json_encode($p)) ?>, <?= htmlspecialchars(json_encode(array_column($pTags, 'id'))) ?>)">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <?php else: ?>
                        <!-- Aktives Projekt -->
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="add_standard_gefaehrdungen">
                            <input type="hidden" name="projekt_id" value="<?= $p['id'] ?>">
                            <button type="submit" class="btn btn-outline-success" title="Standard-Gefährdungen hinzufügen">
                                <i class="bi bi-magic"></i>
                            </button>
                        </form>
                        <button type="button" class="btn btn-outline-primary"
                                onclick="editProjekt(<?= htmlspecialchars(json_encode($p)) ?>, <?= htmlspecialchars(json_encode(array_column($pTags, 'id'))) ?>)">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Projekt wirklich archivieren?')">
                            <input type="hidden" name="action" value="archive">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button type="submit" class="btn btn-outline-secondary" title="Archivieren">
                                <i class="bi bi-archive"></i>
                            </button>
                        </form>
                        <?php if ($p['gef_count'] == 0): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Projekt wirklich löschen?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger" title="Löschen">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal: Projekt -->
<div class="modal fade" id="projektModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" id="projekt_action" value="create">
                <input type="hidden" name="id" id="projekt_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="projektModalTitle">Neues Projekt</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Projektname *</label>
                            <input type="text" class="form-control" name="name" id="p_name" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="p_status">
                                <option value="geplant">Geplant</option>
                                <option value="aktiv">Aktiv</option>
                                <option value="abgeschlossen">Abgeschlossen</option>
                                <option value="archiviert">Archiviert</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Location *</label>
                            <input type="text" class="form-control" name="location" id="p_location" required placeholder="z.B. Messe München, Halle 5">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Indoor/Outdoor *</label>
                            <select class="form-select" name="indoor_outdoor" id="p_indoor_outdoor" required>
                                <option value="indoor">Indoor</option>
                                <option value="outdoor">Outdoor</option>
                                <option value="beides">Beides</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Zeitraum von *</label>
                            <input type="date" class="form-control" name="zeitraum_von" id="p_zeitraum_von" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Zeitraum bis *</label>
                            <input type="date" class="form-control" name="zeitraum_bis" id="p_zeitraum_bis" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Aufbau-Datum</label>
                            <input type="date" class="form-control" name="aufbau_datum" id="p_aufbau_datum">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Abbau-Datum</label>
                            <input type="date" class="form-control" name="abbau_datum" id="p_abbau_datum">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Zusätzliche Tags (für automatische Gefährdungen)</label>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($tags as $tag): ?>
                            <?php if (!in_array($tag['name'], ['indoor', 'outdoor', 'standard'])): ?>
                            <div class="form-check">
                                <input class="form-check-input tag-check" type="checkbox" name="tags[]" value="<?= $tag['id'] ?>" id="ptag_<?= $tag['id'] ?>">
                                <label class="form-check-label" for="ptag_<?= $tag['id'] ?>">
                                    <span class="badge" style="background-color: <?= $tag['farbe'] ?>"><?= sanitize($tag['name']) ?></span>
                                </label>
                            </div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted">Indoor/Outdoor wird automatisch basierend auf der Auswahl oben gesetzt.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Beschreibung</label>
                        <textarea class="form-control" name="beschreibung" id="p_beschreibung" rows="3"></textarea>
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
function editProjekt(data, tagIds) {
    document.getElementById('projekt_action').value = 'update';
    document.getElementById('projekt_id').value = data.id;
    document.getElementById('projektModalTitle').textContent = 'Projekt bearbeiten';
    document.getElementById('p_name').value = data.name;
    document.getElementById('p_location').value = data.location;
    document.getElementById('p_zeitraum_von').value = data.zeitraum_von;
    document.getElementById('p_zeitraum_bis').value = data.zeitraum_bis;
    document.getElementById('p_aufbau_datum').value = data.aufbau_datum || '';
    document.getElementById('p_abbau_datum').value = data.abbau_datum || '';
    document.getElementById('p_indoor_outdoor').value = data.indoor_outdoor;
    document.getElementById('p_status').value = data.status;
    document.getElementById('p_beschreibung').value = data.beschreibung || '';

    // Tags setzen
    document.querySelectorAll('.tag-check').forEach(cb => {
        cb.checked = tagIds.includes(parseInt(cb.value));
    });

    new bootstrap.Modal(document.getElementById('projektModal')).show();
}

document.getElementById('projektModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('projekt_action').value = 'create';
    document.getElementById('projekt_id').value = '';
    document.getElementById('projektModalTitle').textContent = 'Neues Projekt';
    document.querySelectorAll('.tag-check').forEach(cb => cb.checked = false);
    this.querySelector('form').reset();
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
