<?php
/**
 * Gefährdungsbibliothek
 * Gespeicherte Gefährdungen zur Wiederverwendung - mit Kategorien-Accordion
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

requireRole(ROLE_EDITOR);

$db = Database::getInstance();

// Tags laden
$tags = $db->fetchAll("SELECT * FROM gefaehrdung_tags ORDER BY sortierung");

// Gefährdungsarten laden (die 13 festen)
$gefaehrdungsarten = $db->fetchAll("SELECT * FROM gefaehrdungsarten ORDER BY nummer");

// Kategorien laden (nur globale)
$kategorien = $db->fetchAll("SELECT * FROM arbeits_kategorien WHERE ist_global = 1 ORDER BY nummer");

// Unterkategorien laden
$unterkategorien = $db->fetchAll("
    SELECT auk.*, ak.nummer as kat_nummer
    FROM arbeits_unterkategorien auk
    JOIN arbeits_kategorien ak ON auk.kategorie_id = ak.id
    WHERE ak.ist_global = 1
    ORDER BY ak.nummer, auk.nummer
");

// Aktion verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
        case 'update':
            // STOP-Maßnahmen zusammenführen für typische_massnahmen (Kompatibilität)
            $allMassnahmen = [];
            if (!empty($_POST['massnahme_s'])) $allMassnahmen[] = "S: " . $_POST['massnahme_s'];
            if (!empty($_POST['massnahme_t'])) $allMassnahmen[] = "T: " . $_POST['massnahme_t'];
            if (!empty($_POST['massnahme_o'])) $allMassnahmen[] = "O: " . $_POST['massnahme_o'];
            if (!empty($_POST['massnahme_p'])) $allMassnahmen[] = "P: " . $_POST['massnahme_p'];

            $data = [
                'gefaehrdungsart_id' => $_POST['gefaehrdungsart_id'] ?: null,
                'kategorie_id' => $_POST['kategorie_id'] ?: null,
                'unterkategorie_id' => $_POST['unterkategorie_id'] ?: null,
                'titel' => $_POST['titel'],
                'beschreibung' => $_POST['beschreibung'],
                'typische_massnahmen' => !empty($allMassnahmen) ? implode("\n", $allMassnahmen) : null,
                'standard_schadenschwere' => $_POST['schadenschwere'] ?? 2,
                'standard_wahrscheinlichkeit' => $_POST['wahrscheinlichkeit'] ?? 2,
                'stop_s' => isset($_POST['stop_s']) ? 1 : 0,
                'stop_t' => isset($_POST['stop_t']) ? 1 : 0,
                'stop_o' => isset($_POST['stop_o']) ? 1 : 0,
                'stop_p' => isset($_POST['stop_p']) ? 1 : 0,
                'massnahme_s' => $_POST['massnahme_s'] ?: null,
                'massnahme_t' => $_POST['massnahme_t'] ?: null,
                'massnahme_o' => $_POST['massnahme_o'] ?: null,
                'massnahme_p' => $_POST['massnahme_p'] ?: null,
                'verantwortlich' => $_POST['verantwortlich'] ?: null,
                'schadenschwere_nachher' => $_POST['schadenschwere_nachher'] ?: null,
                'wahrscheinlichkeit_nachher' => $_POST['wahrscheinlichkeit_nachher'] ?: null,
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
$katFilter = $_GET['kat'] ?? '';

$sql = "
    SELECT gb.*,
           ga.name as gefaehrdungsart_name, ga.nummer as gefaehrdungsart_nummer,
           ak.name as kategorie_name, ak.nummer as kategorie_nummer,
           auk.name as unterkategorie_name, auk.nummer as unterkategorie_nummer,
           CONCAT(b.vorname, ' ', b.nachname) as erstellt_von_name,
           (SELECT COUNT(*) FROM projekt_gefaehrdungen WHERE gefaehrdung_bibliothek_id = gb.id) as verwendung_count
    FROM gefaehrdung_bibliothek gb
    LEFT JOIN gefaehrdungsarten ga ON gb.gefaehrdungsart_id = ga.id
    LEFT JOIN arbeits_kategorien ak ON gb.kategorie_id = ak.id
    LEFT JOIN arbeits_unterkategorien auk ON gb.unterkategorie_id = auk.id
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

if ($katFilter) {
    if ($katFilter === '0') {
        $where[] = "gb.kategorie_id IS NULL";
    } else {
        $where[] = "gb.kategorie_id = ?";
        $params[] = $katFilter;
    }
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

$sql .= " ORDER BY ak.nummer, auk.nummer, gb.titel";

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

// Nach Kategorien gruppieren
$gefNachKategorie = [];
foreach ($gefaehrdungen as $gef) {
    $katKey = $gef['kategorie_id'] ? $gef['kategorie_id'] : 0;
    $katName = $gef['kategorie_name'] ? $gef['kategorie_nummer'] . '. ' . $gef['kategorie_name'] : 'Ohne Kategorie';
    if (!isset($gefNachKategorie[$katKey])) {
        $gefNachKategorie[$katKey] = [
            'name' => $katName,
            'nummer' => $gef['kategorie_nummer'] ?? 999,
            'items' => []
        ];
    }
    $gefNachKategorie[$katKey]['items'][] = $gef;
}
uasort($gefNachKategorie, fn($a, $b) => ($a['nummer'] ?? 999) <=> ($b['nummer'] ?? 999));

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

    <!-- Statistik-Karten -->
    <div class="row mb-3">
        <?php
        $totalGef = count($gefaehrdungen);
        $standardGef = count(array_filter($gefaehrdungen, fn($g) => $g['ist_standard'] == 1));
        $mitTags = count(array_filter($gefaehrdungen, fn($g) => !empty($gefTagsMap[$g['id']])));
        ?>
        <div class="col-md-4 col-6 mb-2">
            <div class="card bg-primary text-white">
                <div class="card-body py-2">
                    <h4 class="mb-0"><?= $totalGef ?></h4>
                    <small>Gefährdungen gesamt</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-6 mb-2">
            <div class="card bg-success text-white">
                <div class="card-body py-2">
                    <h4 class="mb-0"><?= $standardGef ?></h4>
                    <small>Standard-Gefährdungen</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-6 mb-2">
            <div class="card bg-info text-white">
                <div class="card-body py-2">
                    <h4 class="mb-0"><?= count($gefNachKategorie) ?></h4>
                    <small>Kategorien</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <input type="text" name="q" class="form-control form-control-sm" placeholder="Suchen..."
                           value="<?= sanitize($search) ?>">
                </div>
                <div class="col-md-2">
                    <select name="kat" class="form-select form-select-sm">
                        <option value="">Alle Kategorien</option>
                        <option value="0" <?= $katFilter === '0' ? 'selected' : '' ?>>Ohne Kategorie</option>
                        <?php foreach ($kategorien as $kat): ?>
                        <option value="<?= $kat['id'] ?>" <?= $katFilter == $kat['id'] ? 'selected' : '' ?>>
                            <?= $kat['nummer'] ?>. <?= sanitize($kat['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
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
                        <i class="bi bi-search"></i> Filtern
                    </button>
                    <?php if ($search || $tagFilter || $katFilter): ?>
                    <a href="<?= BASE_URL ?>/bibliothek/gefaehrdungen.php" class="btn btn-sm btn-outline-secondary">
                        Zurücksetzen
                    </a>
                    <?php endif; ?>
                </div>
                <div class="col-auto ms-auto">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAllAccordions(true)">
                        <i class="bi bi-arrows-expand"></i> Alle öffnen
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAllAccordions(false)">
                        <i class="bi bi-arrows-collapse"></i> Alle schließen
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($gefaehrdungen)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-book display-4 text-muted"></i>
            <h5 class="mt-3">Keine Gefährdungen gefunden</h5>
            <p class="text-muted">
                <?php if ($search || $tagFilter || $katFilter): ?>
                Versuchen Sie andere Filterkriterien.
                <?php else: ?>
                Erstellen Sie neue Gefährdungen oder speichern Sie Gefährdungen aus Projekten.
                <?php endif; ?>
            </p>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#gefaehrdungModal">
                <i class="bi bi-plus-lg me-2"></i>Neue Gefährdung erstellen
            </button>
        </div>
    </div>
    <?php else: ?>

    <!-- Accordion mit Kategorien -->
    <div class="accordion" id="gefAccordion">
        <?php foreach ($gefNachKategorie as $katId => $katData): ?>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button <?= count($gefNachKategorie) > 1 ? 'collapsed' : '' ?> py-2" type="button"
                        data-bs-toggle="collapse" data-bs-target="#gefKat_<?= $katId ?>">
                    <strong><?= sanitize($katData['name']) ?></strong>
                    <span class="badge bg-primary ms-2"><?= count($katData['items']) ?></span>
                </button>
            </h2>
            <div id="gefKat_<?= $katId ?>" class="accordion-collapse collapse <?= count($gefNachKategorie) == 1 ? 'show' : '' ?>"
                 data-bs-parent="#gefAccordion">
                <div class="accordion-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 25%">Gefährdung</th>
                                    <th style="width: 8%">Risiko</th>
                                    <th style="width: 8%">STOP</th>
                                    <th style="width: 25%">Maßnahmen</th>
                                    <th style="width: 15%">Tags</th>
                                    <th style="width: 5%">Verw.</th>
                                    <th style="width: 5%"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($katData['items'] as $gef): ?>
                                <tr>
                                    <td>
                                        <strong><?= sanitize($gef['titel']) ?></strong>
                                        <?php if ($gef['ist_standard']): ?>
                                        <span class="badge bg-success">Standard</span>
                                        <?php endif; ?>
                                        <?php if ($gef['gefaehrdungsart_name']): ?>
                                        <br><small class="text-muted"><?= $gef['gefaehrdungsart_nummer'] ?>. <?= sanitize($gef['gefaehrdungsart_name']) ?></small>
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
                                            R=<?= $r ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $stopBadges = [];
                                        if ($gef['stop_s']) $stopBadges[] = '<span class="badge bg-danger">S</span>';
                                        if ($gef['stop_t']) $stopBadges[] = '<span class="badge bg-warning text-dark">T</span>';
                                        if ($gef['stop_o']) $stopBadges[] = '<span class="badge bg-info">O</span>';
                                        if ($gef['stop_p']) $stopBadges[] = '<span class="badge bg-success">P</span>';
                                        echo $stopBadges ? implode(' ', $stopBadges) : '<span class="text-muted">-</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($gef['typische_massnahmen']): ?>
                                        <small><?= sanitize(substr($gef['typische_massnahmen'], 0, 60)) ?>...</small>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($gefTagsMap[$gef['id']])): ?>
                                        <?php foreach ($gefTagsMap[$gef['id']] as $gt): ?>
                                        <span class="badge" style="background-color: <?= $gt['farbe'] ?>; font-size: 0.6rem;"><?= sanitize($gt['name']) ?></span>
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
                                    <td class="text-nowrap">
                                        <a href="#" class="btn btn-sm btn-link text-primary p-0 me-1"
                                           onclick="editGefaehrdung(<?= htmlspecialchars(json_encode($gef)) ?>, <?= htmlspecialchars(json_encode($gefTagsMap[$gef['id']] ?? [])) ?>)"
                                           title="Bearbeiten">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Gefährdung wirklich löschen?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $gef['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="Löschen">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal: Gefährdung - gleiches Layout wie bei Projekten -->
<style>
    #gefaehrdungModal .modal-body {
        max-height: calc(100vh - 200px);
        overflow-y: auto;
    }
</style>
<div class="modal fade" id="gefaehrdungModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" id="gefaehrdungForm">
                <input type="hidden" name="action" id="gef_action" value="create">
                <input type="hidden" name="id" id="gef_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle"><i class="bi bi-plus-circle me-2"></i>Neue Gefährdung</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <!-- Linke Spalte: Gefährdung -->
                        <div class="col-md-6">
                            <h6 class="border-bottom pb-2 mb-3"><i class="bi bi-exclamation-triangle me-2"></i>Gefährdung</h6>

                            <div class="mb-3">
                                <label class="form-label">Gefährdung (Art) *</label>
                                <select class="form-select" name="gefaehrdungsart_id" id="gef_gefaehrdungsart_id">
                                    <option value="">-- Bitte wählen --</option>
                                    <?php foreach ($gefaehrdungsarten as $ga): ?>
                                    <option value="<?= $ga['id'] ?>"><?= $ga['nummer'] ?>. <?= sanitize($ga['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Kategorie</label>
                                    <select class="form-select" name="kategorie_id" id="gef_kategorie_id" onchange="loadUnterkategorien(this.value)">
                                        <option value="">-- Keine --</option>
                                        <?php foreach ($kategorien as $kat): ?>
                                        <option value="<?= $kat['id'] ?>"><?= $kat['nummer'] ?>. <?= sanitize($kat['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Unterkategorie</label>
                                    <select class="form-select" name="unterkategorie_id" id="gef_unterkategorie_id">
                                        <option value="">-- Keine --</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Tätigkeit *</label>
                                <input type="text" class="form-control" name="titel" id="gef_titel" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Gefährdung *</label>
                                <textarea class="form-control" name="beschreibung" id="gef_beschreibung" rows="3" required></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Schadenschwere (S) *</label>
                                    <select class="form-select" name="schadenschwere" id="gef_schadenschwere" required onchange="updateRisiko()">
                                        <?php foreach ($SCHADENSCHWERE as $val => $info): ?>
                                        <option value="<?= $val ?>" <?= $val == 2 ? 'selected' : '' ?>><?= $val ?> - <?= $info['name'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Wahrscheinlichkeit (W) *</label>
                                    <select class="form-select" name="wahrscheinlichkeit" id="gef_wahrscheinlichkeit" required onchange="updateRisiko()">
                                        <?php foreach ($WAHRSCHEINLICHKEIT as $val => $info): ?>
                                        <option value="<?= $val ?>" <?= $val == 2 ? 'selected' : '' ?>><?= $val ?> - <?= $info['name'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="alert alert-secondary py-2">
                                <strong>Risikobewertung (vorher):</strong> <span id="risikoAnzeige">R = 8 (Hoch)</span>
                            </div>
                        </div>

                        <!-- Rechte Spalte: Maßnahmen -->
                        <div class="col-md-6">
                            <h6 class="border-bottom pb-2 mb-3"><i class="bi bi-shield-check me-2"></i>Maßnahmen</h6>

                            <p class="text-muted small mb-2">Aktivieren Sie ein STOP-Prinzip und beschreiben Sie die jeweilige Maßnahme:</p>

                            <!-- S - Substitution -->
                            <div class="card mb-2 stop-card" id="stop_s_card">
                                <div class="card-header py-2 d-flex align-items-center">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input stop-check" type="checkbox" name="stop_s" id="stop_s" value="1" onchange="toggleStopMassnahme('s')">
                                        <label class="form-check-label fw-bold" for="stop_s">
                                            <span class="badge bg-danger">S</span> Substitution
                                        </label>
                                    </div>
                                </div>
                                <div class="card-body py-2 stop-body" id="stop_s_body" style="display: none;">
                                    <textarea class="form-control form-control-sm" name="massnahme_s" id="gef_massnahme_s" rows="2" placeholder="Substitutions-Maßnahmen beschreiben..."></textarea>
                                </div>
                            </div>

                            <!-- T - Technisch -->
                            <div class="card mb-2 stop-card" id="stop_t_card">
                                <div class="card-header py-2 d-flex align-items-center">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input stop-check" type="checkbox" name="stop_t" id="stop_t" value="1" onchange="toggleStopMassnahme('t')">
                                        <label class="form-check-label fw-bold" for="stop_t">
                                            <span class="badge bg-warning text-dark">T</span> Technisch
                                        </label>
                                    </div>
                                </div>
                                <div class="card-body py-2 stop-body" id="stop_t_body" style="display: none;">
                                    <textarea class="form-control form-control-sm" name="massnahme_t" id="gef_massnahme_t" rows="2" placeholder="Technische Maßnahmen beschreiben..."></textarea>
                                </div>
                            </div>

                            <!-- O - Organisatorisch -->
                            <div class="card mb-2 stop-card" id="stop_o_card">
                                <div class="card-header py-2 d-flex align-items-center">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input stop-check" type="checkbox" name="stop_o" id="stop_o" value="1" onchange="toggleStopMassnahme('o')">
                                        <label class="form-check-label fw-bold" for="stop_o">
                                            <span class="badge bg-info">O</span> Organisatorisch
                                        </label>
                                    </div>
                                </div>
                                <div class="card-body py-2 stop-body" id="stop_o_body" style="display: none;">
                                    <textarea class="form-control form-control-sm" name="massnahme_o" id="gef_massnahme_o" rows="2" placeholder="Organisatorische Maßnahmen beschreiben..."></textarea>
                                </div>
                            </div>

                            <!-- P - Persönlich (PSA) -->
                            <div class="card mb-2 stop-card" id="stop_p_card">
                                <div class="card-header py-2 d-flex align-items-center">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input stop-check" type="checkbox" name="stop_p" id="stop_p" value="1" onchange="toggleStopMassnahme('p')">
                                        <label class="form-check-label fw-bold" for="stop_p">
                                            <span class="badge bg-success">P</span> Persönlich (PSA)
                                        </label>
                                    </div>
                                </div>
                                <div class="card-body py-2 stop-body" id="stop_p_body" style="display: none;">
                                    <textarea class="form-control form-control-sm" name="massnahme_p" id="gef_massnahme_p" rows="2" placeholder="Persönliche Schutzausrüstung beschreiben..."></textarea>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Verantwortlich</label>
                                <input type="text" class="form-control" name="verantwortlich" id="gef_verantwortlich">
                            </div>

                            <h6 class="mt-3">Risiko nach Maßnahmen</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">S (nachher)</label>
                                    <select class="form-select" name="schadenschwere_nachher" id="gef_schadenschwere_nachher" onchange="updateRisikoNachher()">
                                        <option value="">-</option>
                                        <?php foreach ($SCHADENSCHWERE as $val => $info): ?>
                                        <option value="<?= $val ?>"><?= $val ?> - <?= $info['name'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">W (nachher)</label>
                                    <select class="form-select" name="wahrscheinlichkeit_nachher" id="gef_wahrscheinlichkeit_nachher" onchange="updateRisikoNachher()">
                                        <option value="">-</option>
                                        <?php foreach ($WAHRSCHEINLICHKEIT as $val => $info): ?>
                                        <option value="<?= $val ?>"><?= $val ?> - <?= $info['name'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="alert alert-success py-2">
                                <strong>Risikobewertung (nachher):</strong> <span id="risikoNachherAnzeige">-</span>
                            </div>

                            <hr>
                            <h6>Bibliothek-Optionen</h6>

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
// Unterkategorien-Daten
const unterkategorien = <?= json_encode($unterkategorien) ?>;

// STOP-Maßnahmen Toggle
function toggleStopMassnahme(type) {
    const checkbox = document.getElementById('stop_' + type);
    const body = document.getElementById('stop_' + type + '_body');
    const card = document.getElementById('stop_' + type + '_card');

    if (checkbox.checked) {
        body.style.display = 'block';
        card.classList.add('border-primary');
    } else {
        body.style.display = 'none';
        card.classList.remove('border-primary');
    }
}

function loadUnterkategorien(kategorieId) {
    const select = document.getElementById('gef_unterkategorie_id');
    select.innerHTML = '<option value="">-- Keine --</option>';

    if (kategorieId) {
        const filtered = unterkategorien.filter(uk => uk.kategorie_id == kategorieId);
        filtered.forEach(uk => {
            const option = document.createElement('option');
            option.value = uk.id;
            option.textContent = uk.kat_nummer + '.' + uk.nummer + ' ' + uk.name;
            select.appendChild(option);
        });
    }
}

// Risiko berechnen
function updateRisiko() {
    const s = parseInt(document.getElementById('gef_schadenschwere').value) || 2;
    const w = parseInt(document.getElementById('gef_wahrscheinlichkeit').value) || 2;
    const r = s * s * w;
    document.getElementById('risikoAnzeige').textContent = 'R = ' + r + ' (' + getRiskLevel(r) + ')';
}

function updateRisikoNachher() {
    const s = document.getElementById('gef_schadenschwere_nachher').value;
    const w = document.getElementById('gef_wahrscheinlichkeit_nachher').value;

    if (s && w) {
        const sVal = parseInt(s);
        const wVal = parseInt(w);
        const r = sVal * sVal * wVal;
        document.getElementById('risikoNachherAnzeige').textContent = 'R = ' + r + ' (' + getRiskLevel(r) + ')';
    } else {
        document.getElementById('risikoNachherAnzeige').textContent = '-';
    }
}

function getRiskLevel(r) {
    if (r <= 2) return 'Gering';
    if (r <= 4) return 'Mittel';
    if (r <= 8) return 'Hoch';
    return 'Sehr hoch';
}

// Alle Accordions öffnen/schließen
function toggleAllAccordions(open) {
    document.querySelectorAll('#gefAccordion .accordion-collapse').forEach(el => {
        if (open) {
            el.classList.add('show');
        } else {
            el.classList.remove('show');
        }
    });
}

// Gefährdung bearbeiten
function editGefaehrdung(data, gefTags) {
    document.getElementById('gef_action').value = 'update';
    document.getElementById('gef_id').value = data.id;
    document.getElementById('modalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Gefährdung bearbeiten';

    document.getElementById('gef_gefaehrdungsart_id').value = data.gefaehrdungsart_id || '';
    document.getElementById('gef_kategorie_id').value = data.kategorie_id || '';
    loadUnterkategorien(data.kategorie_id);
    setTimeout(() => {
        document.getElementById('gef_unterkategorie_id').value = data.unterkategorie_id || '';
    }, 100);

    document.getElementById('gef_titel').value = data.titel;
    document.getElementById('gef_beschreibung').value = data.beschreibung;
    document.getElementById('gef_schadenschwere').value = data.standard_schadenschwere || 2;
    document.getElementById('gef_wahrscheinlichkeit').value = data.standard_wahrscheinlichkeit || 2;

    document.getElementById('stop_s').checked = data.stop_s == 1;
    document.getElementById('stop_t').checked = data.stop_t == 1;
    document.getElementById('stop_o').checked = data.stop_o == 1;
    document.getElementById('stop_p').checked = data.stop_p == 1;

    // STOP Maßnahmen-Textfelder
    document.getElementById('gef_massnahme_s').value = data.massnahme_s || '';
    document.getElementById('gef_massnahme_t').value = data.massnahme_t || '';
    document.getElementById('gef_massnahme_o').value = data.massnahme_o || '';
    document.getElementById('gef_massnahme_p').value = data.massnahme_p || '';

    // Textfelder anzeigen/ausblenden
    toggleStopMassnahme('s');
    toggleStopMassnahme('t');
    toggleStopMassnahme('o');
    toggleStopMassnahme('p');
    document.getElementById('gef_verantwortlich').value = data.verantwortlich || '';
    document.getElementById('gef_schadenschwere_nachher').value = data.schadenschwere_nachher || '';
    document.getElementById('gef_wahrscheinlichkeit_nachher').value = data.wahrscheinlichkeit_nachher || '';
    document.getElementById('gef_ist_standard').checked = data.ist_standard == 1;

    // Tags setzen
    document.querySelectorAll('.tag-check').forEach(cb => cb.checked = false);
    if (gefTags && gefTags.length) {
        gefTags.forEach(tag => {
            const cb = document.getElementById('tag_' + tag.id);
            if (cb) cb.checked = true;
        });
    }

    updateRisiko();
    updateRisikoNachher();
    new bootstrap.Modal(document.getElementById('gefaehrdungModal')).show();
}

// Modal zurücksetzen
document.getElementById('gefaehrdungModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('gef_action').value = 'create';
    document.getElementById('gef_id').value = '';
    document.getElementById('modalTitle').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Neue Gefährdung';
    document.querySelectorAll('.tag-check').forEach(cb => cb.checked = false);
    this.querySelector('form').reset();
    updateRisiko();
    document.getElementById('risikoNachherAnzeige').textContent = '-';

    // STOP-Felder zurücksetzen
    ['s', 't', 'o', 'p'].forEach(type => {
        document.getElementById('stop_' + type + '_body').style.display = 'none';
        document.getElementById('stop_' + type + '_card').classList.remove('border-primary');
    });
});

// Initial
updateRisiko();
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
