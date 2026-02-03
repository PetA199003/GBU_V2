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
                    'gefaehrdungsart_id' => $bibGef['gefaehrdungsart_id'] ?? null,
                    'kategorie_id' => $bibGef['kategorie_id'] ?? null,
                    'unterkategorie_id' => $bibGef['unterkategorie_id'] ?? null,
                    'titel' => $bibGef['titel'],
                    'beschreibung' => $bibGef['beschreibung'],
                    'schadenschwere' => $bibGef['standard_schadenschwere'] ?? 2,
                    'wahrscheinlichkeit' => $bibGef['standard_wahrscheinlichkeit'] ?? 2,
                    'stop_s' => $bibGef['stop_s'] ?? 0,
                    'stop_t' => $bibGef['stop_t'] ?? 0,
                    'stop_o' => $bibGef['stop_o'] ?? 0,
                    'stop_p' => $bibGef['stop_p'] ?? 0,
                    'massnahmen' => $bibGef['typische_massnahmen'],
                    'erstellt_von' => $userId
                ]);
                setFlashMessage('success', 'Gefährdung wurde hinzugefügt.');
            }
            break;

        case 'add_new':
        case 'update':
            $data = [
                'projekt_id' => $projektId,
                'gefaehrdungsart_id' => $_POST['gefaehrdungsart_id'] ?: null,
                'kategorie_id' => $_POST['kategorie_id'] ?: null,
                'unterkategorie_id' => $_POST['unterkategorie_id'] ?: null,
                'titel' => $_POST['titel'],
                'beschreibung' => $_POST['beschreibung'],
                'schadenschwere' => $_POST['schadenschwere'],
                'wahrscheinlichkeit' => $_POST['wahrscheinlichkeit'],
                'stop_s' => isset($_POST['stop_s']) ? 1 : 0,
                'stop_t' => isset($_POST['stop_t']) ? 1 : 0,
                'stop_o' => isset($_POST['stop_o']) ? 1 : 0,
                'stop_p' => isset($_POST['stop_p']) ? 1 : 0,
                'massnahmen' => $_POST['massnahmen'] ?: null,
                'gegenmassnahmen' => $_POST['gegenmassnahmen'] ?: null,
                'verantwortlich' => $_POST['verantwortlich'] ?: null,
                'schadenschwere_nach' => $_POST['schadenschwere_nach'] ?: null,
                'wahrscheinlichkeit_nach' => $_POST['wahrscheinlichkeit_nach'] ?: null
            ];

            if ($action === 'add_new') {
                $data['erstellt_von'] = $userId;
                $newId = $db->insert('projekt_gefaehrdungen', $data);

                // In Bibliothek speichern?
                if (!empty($_POST['save_to_library'])) {
                    $libData = [
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

                    $db->update('projekt_gefaehrdungen', ['gefaehrdung_bibliothek_id' => $libId], 'id = :id', ['id' => $newId]);
                    setFlashMessage('success', 'Gefährdung wurde erstellt und zur Bibliothek hinzugefügt.');
                } else {
                    setFlashMessage('success', 'Gefährdung wurde erstellt.');
                }
            } else {
                // Update
                $gefId = $_POST['gefaehrdung_id'];
                unset($data['projekt_id']);
                $db->update('projekt_gefaehrdungen', $data, 'id = :id AND projekt_id = :pid', ['id' => $gefId, 'pid' => $projektId]);
                setFlashMessage('success', 'Gefährdung wurde aktualisiert.');
            }
            break;

        case 'delete':
            $gefId = $_POST['gefaehrdung_id'];
            $db->delete('projekt_gefaehrdungen', 'id = ? AND projekt_id = ?', [$gefId, $projektId]);
            setFlashMessage('success', 'Gefährdung wurde entfernt.');
            break;

        case 'add_kategorie':
            // Neue Kategorie hinzufügen
            $maxNummer = $db->fetchOne("SELECT MAX(nummer) as max FROM arbeits_kategorien WHERE projekt_id = ? OR ist_global = 1", [$projektId]);
            $neueNummer = ($maxNummer['max'] ?? 0) + 1;

            $db->insert('arbeits_kategorien', [
                'projekt_id' => $projektId,
                'nummer' => $neueNummer,
                'name' => $_POST['kategorie_name'],
                'ist_global' => 0,
                'erstellt_von' => $userId
            ]);
            setFlashMessage('success', 'Kategorie wurde hinzugefügt.');
            break;

        case 'add_unterkategorie':
            // Neue Unterkategorie hinzufügen
            $katId = $_POST['parent_kategorie_id'];
            $maxNummer = $db->fetchOne("SELECT MAX(nummer) as max FROM arbeits_unterkategorien WHERE kategorie_id = ?", [$katId]);
            $neueNummer = ($maxNummer['max'] ?? 0) + 1;

            $db->insert('arbeits_unterkategorien', [
                'kategorie_id' => $katId,
                'nummer' => $neueNummer,
                'name' => $_POST['unterkategorie_name'],
                'erstellt_von' => $userId
            ]);
            setFlashMessage('success', 'Unterkategorie wurde hinzugefügt.');
            break;
    }

    redirect('projekt.php?id=' . $projektId);
}

// Gefährdungen des Projekts laden
$gefaehrdungen = $db->fetchAll("
    SELECT pg.*,
           ga.name as gefaehrdungsart_name, ga.nummer as gefaehrdungsart_nummer,
           ak.name as kategorie_name, ak.nummer as kategorie_nummer,
           auk.name as unterkategorie_name, auk.nummer as unterkategorie_nummer
    FROM projekt_gefaehrdungen pg
    LEFT JOIN gefaehrdungsarten ga ON pg.gefaehrdungsart_id = ga.id
    LEFT JOIN arbeits_kategorien ak ON pg.kategorie_id = ak.id
    LEFT JOIN arbeits_unterkategorien auk ON pg.unterkategorie_id = auk.id
    WHERE pg.projekt_id = ?
    ORDER BY ak.nummer, auk.nummer, pg.titel
", [$projektId]);

// Gefährdungsarten laden (die 13 festen)
$gefaehrdungsarten = $db->fetchAll("SELECT * FROM gefaehrdungsarten ORDER BY nummer");

// Kategorien laden (global + projektspezifisch)
$kategorien = $db->fetchAll("
    SELECT * FROM arbeits_kategorien
    WHERE ist_global = 1 OR projekt_id = ?
    ORDER BY nummer
", [$projektId]);

// Unterkategorien laden
$unterkategorien = $db->fetchAll("
    SELECT auk.*, ak.nummer as kat_nummer
    FROM arbeits_unterkategorien auk
    JOIN arbeits_kategorien ak ON auk.kategorie_id = ak.id
    WHERE ak.ist_global = 1 OR ak.projekt_id = ?
    ORDER BY ak.nummer, auk.nummer
", [$projektId]);

// Bibliothek laden (für Modal) - mit Kategorien
$bibliothek = $db->fetchAll("
    SELECT gb.*,
           ga.name as gefaehrdungsart_name, ga.nummer as gefaehrdungsart_nummer,
           ak.name as kategorie_name, ak.nummer as kategorie_nummer
    FROM gefaehrdung_bibliothek gb
    LEFT JOIN gefaehrdungsarten ga ON gb.gefaehrdungsart_id = ga.id
    LEFT JOIN arbeits_kategorien ak ON gb.kategorie_id = ak.id
    ORDER BY ak.nummer, gb.titel
");

// Bereits hinzugefügte Bibliotheks-IDs ermitteln
$bereitsHinzugefuegt = $db->fetchAll(
    "SELECT gefaehrdung_bibliothek_id FROM projekt_gefaehrdungen WHERE projekt_id = ? AND gefaehrdung_bibliothek_id IS NOT NULL",
    [$projektId]
);
$bereitsHinzugefuegtIds = array_column($bereitsHinzugefuegt, 'gefaehrdung_bibliothek_id');

// Bibliothek nach Kategorien gruppieren
$bibliothekNachKategorie = [];
foreach ($bibliothek as $bib) {
    $katKey = $bib['kategorie_id'] ? $bib['kategorie_id'] : 0;
    $katName = $bib['kategorie_name'] ? $bib['kategorie_nummer'] . '. ' . $bib['kategorie_name'] : 'Ohne Kategorie';
    if (!isset($bibliothekNachKategorie[$katKey])) {
        $bibliothekNachKategorie[$katKey] = [
            'name' => $katName,
            'nummer' => $bib['kategorie_nummer'] ?? 999,
            'items' => []
        ];
    }
    $bibliothekNachKategorie[$katKey]['items'][] = $bib;
}
// Nach Kategorienummer sortieren
uasort($bibliothekNachKategorie, fn($a, $b) => ($a['nummer'] ?? 999) <=> ($b['nummer'] ?? 999));

// Tags laden
$tags = $db->fetchAll("SELECT * FROM gefaehrdung_tags ORDER BY sortierung");

$pageTitle = $projekt['name'] . ' - Gefährdungen';
require_once __DIR__ . '/templates/header.php';

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
                <i class="bi bi-book me-2"></i>Aus Bibliothek
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
        $hoheRisiken = count(array_filter($gefaehrdungen, fn($g) => ($g['risikobewertung'] ?? 0) >= 9));
        $mitMassnahmen = count(array_filter($gefaehrdungen, fn($g) => !empty($g['massnahmen'])));
        ?>
        <div class="col-md-4 col-6 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body py-3">
                    <h3 class="mb-0"><?= $totalGef ?></h3>
                    <small>Gefährdungen gesamt</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-6 mb-3">
            <div class="card bg-danger text-white">
                <div class="card-body py-3">
                    <h3 class="mb-0"><?= $hoheRisiken ?></h3>
                    <small>Hohe Risiken (R≥9)</small>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-6 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body py-3">
                    <h3 class="mb-0"><?= $mitMassnahmen ?></h3>
                    <small>Mit Maßnahmen</small>
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
                    <i class="bi bi-book me-2"></i>Aus Bibliothek hinzufügen
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
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Erfasste Gefährdungen</h5>
            <?php if ($canEdit): ?>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#kategorieModal">
                <i class="bi bi-plus me-1"></i>Kategorie hinzufügen
            </button>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 5%">Nr.</th>
                        <th style="width: 20%">Kategorie / Tätigkeit</th>
                        <th style="width: 15%">Gefährdung</th>
                        <th style="width: 10%">Risiko</th>
                        <th style="width: 8%">STOP</th>
                        <th style="width: 20%">Maßnahmen</th>
                        <th style="width: 10%">R (nach)</th>
                        <th style="width: 5%"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $currentKat = null;
                    $lfdNr = 0;
                    foreach ($gefaehrdungen as $gef):
                        $lfdNr++;
                        $katDisplay = '';
                        if ($gef['kategorie_name']) {
                            $katDisplay = $gef['kategorie_nummer'] . '. ' . $gef['kategorie_name'];
                            if ($gef['unterkategorie_name']) {
                                $katDisplay .= '<br><small class="text-muted">' . $gef['kategorie_nummer'] . '.' . $gef['unterkategorie_nummer'] . ' ' . $gef['unterkategorie_name'] . '</small>';
                            }
                        }
                    ?>
                    <tr>
                        <td><?= $lfdNr ?></td>
                        <td><?= $katDisplay ?: '<span class="text-muted">-</span>' ?></td>
                        <td>
                            <strong><?= sanitize($gef['titel']) ?></strong>
                            <?php if ($gef['gefaehrdungsart_name']): ?>
                            <br><span class="badge bg-secondary"><?= $gef['gefaehrdungsart_nummer'] ?>. <?= sanitize($gef['gefaehrdungsart_name']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $rScore = $gef['risikobewertung'] ?? 0;
                            $rColor = getRiskColor($rScore);
                            ?>
                            <span class="badge" style="background-color: <?= $rColor ?>; color: <?= $rScore >= 9 ? '#fff' : '#000' ?>">
                                R = <?= $rScore ?>
                            </span>
                            <br><small class="text-muted">S=<?= $gef['schadenschwere'] ?> W=<?= $gef['wahrscheinlichkeit'] ?></small>
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
                            <?php if ($gef['massnahmen']): ?>
                            <small><?= nl2br(sanitize(substr($gef['massnahmen'], 0, 80))) ?><?= strlen($gef['massnahmen']) > 80 ? '...' : '' ?></small>
                            <?php endif; ?>
                            <?php if ($gef['gegenmassnahmen']): ?>
                            <br><small class="text-success"><i class="bi bi-shield-check"></i> <?= sanitize(substr($gef['gegenmassnahmen'], 0, 50)) ?>...</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($gef['risikobewertung_nach']): ?>
                            <?php $rColorNach = getRiskColor($gef['risikobewertung_nach']); ?>
                            <span class="badge" style="background-color: <?= $rColorNach ?>; color: <?= $gef['risikobewertung_nach'] >= 9 ? '#fff' : '#000' ?>">
                                R = <?= $gef['risikobewertung_nach'] ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title"><i class="bi bi-book me-2"></i>Aus Bibliothek hinzufügen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-2">
                <!-- Suche -->
                <div class="mb-2">
                    <input type="text" class="form-control form-control-sm" id="bibliothekSuche" placeholder="Suchen...">
                </div>

                <!-- Accordion mit Kategorien -->
                <div class="accordion" id="bibAccordion" style="max-height: 450px; overflow-y: auto;">
                    <?php foreach ($bibliothekNachKategorie as $katId => $katData): ?>
                    <?php
                    $availableCount = count(array_filter($katData['items'], fn($b) => !in_array($b['id'], $bereitsHinzugefuegtIds)));
                    $totalCount = count($katData['items']);
                    ?>
                    <div class="accordion-item bib-kategorie" data-kategorie="<?= $katId ?>">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#bibKat_<?= $katId ?>">
                                <strong><?= sanitize($katData['name']) ?></strong>
                                <span class="badge bg-<?= $availableCount > 0 ? 'primary' : 'secondary' ?> ms-2"><?= $availableCount ?>/<?= $totalCount ?></span>
                            </button>
                        </h2>
                        <div id="bibKat_<?= $katId ?>" class="accordion-collapse collapse" data-bs-parent="#bibAccordion">
                            <div class="accordion-body p-0">
                                <table class="table table-sm table-hover mb-0">
                                    <tbody>
                                        <?php foreach ($katData['items'] as $bib):
                                        $istHinzugefuegt = in_array($bib['id'], $bereitsHinzugefuegtIds);
                                        ?>
                                        <tr class="bib-item <?= $istHinzugefuegt ? 'table-secondary' : '' ?>"
                                            data-titel="<?= strtolower($bib['titel']) ?>"
                                            data-beschreibung="<?= strtolower($bib['beschreibung']) ?>">
                                            <td style="width: 40px" class="text-center">
                                                <?php if ($istHinzugefuegt): ?>
                                                <i class="bi bi-check-circle-fill text-success" title="Bereits hinzugefügt"></i>
                                                <?php else: ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="add_from_library">
                                                    <input type="hidden" name="bibliothek_id" value="<?= $bib['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-success py-0 px-1" title="Hinzufügen">
                                                        <i class="bi bi-plus"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong class="<?= $istHinzugefuegt ? 'text-muted' : '' ?>"><?= sanitize($bib['titel']) ?></strong>
                                                <?php if ($bib['gefaehrdungsart_name']): ?>
                                                <br><small class="text-muted"><?= $bib['gefaehrdungsart_nummer'] ?>. <?= sanitize($bib['gefaehrdungsart_name']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end" style="width: 60px">
                                                <?php
                                                $s = $bib['standard_schadenschwere'] ?? 2;
                                                $w = $bib['standard_wahrscheinlichkeit'] ?? 2;
                                                $r = $s * $s * $w;
                                                $rColor = getRiskColor($r);
                                                ?>
                                                <span class="badge" style="background-color: <?= $rColor ?>; color: <?= $r >= 9 ? '#fff' : '#000' ?>; font-size: 0.7rem;">
                                                    R=<?= $r ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($bibliothek)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-book display-6"></i>
                    <p class="mt-2">Keine Gefährdungen in der Bibliothek vorhanden.</p>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer py-2">
                <small class="text-muted me-auto">
                    <i class="bi bi-check-circle-fill text-success"></i> = Bereits im Projekt
                </small>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Neue Gefährdung / Bearbeiten -->
<div class="modal fade" id="neueGefaehrdungModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
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
                                <label class="form-label">Titel *</label>
                                <input type="text" class="form-control" name="titel" id="gef_titel" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Beschreibung *</label>
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
                                <strong>Risikobewertung (vorher):</strong> <span id="risikoVorher">R = 8</span>
                            </div>
                        </div>

                        <!-- Rechte Spalte: Maßnahmen -->
                        <div class="col-md-6">
                            <h6 class="border-bottom pb-2 mb-3"><i class="bi bi-shield-check me-2"></i>Maßnahmen</h6>

                            <div class="mb-3">
                                <label class="form-label">STOP-Prinzip (Mehrfachauswahl)</label>
                                <div class="d-flex flex-wrap gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="stop_s" id="stop_s" value="1">
                                        <label class="form-check-label" for="stop_s">
                                            <span class="badge bg-danger">S</span> Substitution
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="stop_t" id="stop_t" value="1">
                                        <label class="form-check-label" for="stop_t">
                                            <span class="badge bg-warning text-dark">T</span> Technisch
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="stop_o" id="stop_o" value="1">
                                        <label class="form-check-label" for="stop_o">
                                            <span class="badge bg-info">O</span> Organisatorisch
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="stop_p" id="stop_p" value="1">
                                        <label class="form-check-label" for="stop_p">
                                            <span class="badge bg-success">P</span> Persönlich (PSA)
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Maßnahmen</label>
                                <textarea class="form-control" name="massnahmen" id="gef_massnahmen" rows="4" placeholder="Beschreiben Sie die Schutzmaßnahmen..."></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Verantwortlich</label>
                                <input type="text" class="form-control" name="verantwortlich" id="gef_verantwortlich">
                            </div>

                            <hr>
                            <h6>Risiko nach Maßnahmen</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">S (nachher)</label>
                                    <select class="form-select" name="schadenschwere_nach" id="gef_schadenschwere_nach" onchange="updateRisikoNach()">
                                        <option value="">-</option>
                                        <?php foreach ($SCHADENSCHWERE as $val => $info): ?>
                                        <option value="<?= $val ?>"><?= $val ?> - <?= $info['name'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">W (nachher)</label>
                                    <select class="form-select" name="wahrscheinlichkeit_nach" id="gef_wahrscheinlichkeit_nach" onchange="updateRisikoNach()">
                                        <option value="">-</option>
                                        <?php foreach ($WAHRSCHEINLICHKEIT as $val => $info): ?>
                                        <option value="<?= $val ?>"><?= $val ?> - <?= $info['name'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="alert alert-success py-2">
                                <strong>Risikobewertung (nachher):</strong> <span id="risikoNachher">-</span>
                            </div>
                        </div>
                    </div>

                    <!-- Bibliothek-Optionen (nur bei Neu) -->
                    <div id="newOnlyFields" class="border-top pt-3 mt-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="save_to_library" id="save_to_library" value="1">
                            <label class="form-check-label" for="save_to_library">
                                <strong>In Bibliothek speichern</strong> (zur Wiederverwendung)
                            </label>
                        </div>
                        <div id="libraryOptions" style="display: none;" class="ms-4">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="ist_standard" id="ist_standard" value="1">
                                <label class="form-check-label" for="ist_standard">Als Standard-Gefährdung markieren</label>
                            </div>
                            <label class="form-label">Tags</label>
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Kategorie hinzufügen -->
<div class="modal fade" id="kategorieModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_kategorie">
                <div class="modal-header">
                    <h5 class="modal-title">Neue Kategorie hinzufügen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name der Kategorie *</label>
                        <input type="text" class="form-control" name="kategorie_name" required placeholder="z.B. Pyrotechnik">
                    </div>
                    <hr>
                    <h6>Oder Unterkategorie zu bestehender Kategorie:</h6>
                    <div class="mb-3">
                        <label class="form-label">Übergeordnete Kategorie</label>
                        <select class="form-select" name="parent_kategorie_id" id="parent_kategorie_select">
                            <option value="">-- Neue Hauptkategorie --</option>
                            <?php foreach ($kategorien as $kat): ?>
                            <option value="<?= $kat['id'] ?>"><?= $kat['nummer'] ?>. <?= sanitize($kat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3" id="unterkategorie_name_field" style="display: none;">
                        <label class="form-label">Name der Unterkategorie</label>
                        <input type="text" class="form-control" name="unterkategorie_name" placeholder="z.B. Indoor-Feuerwerk">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Hinzufügen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Unterkategorien-Daten
const unterkategorien = <?= json_encode($unterkategorien) ?>;

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
    document.getElementById('risikoVorher').textContent = 'R = ' + r + ' (' + getRiskLevel(r) + ')';
}

function updateRisikoNach() {
    const s = document.getElementById('gef_schadenschwere_nach').value;
    const w = document.getElementById('gef_wahrscheinlichkeit_nach').value;
    if (s && w) {
        const r = parseInt(s) * parseInt(s) * parseInt(w);
        document.getElementById('risikoNachher').textContent = 'R = ' + r + ' (' + getRiskLevel(r) + ')';
    } else {
        document.getElementById('risikoNachher').textContent = '-';
    }
}

function getRiskLevel(r) {
    if (r <= 2) return 'Gering';
    if (r <= 4) return 'Mittel';
    if (r <= 8) return 'Hoch';
    return 'Sehr hoch';
}

// Bibliothek-Suche mit Accordion
document.getElementById('bibliothekSuche').addEventListener('input', function() {
    const search = this.value.toLowerCase();

    // Alle Kategorien durchgehen
    document.querySelectorAll('.bib-kategorie').forEach(kategorie => {
        let visibleItems = 0;
        const items = kategorie.querySelectorAll('.bib-item');

        items.forEach(item => {
            const titel = item.dataset.titel || '';
            const beschreibung = item.dataset.beschreibung || '';
            const matches = titel.includes(search) || beschreibung.includes(search);
            item.style.display = matches ? '' : 'none';
            if (matches) visibleItems++;
        });

        // Kategorie ausblenden wenn keine Treffer
        kategorie.style.display = visibleItems > 0 ? '' : 'none';

        // Bei Suche alle Kategorien mit Treffern öffnen
        if (search && visibleItems > 0) {
            const collapse = kategorie.querySelector('.accordion-collapse');
            if (collapse && !collapse.classList.contains('show')) {
                collapse.classList.add('show');
            }
        }
    });

    // Bei leerer Suche alle schließen
    if (!search) {
        document.querySelectorAll('.bib-kategorie .accordion-collapse').forEach(c => {
            c.classList.remove('show');
        });
    }
});

// Bibliothek-Checkbox
document.getElementById('save_to_library').addEventListener('change', function() {
    document.getElementById('libraryOptions').style.display = this.checked ? 'block' : 'none';
});

// Kategorie Modal: Unterkategorie Feld anzeigen
document.getElementById('parent_kategorie_select').addEventListener('change', function() {
    const field = document.getElementById('unterkategorie_name_field');
    const nameField = document.querySelector('input[name="kategorie_name"]');
    if (this.value) {
        field.style.display = 'block';
        nameField.removeAttribute('required');
        // Action ändern
        this.form.querySelector('input[name="action"]').value = 'add_unterkategorie';
    } else {
        field.style.display = 'none';
        nameField.setAttribute('required', 'required');
        this.form.querySelector('input[name="action"]').value = 'add_kategorie';
    }
});

// Gefährdung bearbeiten
function editGefaehrdung(data) {
    document.getElementById('form_action').value = 'update';
    document.getElementById('form_gefaehrdung_id').value = data.id;
    document.getElementById('gefaehrdungModalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Gefährdung bearbeiten';

    document.getElementById('gef_gefaehrdungsart_id').value = data.gefaehrdungsart_id || '';
    document.getElementById('gef_kategorie_id').value = data.kategorie_id || '';
    loadUnterkategorien(data.kategorie_id);
    setTimeout(() => {
        document.getElementById('gef_unterkategorie_id').value = data.unterkategorie_id || '';
    }, 100);

    document.getElementById('gef_titel').value = data.titel;
    document.getElementById('gef_beschreibung').value = data.beschreibung;
    document.getElementById('gef_schadenschwere').value = data.schadenschwere;
    document.getElementById('gef_wahrscheinlichkeit').value = data.wahrscheinlichkeit;

    document.getElementById('stop_s').checked = data.stop_s == 1;
    document.getElementById('stop_t').checked = data.stop_t == 1;
    document.getElementById('stop_o').checked = data.stop_o == 1;
    document.getElementById('stop_p').checked = data.stop_p == 1;

    document.getElementById('gef_massnahmen').value = data.massnahmen || '';
    document.getElementById('gef_verantwortlich').value = data.verantwortlich || '';

    document.getElementById('gef_schadenschwere_nach').value = data.schadenschwere_nach || '';
    document.getElementById('gef_wahrscheinlichkeit_nach').value = data.wahrscheinlichkeit_nach || '';

    document.getElementById('newOnlyFields').style.display = 'none';

    updateRisiko();
    updateRisikoNach();

    new bootstrap.Modal(document.getElementById('neueGefaehrdungModal')).show();
}

// Modal zurücksetzen
document.getElementById('neueGefaehrdungModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('form_action').value = 'add_new';
    document.getElementById('form_gefaehrdung_id').value = '';
    document.getElementById('gefaehrdungModalTitle').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Neue Gefährdung';
    document.getElementById('newOnlyFields').style.display = 'block';
    document.getElementById('libraryOptions').style.display = 'none';
    this.querySelector('form').reset();
    updateRisiko();
    updateRisikoNach();
});

// Initial
updateRisiko();
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
