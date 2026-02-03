<?php
/**
 * Projekt-Detail - Gefährdungsbeurteilung für ein Projekt
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

requireLogin();

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$isAdmin = hasRole(ROLE_ADMIN);

$projektId = $_GET['id'] ?? 0;

// Projekt laden und Berechtigung prüfen
$projekt = $db->fetchOne("SELECT * FROM projekte WHERE id = ?", [$projektId]);

if (!$projekt) {
    setFlashMessage('error', 'Projekt nicht gefunden.');
    redirect('projekte.php');
}

// Berechtigung prüfen
if ($isAdmin) {
    $berechtigung = 'bearbeiten';
} else {
    $access = $db->fetchOne(
        "SELECT berechtigung FROM benutzer_projekte WHERE benutzer_id = ? AND projekt_id = ?",
        [$userId, $projektId]
    );
    if (!$access) {
        setFlashMessage('error', 'Sie haben keinen Zugriff auf dieses Projekt.');
        redirect('projekte.php');
    }
    $berechtigung = $access['berechtigung'];
}

$canEdit = $berechtigung === 'bearbeiten';

// POST-Aktionen verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_from_library':
            // Gefährdung aus Bibliothek hinzufügen
            $bibId = $_POST['bibliothek_id'];
            $bibGef = $db->fetchOne("SELECT * FROM gefaehrdung_bibliothek WHERE id = ?", [$bibId]);

            if ($bibGef) {
                $db->insert('projekt_gefaehrdungen', [
                    'projekt_id' => $projektId,
                    'gefaehrdung_bibliothek_id' => $bibId,
                    'titel' => $bibGef['titel'],
                    'beschreibung' => $bibGef['beschreibung'],
                    'kategorie_id' => $bibGef['kategorie_id'],
                    'faktor_id' => $bibGef['faktor_id'],
                    'schadenschwere' => $bibGef['standard_schadenschwere'] ?? 2,
                    'wahrscheinlichkeit' => $bibGef['standard_wahrscheinlichkeit'] ?? 2,
                    'massnahmen' => $bibGef['typische_massnahmen'],
                    'erstellt_von' => $userId
                ]);
                setFlashMessage('success', 'Gefährdung wurde hinzugefügt.');
            }
            break;

        case 'add_new':
            // Neue Gefährdung erstellen
            $data = [
                'projekt_id' => $projektId,
                'titel' => $_POST['titel'],
                'beschreibung' => $_POST['beschreibung'],
                'kategorie_id' => $_POST['kategorie_id'] ?: null,
                'faktor_id' => $_POST['faktor_id'] ?: null,
                'schadenschwere' => $_POST['schadenschwere'],
                'wahrscheinlichkeit' => $_POST['wahrscheinlichkeit'],
                'massnahmen' => $_POST['massnahmen'] ?: null,
                'massnahmen_art' => $_POST['massnahmen_art'] ?: null,
                'verantwortlich' => $_POST['verantwortlich'] ?: null,
                'termin' => $_POST['termin'] ?: null,
                'erstellt_von' => $userId
            ];

            $newId = $db->insert('projekt_gefaehrdungen', $data);

            // In Bibliothek speichern?
            if (!empty($_POST['save_to_library'])) {
                $libData = [
                    'kategorie_id' => $data['kategorie_id'],
                    'faktor_id' => $data['faktor_id'],
                    'titel' => $data['titel'],
                    'beschreibung' => $data['beschreibung'],
                    'typische_massnahmen' => $data['massnahmen'],
                    'standard_schadenschwere' => $data['schadenschwere'],
                    'standard_wahrscheinlichkeit' => $data['wahrscheinlichkeit'],
                    'ist_standard' => !empty($_POST['ist_standard']) ? 1 : 0,
                    'erstellt_von' => $userId
                ];
                $libId = $db->insert('gefaehrdung_bibliothek', $libData);

                // Tags zuweisen
                if (!empty($_POST['tags']) && is_array($_POST['tags'])) {
                    foreach ($_POST['tags'] as $tagId) {
                        $db->query(
                            "INSERT IGNORE INTO gefaehrdung_bibliothek_tags (gefaehrdung_id, tag_id) VALUES (?, ?)",
                            [$libId, $tagId]
                        );
                    }
                }

                // Projekt-Gefährdung mit Bibliothek verknüpfen
                $db->update('projekt_gefaehrdungen', ['gefaehrdung_bibliothek_id' => $libId], 'id = :id', ['id' => $newId]);

                setFlashMessage('success', 'Gefährdung wurde erstellt und zur Bibliothek hinzugefügt.');
            } else {
                setFlashMessage('success', 'Gefährdung wurde erstellt.');
            }
            break;

        case 'update':
            // Gefährdung aktualisieren
            $gefId = $_POST['gefaehrdung_id'];
            $data = [
                'titel' => $_POST['titel'],
                'beschreibung' => $_POST['beschreibung'],
                'schadenschwere' => $_POST['schadenschwere'],
                'wahrscheinlichkeit' => $_POST['wahrscheinlichkeit'],
                'massnahmen' => $_POST['massnahmen'] ?: null,
                'massnahmen_art' => $_POST['massnahmen_art'] ?: null,
                'verantwortlich' => $_POST['verantwortlich'] ?: null,
                'termin' => $_POST['termin'] ?: null,
                'status' => $_POST['status'],
                'schadenschwere_nach' => $_POST['schadenschwere_nach'] ?: null,
                'wahrscheinlichkeit_nach' => $_POST['wahrscheinlichkeit_nach'] ?: null
            ];
            $db->update('projekt_gefaehrdungen', $data, 'id = :id AND projekt_id = :pid', ['id' => $gefId, 'pid' => $projektId]);
            setFlashMessage('success', 'Gefährdung wurde aktualisiert.');
            break;

        case 'delete':
            $gefId = $_POST['gefaehrdung_id'];
            $db->delete('projekt_gefaehrdungen', 'id = ? AND projekt_id = ?', [$gefId, $projektId]);
            setFlashMessage('success', 'Gefährdung wurde entfernt.');
            break;
    }

    redirect('projekt.php?id=' . $projektId);
}

// Gefährdungen des Projekts laden
$gefaehrdungen = $db->fetchAll("
    SELECT pg.*, gk.name as kategorie_name, gf.name as faktor_name, gf.nummer as faktor_nummer
    FROM projekt_gefaehrdungen pg
    LEFT JOIN gefaehrdung_kategorien gk ON pg.kategorie_id = gk.id
    LEFT JOIN gefaehrdung_faktoren gf ON pg.faktor_id = gf.id
    WHERE pg.projekt_id = ?
    ORDER BY gk.sortierung, pg.titel
", [$projektId]);

// Bibliothek laden (für Modal)
$bibliothek = $db->fetchAll("
    SELECT gb.*, gk.name as kategorie_name
    FROM gefaehrdung_bibliothek gb
    LEFT JOIN gefaehrdung_kategorien gk ON gb.kategorie_id = gk.id
    ORDER BY gk.sortierung, gb.titel
");

// Kategorien und Faktoren laden
$kategorien = $db->fetchAll("SELECT * FROM gefaehrdung_kategorien ORDER BY sortierung");
$faktoren = $db->fetchAll("
    SELECT gf.*, gk.name as kategorie_name
    FROM gefaehrdung_faktoren gf
    LEFT JOIN gefaehrdung_kategorien gk ON gf.kategorie_id = gk.id
    ORDER BY gk.sortierung, gf.nummer
");

// Tags laden
$tags = $db->fetchAll("SELECT * FROM gefaehrdung_tags ORDER BY sortierung");

// Projekt-Tags laden
$projektTags = $db->fetchAll("
    SELECT tag_id FROM projekt_tags WHERE projekt_id = ?
", [$projektId]);
$projektTagIds = array_column($projektTags, 'tag_id');

$pageTitle = $projekt['name'] . ' - Gefährdungen';
require_once __DIR__ . '/templates/header.php';

// Globale PHP-Variablen für JS
global $SCHADENSCHWERE, $WAHRSCHEINLICHKEIT, $STOP_PRINZIP;
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/projekte.php">Projekte</a></li>
                    <li class="breadcrumb-item active"><?= sanitize($projekt['name']) ?></li>
                </ol>
            </nav>
            <h1 class="h3 mb-0">
                <i class="bi bi-folder me-2"></i><?= sanitize($projekt['name']) ?>
            </h1>
            <p class="text-muted">
                <i class="bi bi-geo-alt me-1"></i><?= sanitize($projekt['location']) ?>
                <span class="mx-2">|</span>
                <i class="bi bi-calendar me-1"></i><?= date('d.m.Y', strtotime($projekt['zeitraum_von'])) ?> - <?= date('d.m.Y', strtotime($projekt['zeitraum_bis'])) ?>
                <span class="mx-2">|</span>
                <span class="badge bg-<?= $projekt['indoor_outdoor'] === 'indoor' ? 'info' : ($projekt['indoor_outdoor'] === 'outdoor' ? 'success' : 'primary') ?>">
                    <?= ucfirst($projekt['indoor_outdoor']) ?>
                </span>
            </p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($canEdit): ?>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bibliothekModal">
                <i class="bi bi-plus-lg me-2"></i>Aus Bibliothek
            </button>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#neueGefaehrdungModal">
                <i class="bi bi-plus-circle me-2"></i>Neue Gefährdung
            </button>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/api/export.php?type=projekt&id=<?= $projektId ?>" class="btn btn-outline-secondary">
                <i class="bi bi-download me-2"></i>Export
            </a>
        </div>
    </div>

    <!-- Statistik-Karten -->
    <div class="row mb-4">
        <?php
        $totalGef = count($gefaehrdungen);
        $hoheRisiken = count(array_filter($gefaehrdungen, fn($g) => $g['risikobewertung'] >= 9));
        $offene = count(array_filter($gefaehrdungen, fn($g) => $g['status'] === 'offen'));
        $erledigt = count(array_filter($gefaehrdungen, fn($g) => $g['status'] === 'erledigt'));
        ?>
        <div class="col-md-3 col-6 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body py-3">
                    <h3 class="mb-0"><?= $totalGef ?></h3>
                    <small>Gefährdungen gesamt</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="card bg-danger text-white">
                <div class="card-body py-3">
                    <h3 class="mb-0"><?= $hoheRisiken ?></h3>
                    <small>Hohe Risiken</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="card bg-warning text-dark">
                <div class="card-body py-3">
                    <h3 class="mb-0"><?= $offene ?></h3>
                    <small>Offene Maßnahmen</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body py-3">
                    <h3 class="mb-0"><?= $erledigt ?></h3>
                    <small>Erledigt</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Gefährdungsliste -->
    <?php if (empty($gefaehrdungen)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-exclamation-triangle display-4 text-muted"></i>
            <h5 class="mt-3">Noch keine Gefährdungen erfasst</h5>
            <p class="text-muted">Fügen Sie Gefährdungen aus der Bibliothek hinzu oder erstellen Sie neue.</p>
            <?php if ($canEdit): ?>
            <div class="d-flex gap-2 justify-content-center">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bibliothekModal">
                    <i class="bi bi-plus-lg me-2"></i>Aus Bibliothek hinzufügen
                </button>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#neueGefaehrdungModal">
                    <i class="bi bi-plus-circle me-2"></i>Neue Gefährdung erstellen
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Erfasste Gefährdungen</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 30%">Gefährdung</th>
                        <th style="width: 15%">Risiko (vorher)</th>
                        <th style="width: 25%">Maßnahmen</th>
                        <th style="width: 15%">Risiko (nachher)</th>
                        <th style="width: 10%">Status</th>
                        <th style="width: 5%"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gefaehrdungen as $gef): ?>
                    <tr>
                        <td>
                            <strong><?= sanitize($gef['titel']) ?></strong>
                            <?php if ($gef['kategorie_name']): ?>
                            <br><small class="text-muted"><?= sanitize($gef['kategorie_name']) ?></small>
                            <?php endif; ?>
                            <?php if ($gef['faktor_name']): ?>
                            <br><span class="badge bg-secondary"><?= sanitize($gef['faktor_nummer']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $rColor = getRiskColor($gef['risikobewertung']);
                            $rLevel = getRiskLevel($gef['risikobewertung']);
                            ?>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge" style="background-color: <?= $rColor ?>; color: <?= $gef['risikobewertung'] >= 9 ? '#fff' : '#000' ?>">
                                    R = <?= $gef['risikobewertung'] ?>
                                </span>
                                <small class="text-muted"><?= $rLevel ?></small>
                            </div>
                            <small class="text-muted">
                                S=<?= $gef['schadenschwere'] ?>, W=<?= $gef['wahrscheinlichkeit'] ?>
                            </small>
                        </td>
                        <td>
                            <?php if ($gef['massnahmen']): ?>
                            <small><?= nl2br(sanitize(substr($gef['massnahmen'], 0, 100))) ?><?= strlen($gef['massnahmen']) > 100 ? '...' : '' ?></small>
                            <?php if ($gef['massnahmen_art']): ?>
                            <br><span class="badge bg-<?= $gef['massnahmen_art'] === 'S' ? 'danger' : ($gef['massnahmen_art'] === 'T' ? 'warning text-dark' : ($gef['massnahmen_art'] === 'O' ? 'info' : 'success')) ?>">
                                <?= $STOP_PRINZIP[$gef['massnahmen_art']]['name'] ?>
                            </span>
                            <?php endif; ?>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($gef['risikobewertung_nach']): ?>
                            <?php
                            $rColorNach = getRiskColor($gef['risikobewertung_nach']);
                            $rLevelNach = getRiskLevel($gef['risikobewertung_nach']);
                            ?>
                            <span class="badge" style="background-color: <?= $rColorNach ?>; color: <?= $gef['risikobewertung_nach'] >= 9 ? '#fff' : '#000' ?>">
                                R = <?= $gef['risikobewertung_nach'] ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= $gef['status'] === 'erledigt' ? 'success' : ($gef['status'] === 'in_bearbeitung' ? 'warning text-dark' : 'secondary') ?>">
                                <?= $gef['status'] === 'in_bearbeitung' ? 'In Bearbeitung' : ucfirst($gef['status']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-link text-muted" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#" onclick="editGefaehrdung(<?= htmlspecialchars(json_encode($gef)) ?>)">
                                            <i class="bi bi-<?= $canEdit ? 'pencil' : 'eye' ?> me-2"></i><?= $canEdit ? 'Bearbeiten' : 'Details' ?>
                                        </a>
                                    </li>
                                    <?php if ($canEdit): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" onsubmit="return confirm('Gefährdung wirklich entfernen?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="gefaehrdung_id" value="<?= $gef['id'] ?>">
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="bi bi-trash me-2"></i>Entfernen
                                            </button>
                                        </form>
                                    </li>
                                    <?php endif; ?>
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

<!-- Modal: Bibliothek -->
<div class="modal fade" id="bibliothekModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-book me-2"></i>Gefährdung aus Bibliothek hinzufügen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" class="form-control" id="bibliothekSuche" placeholder="Suchen...">
                </div>
                <div class="row" id="bibliothekListe" style="max-height: 400px; overflow-y: auto;">
                    <?php foreach ($bibliothek as $bib): ?>
                    <div class="col-md-6 mb-3 bib-item" data-titel="<?= strtolower($bib['titel']) ?>" data-kategorie="<?= strtolower($bib['kategorie_name'] ?? '') ?>">
                        <div class="card h-100">
                            <div class="card-body py-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?= sanitize($bib['titel']) ?></strong>
                                        <?php if ($bib['kategorie_name']): ?>
                                        <br><small class="text-muted"><?= sanitize($bib['kategorie_name']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <form method="POST" class="ms-2">
                                        <input type="hidden" name="action" value="add_from_library">
                                        <input type="hidden" name="bibliothek_id" value="<?= $bib['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success" title="Hinzufügen">
                                            <i class="bi bi-plus-lg"></i>
                                        </button>
                                    </form>
                                </div>
                                <small class="text-muted"><?= sanitize(substr($bib['beschreibung'], 0, 100)) ?>...</small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Neue Gefährdung -->
<div class="modal fade" id="neueGefaehrdungModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="gefaehrdungForm">
                <input type="hidden" name="action" id="form_action" value="add_new">
                <input type="hidden" name="gefaehrdung_id" id="form_gefaehrdung_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="gefaehrdungModalTitle"><i class="bi bi-plus-circle me-2"></i>Neue Gefährdung</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="form-label">Titel *</label>
                            <input type="text" class="form-control" name="titel" id="gef_titel" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kategorie</label>
                            <select class="form-select" name="kategorie_id" id="gef_kategorie_id">
                                <option value="">-- Auswählen --</option>
                                <?php foreach ($kategorien as $kat): ?>
                                <option value="<?= $kat['id'] ?>"><?= sanitize($kat['nummer'] . ' - ' . $kat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Gefährdungsfaktor</label>
                            <select class="form-select" name="faktor_id" id="gef_faktor_id">
                                <option value="">-- Auswählen --</option>
                                <?php
                                $currentKat = null;
                                foreach ($faktoren as $f):
                                    if ($currentKat !== $f['kategorie_name']):
                                        if ($currentKat !== null) echo '</optgroup>';
                                        $currentKat = $f['kategorie_name'];
                                        echo '<optgroup label="' . sanitize($currentKat ?? 'Ohne Kategorie') . '">';
                                    endif;
                                ?>
                                <option value="<?= $f['id'] ?>"><?= sanitize($f['nummer'] . ' - ' . $f['name']) ?></option>
                                <?php endforeach; ?>
                                <?php if ($currentKat !== null) echo '</optgroup>'; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Beschreibung *</label>
                        <textarea class="form-control" name="beschreibung" id="gef_beschreibung" rows="3" required></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Schadenschwere (S) *</label>
                            <select class="form-select" name="schadenschwere" id="gef_schadenschwere" required>
                                <?php foreach ($SCHADENSCHWERE as $val => $info): ?>
                                <option value="<?= $val ?>" <?= $val == 2 ? 'selected' : '' ?>><?= $val ?> - <?= $info['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Wahrscheinlichkeit (W) *</label>
                            <select class="form-select" name="wahrscheinlichkeit" id="gef_wahrscheinlichkeit" required>
                                <?php foreach ($WAHRSCHEINLICHKEIT as $val => $info): ?>
                                <option value="<?= $val ?>" <?= $val == 2 ? 'selected' : '' ?>><?= $val ?> - <?= $info['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <hr>
                    <h6>Maßnahmen</h6>

                    <div class="mb-3">
                        <label class="form-label">Maßnahmen</label>
                        <textarea class="form-control" name="massnahmen" id="gef_massnahmen" rows="3"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">STOP-Prinzip</label>
                            <select class="form-select" name="massnahmen_art" id="gef_massnahmen_art">
                                <option value="">-- Auswählen --</option>
                                <?php foreach ($STOP_PRINZIP as $key => $info): ?>
                                <option value="<?= $key ?>"><?= $key ?> - <?= $info['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Verantwortlich</label>
                            <input type="text" class="form-control" name="verantwortlich" id="gef_verantwortlich">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Termin</label>
                            <input type="date" class="form-control" name="termin" id="gef_termin">
                        </div>
                    </div>

                    <!-- Nur bei Bearbeitung: Status und Risiko nachher -->
                    <div id="editOnlyFields" style="display: none;">
                        <hr>
                        <h6>Nach Maßnahmen</h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" id="gef_status">
                                    <option value="offen">Offen</option>
                                    <option value="in_bearbeitung">In Bearbeitung</option>
                                    <option value="erledigt">Erledigt</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">S (nachher)</label>
                                <select class="form-select" name="schadenschwere_nach" id="gef_schadenschwere_nach">
                                    <option value="">-</option>
                                    <?php foreach ($SCHADENSCHWERE as $val => $info): ?>
                                    <option value="<?= $val ?>"><?= $val ?> - <?= $info['name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">W (nachher)</label>
                                <select class="form-select" name="wahrscheinlichkeit_nach" id="gef_wahrscheinlichkeit_nach">
                                    <option value="">-</option>
                                    <?php foreach ($WAHRSCHEINLICHKEIT as $val => $info): ?>
                                    <option value="<?= $val ?>"><?= $val ?> - <?= $info['name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Nur bei Neu: In Bibliothek speichern -->
                    <div id="newOnlyFields">
                        <hr>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="save_to_library" id="save_to_library" value="1">
                            <label class="form-check-label" for="save_to_library">
                                <strong>In Bibliothek speichern</strong> (zur Wiederverwendung)
                            </label>
                        </div>

                        <div id="libraryOptions" style="display: none;">
                            <div class="form-check mb-2 ms-4">
                                <input class="form-check-input" type="checkbox" name="ist_standard" id="ist_standard" value="1">
                                <label class="form-check-label" for="ist_standard">
                                    Als Standard-Gefährdung markieren
                                </label>
                            </div>

                            <div class="ms-4">
                                <label class="form-label">Tags (für automatische Zuweisung)</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($tags as $tag): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="tags[]" value="<?= $tag['id'] ?>" id="tag_<?= $tag['id'] ?>">
                                        <label class="form-check-label" for="tag_<?= $tag['id'] ?>">
                                            <span class="badge" style="background-color: <?= $tag['farbe'] ?>"><?= sanitize($tag['name']) ?></span>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
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
// Bibliothek-Suche
document.getElementById('bibliothekSuche').addEventListener('input', function() {
    const search = this.value.toLowerCase();
    document.querySelectorAll('.bib-item').forEach(item => {
        const titel = item.dataset.titel;
        const kategorie = item.dataset.kategorie;
        item.style.display = (titel.includes(search) || kategorie.includes(search)) ? '' : 'none';
    });
});

// Checkbox für Bibliothek-Optionen
document.getElementById('save_to_library').addEventListener('change', function() {
    document.getElementById('libraryOptions').style.display = this.checked ? 'block' : 'none';
});

// Gefährdung bearbeiten
function editGefaehrdung(data) {
    document.getElementById('form_action').value = 'update';
    document.getElementById('form_gefaehrdung_id').value = data.id;
    document.getElementById('gefaehrdungModalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Gefährdung bearbeiten';

    document.getElementById('gef_titel').value = data.titel;
    document.getElementById('gef_beschreibung').value = data.beschreibung;
    document.getElementById('gef_kategorie_id').value = data.kategorie_id || '';
    document.getElementById('gef_faktor_id').value = data.faktor_id || '';
    document.getElementById('gef_schadenschwere').value = data.schadenschwere;
    document.getElementById('gef_wahrscheinlichkeit').value = data.wahrscheinlichkeit;
    document.getElementById('gef_massnahmen').value = data.massnahmen || '';
    document.getElementById('gef_massnahmen_art').value = data.massnahmen_art || '';
    document.getElementById('gef_verantwortlich').value = data.verantwortlich || '';
    document.getElementById('gef_termin').value = data.termin || '';
    document.getElementById('gef_status').value = data.status;
    document.getElementById('gef_schadenschwere_nach').value = data.schadenschwere_nach || '';
    document.getElementById('gef_wahrscheinlichkeit_nach').value = data.wahrscheinlichkeit_nach || '';

    document.getElementById('editOnlyFields').style.display = 'block';
    document.getElementById('newOnlyFields').style.display = 'none';

    new bootstrap.Modal(document.getElementById('neueGefaehrdungModal')).show();
}

// Modal zurücksetzen beim Schließen
document.getElementById('neueGefaehrdungModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('form_action').value = 'add_new';
    document.getElementById('form_gefaehrdung_id').value = '';
    document.getElementById('gefaehrdungModalTitle').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Neue Gefährdung';
    document.getElementById('editOnlyFields').style.display = 'none';
    document.getElementById('newOnlyFields').style.display = 'block';
    document.getElementById('libraryOptions').style.display = 'none';
    this.querySelector('form').reset();
});
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
