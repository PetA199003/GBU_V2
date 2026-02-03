<?php
/**
 * Gefährdungsbeurteilung bearbeiten - Haupteditor
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Gefaehrdungsbeurteilung.php';

requireRole(ROLE_EDITOR);

$gbClass = new Gefaehrdungsbeurteilung();
$db = Database::getInstance();

$id = $_GET['id'] ?? null;
if (!$id) {
    redirect('beurteilungen.php');
}

$beurteilung = $gbClass->getById($id);
if (!$beurteilung) {
    setFlashMessage('error', 'Gefährdungsbeurteilung nicht gefunden.');
    redirect('beurteilungen.php');
}

// Kategorien und Faktoren laden
$kategorien = $gbClass->getKategorien();
$faktoren = $gbClass->getFaktoren();

// Gefährdungsbibliothek laden
$gefaehrdungBibliothek = $db->fetchAll("
    SELECT gb.*, gk.name as kategorie_name, gf.name as faktor_name
    FROM gefaehrdung_bibliothek gb
    LEFT JOIN gefaehrdung_kategorien gk ON gb.kategorie_id = gk.id
    LEFT JOIN gefaehrdung_faktoren gf ON gb.faktor_id = gf.id
    ORDER BY gk.sortierung, gb.titel
");

// Maßnahmenbibliothek laden
$massnahmenBibliothek = $db->fetchAll("
    SELECT mb.*, gk.name as kategorie_name
    FROM massnahmen_bibliothek mb
    LEFT JOIN gefaehrdung_kategorien gk ON mb.kategorie_id = gk.id
    ORDER BY gk.sortierung, mb.titel
");

$pageTitle = 'Bearbeiten: ' . $beurteilung['titel'];
require_once __DIR__ . '/templates/header.php';
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="h3 mb-1">
                <i class="bi bi-pencil-square me-2"></i><?= sanitize($beurteilung['titel']) ?>
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/beurteilungen.php">Beurteilungen</a></li>
                    <li class="breadcrumb-item active">Bearbeiten</li>
                </ol>
            </nav>
        </div>
        <div class="btn-group">
            <a href="<?= BASE_URL ?>/beurteilung.php?id=<?= $id ?>" class="btn btn-outline-primary">
                <i class="bi bi-eye me-1"></i>Ansehen
            </a>
            <a href="<?= BASE_URL ?>/api/export.php?type=pdf&id=<?= $id ?>" class="btn btn-outline-danger" target="_blank">
                <i class="bi bi-file-pdf me-1"></i>PDF
            </a>
        </div>
    </div>

    <!-- Info-Leiste -->
    <div class="card mb-4">
        <div class="card-body py-2">
            <div class="row align-items-center">
                <div class="col-auto">
                    <span class="text-muted">Unternehmen:</span>
                    <strong><?= sanitize($beurteilung['unternehmen_name']) ?></strong>
                </div>
                <div class="col-auto">
                    <span class="text-muted">Ersteller:</span>
                    <strong><?= sanitize($beurteilung['ersteller_name']) ?></strong>
                </div>
                <div class="col-auto">
                    <span class="text-muted">Datum:</span>
                    <strong><?= date('d.m.Y', strtotime($beurteilung['erstelldatum'])) ?></strong>
                </div>
                <div class="col-auto">
                    <span class="status-badge status-<?= $beurteilung['status'] ?>">
                        <?= ucfirst($beurteilung['status']) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- STOP-Prinzip Legende -->
    <div class="stop-legend mb-4">
        <strong class="me-3">STOP-Prinzip:</strong>
        <div class="stop-legend-item">
            <div class="stop-legend-color" style="background-color: var(--stop-s);"></div>
            <span><strong>S</strong> - Substitution</span>
        </div>
        <div class="stop-legend-item">
            <div class="stop-legend-color" style="background-color: var(--stop-t);"></div>
            <span><strong>T</strong> - Technisch</span>
        </div>
        <div class="stop-legend-item">
            <div class="stop-legend-color" style="background-color: var(--stop-o);"></div>
            <span><strong>O</strong> - Organisatorisch</span>
        </div>
        <div class="stop-legend-item">
            <div class="stop-legend-color" style="background-color: var(--stop-p);"></div>
            <span><strong>P</strong> - Persönlich</span>
        </div>
    </div>

    <div class="row">
        <!-- Hauptbereich: Tätigkeiten und Vorgänge -->
        <div class="col-lg-9">
            <!-- Tätigkeiten -->
            <div id="taetigkeiten-container">
                <?php if (empty($beurteilung['taetigkeiten'])): ?>
                <div class="card mb-3" id="empty-state">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-clipboard-plus display-4 text-muted"></i>
                        <h5 class="mt-3">Noch keine Tätigkeiten vorhanden</h5>
                        <p class="text-muted">Fügen Sie eine erste Tätigkeit hinzu, um Gefährdungen zu erfassen.</p>
                        <button type="button" class="btn btn-primary" onclick="showTaetigkeitModal()">
                            <i class="bi bi-plus-lg me-2"></i>Erste Tätigkeit hinzufügen
                        </button>
                    </div>
                </div>
                <?php else: ?>
                <?php foreach ($beurteilung['taetigkeiten'] as $tIdx => $taetigkeit): ?>
                <div class="card mb-3 taetigkeit-card" data-id="<?= $taetigkeit['id'] ?>">
                    <div class="card-header d-flex justify-content-between align-items-center bg-primary text-white">
                        <h5 class="mb-0">
                            <span class="badge bg-light text-primary me-2"><?= sanitize($taetigkeit['position']) ?></span>
                            <?= sanitize($taetigkeit['name']) ?>
                        </h5>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-light btn-sm"
                                    onclick="showVorgangModal(<?= $taetigkeit['id'] ?>)">
                                <i class="bi bi-plus-lg me-1"></i>Gefährdung
                            </button>
                            <button type="button" class="btn btn-outline-light btn-sm"
                                    onclick="editTaetigkeit(<?= $taetigkeit['id'] ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-outline-light btn-sm"
                                    onclick="deleteTaetigkeit(<?= $taetigkeit['id'] ?>)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>

                    <?php if (!empty($taetigkeit['vorgaenge'])): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered gb-table mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">Pos.</th>
                                    <th style="width: 15%;">Vorgang</th>
                                    <th style="width: 20%;">Gefährdung</th>
                                    <th style="width: 12%;">Gef.-Faktor</th>
                                    <th class="risk-header" style="width: 40px;">S</th>
                                    <th class="risk-header" style="width: 40px;">W</th>
                                    <th class="risk-header" style="width: 40px;">R</th>
                                    <th style="width: 100px;">STOP</th>
                                    <th style="width: 20%;">Maßnahmen</th>
                                    <th class="measure-header" style="width: 40px;">S</th>
                                    <th class="measure-header" style="width: 40px;">W</th>
                                    <th class="measure-header" style="width: 40px;">R</th>
                                    <th style="width: 60px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($taetigkeit['vorgaenge'] as $vorgang): ?>
                                <?php
                                    $riskScore = $vorgang['risikobewertung'];
                                    $newRiskScore = $vorgang['massnahme_risikobewertung'];
                                ?>
                                <tr data-vorgang-id="<?= $vorgang['id'] ?>">
                                    <td class="position-cell"><?= sanitize($vorgang['position']) ?></td>
                                    <td><?= nl2br(sanitize($vorgang['vorgang_beschreibung'])) ?></td>
                                    <td><?= nl2br(sanitize($vorgang['gefaehrdung'])) ?></td>
                                    <td>
                                        <?php if ($vorgang['faktor_nummer']): ?>
                                        <small class="text-muted"><?= sanitize($vorgang['faktor_nummer']) ?></small><br>
                                        <?= sanitize($vorgang['faktor_name']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="risk-cell risk-<?= $vorgang['schadenschwere'] ?>"><?= $vorgang['schadenschwere'] ?></td>
                                    <td class="risk-cell risk-<?= $vorgang['wahrscheinlichkeit'] ?>"><?= $vorgang['wahrscheinlichkeit'] ?></td>
                                    <td class="risk-cell risk-<?= $riskScore ?>"><?= $riskScore ?></td>
                                    <td class="text-center">
                                        <span class="stop-badge stop-s <?= !$vorgang['stop_s'] ? 'inactive' : '' ?>">S</span>
                                        <span class="stop-badge stop-t <?= !$vorgang['stop_t'] ? 'inactive' : '' ?>">T</span>
                                        <span class="stop-badge stop-o <?= !$vorgang['stop_o'] ? 'inactive' : '' ?>">O</span>
                                        <span class="stop-badge stop-p <?= !$vorgang['stop_p'] ? 'inactive' : '' ?>">P</span>
                                    </td>
                                    <td><?= nl2br(sanitize($vorgang['massnahmen'])) ?></td>
                                    <td class="risk-cell <?= $vorgang['massnahme_schadenschwere'] ? 'risk-' . $vorgang['massnahme_schadenschwere'] : '' ?>">
                                        <?= $vorgang['massnahme_schadenschwere'] ?: '-' ?>
                                    </td>
                                    <td class="risk-cell <?= $vorgang['massnahme_wahrscheinlichkeit'] ? 'risk-' . $vorgang['massnahme_wahrscheinlichkeit'] : '' ?>">
                                        <?= $vorgang['massnahme_wahrscheinlichkeit'] ?: '-' ?>
                                    </td>
                                    <td class="risk-cell <?= $newRiskScore ? 'risk-' . $newRiskScore : '' ?>">
                                        <?= $newRiskScore ?: '-' ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary btn-sm"
                                                    onclick="editVorgang(<?= $vorgang['id'] ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger btn-sm"
                                                    onclick="deleteVorgang(<?= $vorgang['id'] ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="card-body text-center text-muted">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Keine Gefährdungen erfasst.
                        <button type="button" class="btn btn-sm btn-outline-primary ms-2"
                                onclick="showVorgangModal(<?= $taetigkeit['id'] ?>)">
                            <i class="bi bi-plus-lg"></i> Hinzufügen
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Neue Tätigkeit hinzufügen Button -->
            <div class="text-center mb-4">
                <button type="button" class="btn btn-outline-primary btn-lg" onclick="showTaetigkeitModal()">
                    <i class="bi bi-plus-lg me-2"></i>Neue Tätigkeit hinzufügen
                </button>
            </div>
        </div>

        <!-- Seitenleiste: Bibliothek -->
        <div class="col-lg-3">
            <div class="card filter-sidebar">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-book me-2"></i>Bibliothek</h6>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs nav-fill mb-3" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#tab-gefaehrdungen">Gefährdungen</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#tab-massnahmen">Maßnahmen</a>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- Gefährdungen -->
                        <div class="tab-pane fade show active" id="tab-gefaehrdungen">
                            <input type="text" class="form-control form-control-sm mb-2"
                                   id="search-gefaehrdungen" placeholder="Suchen...">
                            <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                                <?php foreach ($gefaehrdungBibliothek as $gef): ?>
                                <a href="#" class="list-group-item list-group-item-action library-item"
                                   data-type="gefaehrdung"
                                   data-content="<?= htmlspecialchars(json_encode($gef)) ?>">
                                    <div class="d-flex justify-content-between">
                                        <strong class="small"><?= sanitize($gef['titel']) ?></strong>
                                    </div>
                                    <small class="text-muted"><?= sanitize(substr($gef['beschreibung'], 0, 80)) ?>...</small>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Maßnahmen -->
                        <div class="tab-pane fade" id="tab-massnahmen">
                            <input type="text" class="form-control form-control-sm mb-2"
                                   id="search-massnahmen" placeholder="Suchen...">
                            <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                                <?php foreach ($massnahmenBibliothek as $mass): ?>
                                <a href="#" class="list-group-item list-group-item-action library-item"
                                   data-type="massnahme"
                                   data-content="<?= htmlspecialchars(json_encode($mass)) ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <strong class="small"><?= sanitize($mass['titel']) ?></strong>
                                        <span class="stop-badge stop-<?= strtolower($mass['stop_typ']) ?>" style="width: 20px; height: 20px; font-size: 0.7rem;">
                                            <?= $mass['stop_typ'] ?>
                                        </span>
                                    </div>
                                    <small class="text-muted"><?= sanitize(substr($mass['beschreibung'], 0, 80)) ?>...</small>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Tätigkeit -->
<div class="modal fade" id="taetigkeitModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="taetigkeitForm">
                <input type="hidden" name="id" id="taetigkeit_id">
                <input type="hidden" name="gefaehrdungsbeurteilung_id" value="<?= $id ?>">

                <div class="modal-header">
                    <h5 class="modal-title" id="taetigkeitModalTitle">Neue Tätigkeit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="taetigkeit_position" class="form-label">Position *</label>
                        <input type="text" class="form-control" id="taetigkeit_position" name="position"
                               required placeholder="z.B. 1, 1.1, 2">
                    </div>
                    <div class="mb-3">
                        <label for="taetigkeit_name" class="form-label">Name der Tätigkeit *</label>
                        <input type="text" class="form-control" id="taetigkeit_name" name="name"
                               required placeholder="z.B. Büro & Bildschirmarbeitsplatz">
                    </div>
                    <div class="mb-3">
                        <label for="taetigkeit_beschreibung" class="form-label">Beschreibung</label>
                        <textarea class="form-control" id="taetigkeit_beschreibung" name="beschreibung"
                                  rows="2"></textarea>
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

<!-- Modal: Vorgang/Gefährdung -->
<div class="modal fade" id="vorgangModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form id="vorgangForm">
                <input type="hidden" name="id" id="vorgang_id">
                <input type="hidden" name="taetigkeit_id" id="vorgang_taetigkeit_id">

                <div class="modal-header">
                    <h5 class="modal-title" id="vorgangModalTitle">Neue Gefährdung</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="border-bottom pb-2 text-primary">Gefährdung</h6>

                            <div class="row">
                                <div class="col-4 mb-3">
                                    <label for="vorgang_position" class="form-label">Position *</label>
                                    <input type="text" class="form-control" id="vorgang_position" name="position"
                                           required placeholder="z.B. 1.1.1">
                                </div>
                                <div class="col-8 mb-3">
                                    <label for="vorgang_faktor" class="form-label">Gefährdungsfaktor</label>
                                    <select class="form-select" id="vorgang_faktor" name="gefaehrdung_faktor_id">
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
                                <label for="vorgang_beschreibung" class="form-label">Vorgang *</label>
                                <textarea class="form-control" id="vorgang_beschreibung" name="vorgang_beschreibung"
                                          rows="2" required placeholder="z.B. Langes Sitzen"></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="vorgang_gefaehrdung" class="form-label">Gefährdungsbeschreibung *</label>
                                <textarea class="form-control" id="vorgang_gefaehrdung" name="gefaehrdung"
                                          rows="3" required placeholder="Beschreibung der möglichen Gefährdung..."></textarea>
                            </div>

                            <div class="row">
                                <div class="col-4 mb-3">
                                    <label for="vorgang_schadenschwere" class="form-label">Schadenschwere (S) *</label>
                                    <select class="form-select" id="vorgang_schadenschwere" name="schadenschwere" required>
                                        <option value="1">1 - Leicht</option>
                                        <option value="2">2 - Mittel</option>
                                        <option value="3">3 - Schwer</option>
                                    </select>
                                </div>
                                <div class="col-4 mb-3">
                                    <label for="vorgang_wahrscheinlichkeit" class="form-label">Wahrscheinl. (W) *</label>
                                    <select class="form-select" id="vorgang_wahrscheinlichkeit" name="wahrscheinlichkeit" required>
                                        <option value="1">1 - Unwahrscheinlich</option>
                                        <option value="2">2 - Wahrscheinlich</option>
                                        <option value="3">3 - Sehr wahrsch.</option>
                                    </select>
                                </div>
                                <div class="col-4 mb-3">
                                    <label class="form-label">Risiko (R)</label>
                                    <div class="risk-cell p-2 text-center rounded" id="risk-preview">-</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h6 class="border-bottom pb-2 text-success">Maßnahmen</h6>

                            <div class="mb-3">
                                <label class="form-label">STOP-Prinzip</label>
                                <div class="d-flex gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="stop_s" name="stop_s" value="1">
                                        <label class="form-check-label stop-badge stop-s" for="stop_s">S</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="stop_t" name="stop_t" value="1">
                                        <label class="form-check-label stop-badge stop-t" for="stop_t">T</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="stop_o" name="stop_o" value="1">
                                        <label class="form-check-label stop-badge stop-o" for="stop_o">O</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="stop_p" name="stop_p" value="1">
                                        <label class="form-check-label stop-badge stop-p" for="stop_p">P</label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="vorgang_massnahmen" class="form-label">Maßnahmen</label>
                                <textarea class="form-control" id="vorgang_massnahmen" name="massnahmen"
                                          rows="4" placeholder="Beschreibung der Schutzmaßnahmen..."></textarea>
                            </div>

                            <div class="row">
                                <div class="col-4 mb-3">
                                    <label for="massnahme_schadenschwere" class="form-label">S nach Maßnahme</label>
                                    <select class="form-select" id="massnahme_schadenschwere" name="massnahme_schadenschwere">
                                        <option value="">-</option>
                                        <option value="1">1 - Leicht</option>
                                        <option value="2">2 - Mittel</option>
                                        <option value="3">3 - Schwer</option>
                                    </select>
                                </div>
                                <div class="col-4 mb-3">
                                    <label for="massnahme_wahrscheinlichkeit" class="form-label">W nach Maßnahme</label>
                                    <select class="form-select" id="massnahme_wahrscheinlichkeit" name="massnahme_wahrscheinlichkeit">
                                        <option value="">-</option>
                                        <option value="1">1 - Unwahrscheinlich</option>
                                        <option value="2">2 - Wahrscheinlich</option>
                                        <option value="3">3 - Sehr wahrsch.</option>
                                    </select>
                                </div>
                                <div class="col-4 mb-3">
                                    <label class="form-label">R nach Maßnahme</label>
                                    <div class="risk-cell p-2 text-center rounded" id="risk-preview-after">-</div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="vorgang_regelungen" class="form-label">Gesetzliche Regelungen</label>
                                <textarea class="form-control" id="vorgang_regelungen" name="gesetzliche_regelungen"
                                          rows="2" placeholder="z.B. ArbSchG, ArbStättV, DGUV..."></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="vorgang_bemerkungen" class="form-label">Sonstige Bemerkungen</label>
                                <textarea class="form-control" id="vorgang_bemerkungen" name="sonstige_bemerkungen"
                                          rows="2"></textarea>
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
const GB_ID = <?= $id ?>;
const API_URL = '<?= BASE_URL ?>/api';

// Modal-Instanzen
let taetigkeitModal, vorgangModal;

document.addEventListener('DOMContentLoaded', function() {
    taetigkeitModal = new bootstrap.Modal(document.getElementById('taetigkeitModal'));
    vorgangModal = new bootstrap.Modal(document.getElementById('vorgangModal'));

    // Risiko-Preview
    ['vorgang_schadenschwere', 'vorgang_wahrscheinlichkeit'].forEach(id => {
        document.getElementById(id).addEventListener('change', updateRiskPreview);
    });

    ['massnahme_schadenschwere', 'massnahme_wahrscheinlichkeit'].forEach(id => {
        document.getElementById(id).addEventListener('change', updateRiskPreviewAfter);
    });

    // Formulare
    document.getElementById('taetigkeitForm').addEventListener('submit', saveTaetigkeit);
    document.getElementById('vorgangForm').addEventListener('submit', saveVorgang);

    // Bibliothek-Suche
    document.getElementById('search-gefaehrdungen').addEventListener('input', function() {
        filterLibrary('gefaehrdungen', this.value);
    });
    document.getElementById('search-massnahmen').addEventListener('input', function() {
        filterLibrary('massnahmen', this.value);
    });

    // Bibliothek-Items
    document.querySelectorAll('.library-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const type = this.dataset.type;
            const content = JSON.parse(this.dataset.content);

            if (type === 'gefaehrdung') {
                document.getElementById('vorgang_beschreibung').value = content.titel;
                document.getElementById('vorgang_gefaehrdung').value = content.beschreibung;
                if (content.faktor_id) {
                    document.getElementById('vorgang_faktor').value = content.faktor_id;
                }
                if (content.gesetzliche_grundlage) {
                    document.getElementById('vorgang_regelungen').value = content.gesetzliche_grundlage;
                }
            } else if (type === 'massnahme') {
                const currentMassnahmen = document.getElementById('vorgang_massnahmen').value;
                document.getElementById('vorgang_massnahmen').value =
                    (currentMassnahmen ? currentMassnahmen + '\n\n' : '') + content.beschreibung;

                // STOP-Checkbox aktivieren
                const stopType = content.stop_typ.toLowerCase();
                document.getElementById('stop_' + stopType).checked = true;

                if (content.gesetzliche_grundlage) {
                    const currentRegelungen = document.getElementById('vorgang_regelungen').value;
                    if (!currentRegelungen.includes(content.gesetzliche_grundlage)) {
                        document.getElementById('vorgang_regelungen').value =
                            (currentRegelungen ? currentRegelungen + '\n' : '') + content.gesetzliche_grundlage;
                    }
                }
            }
        });
    });
});

function updateRiskPreview() {
    const s = parseInt(document.getElementById('vorgang_schadenschwere').value) || 1;
    const w = parseInt(document.getElementById('vorgang_wahrscheinlichkeit').value) || 1;
    const r = s * s * w;

    const preview = document.getElementById('risk-preview');
    preview.textContent = r;
    preview.className = 'risk-cell p-2 text-center rounded risk-' + r;
}

function updateRiskPreviewAfter() {
    const s = parseInt(document.getElementById('massnahme_schadenschwere').value);
    const w = parseInt(document.getElementById('massnahme_wahrscheinlichkeit').value);

    const preview = document.getElementById('risk-preview-after');

    if (s && w) {
        const r = s * s * w;
        preview.textContent = r;
        preview.className = 'risk-cell p-2 text-center rounded risk-' + r;
    } else {
        preview.textContent = '-';
        preview.className = 'risk-cell p-2 text-center rounded';
    }
}

function filterLibrary(type, query) {
    const container = document.querySelector('#tab-' + type + ' .list-group');
    const items = container.querySelectorAll('.library-item');

    items.forEach(item => {
        const text = item.textContent.toLowerCase();
        item.style.display = text.includes(query.toLowerCase()) ? '' : 'none';
    });
}

function showTaetigkeitModal(id = null) {
    const form = document.getElementById('taetigkeitForm');
    form.reset();
    document.getElementById('taetigkeit_id').value = '';
    document.getElementById('taetigkeitModalTitle').textContent = 'Neue Tätigkeit';
    taetigkeitModal.show();
}

function editTaetigkeit(id) {
    fetch(`${API_URL}/taetigkeiten.php?id=${id}`)
        .then(r => r.json())
        .then(data => {
            document.getElementById('taetigkeit_id').value = data.id;
            document.getElementById('taetigkeit_position').value = data.position;
            document.getElementById('taetigkeit_name').value = data.name;
            document.getElementById('taetigkeit_beschreibung').value = data.beschreibung || '';
            document.getElementById('taetigkeitModalTitle').textContent = 'Tätigkeit bearbeiten';
            taetigkeitModal.show();
        });
}

function deleteTaetigkeit(id) {
    if (!confirm('Tätigkeit und alle zugehörigen Gefährdungen wirklich löschen?')) return;

    fetch(`${API_URL}/taetigkeiten.php?id=${id}`, { method: 'DELETE' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Fehler: ' + data.error);
            }
        });
}

function saveTaetigkeit(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const id = formData.get('id');
    const method = id ? 'PUT' : 'POST';

    const data = Object.fromEntries(formData);

    fetch(`${API_URL}/taetigkeiten.php${id ? '?id=' + id : ''}`, {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            location.reload();
        } else {
            alert('Fehler: ' + result.error);
        }
    });
}

function showVorgangModal(taetigkeitId) {
    const form = document.getElementById('vorgangForm');
    form.reset();
    document.getElementById('vorgang_id').value = '';
    document.getElementById('vorgang_taetigkeit_id').value = taetigkeitId;
    document.getElementById('vorgangModalTitle').textContent = 'Neue Gefährdung';
    document.getElementById('risk-preview').textContent = '1';
    document.getElementById('risk-preview').className = 'risk-cell p-2 text-center rounded risk-1';
    document.getElementById('risk-preview-after').textContent = '-';
    document.getElementById('risk-preview-after').className = 'risk-cell p-2 text-center rounded';
    vorgangModal.show();
}

function editVorgang(id) {
    fetch(`${API_URL}/vorgaenge.php?id=${id}`)
        .then(r => r.json())
        .then(data => {
            document.getElementById('vorgang_id').value = data.id;
            document.getElementById('vorgang_taetigkeit_id').value = data.taetigkeit_id;
            document.getElementById('vorgang_position').value = data.position;
            document.getElementById('vorgang_faktor').value = data.gefaehrdung_faktor_id || '';
            document.getElementById('vorgang_beschreibung').value = data.vorgang_beschreibung;
            document.getElementById('vorgang_gefaehrdung').value = data.gefaehrdung;
            document.getElementById('vorgang_schadenschwere').value = data.schadenschwere;
            document.getElementById('vorgang_wahrscheinlichkeit').value = data.wahrscheinlichkeit;
            document.getElementById('stop_s').checked = data.stop_s == 1;
            document.getElementById('stop_t').checked = data.stop_t == 1;
            document.getElementById('stop_o').checked = data.stop_o == 1;
            document.getElementById('stop_p').checked = data.stop_p == 1;
            document.getElementById('vorgang_massnahmen').value = data.massnahmen || '';
            document.getElementById('massnahme_schadenschwere').value = data.massnahme_schadenschwere || '';
            document.getElementById('massnahme_wahrscheinlichkeit').value = data.massnahme_wahrscheinlichkeit || '';
            document.getElementById('vorgang_regelungen').value = data.gesetzliche_regelungen || '';
            document.getElementById('vorgang_bemerkungen').value = data.sonstige_bemerkungen || '';

            document.getElementById('vorgangModalTitle').textContent = 'Gefährdung bearbeiten';
            updateRiskPreview();
            updateRiskPreviewAfter();
            vorgangModal.show();
        });
}

function deleteVorgang(id) {
    if (!confirm('Gefährdung wirklich löschen?')) return;

    fetch(`${API_URL}/vorgaenge.php?id=${id}`, { method: 'DELETE' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Fehler: ' + data.error);
            }
        });
}

function saveVorgang(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const id = formData.get('id');
    const method = id ? 'PUT' : 'POST';

    const data = Object.fromEntries(formData);

    // Checkboxen korrekt behandeln
    data.stop_s = document.getElementById('stop_s').checked ? 1 : 0;
    data.stop_t = document.getElementById('stop_t').checked ? 1 : 0;
    data.stop_o = document.getElementById('stop_o').checked ? 1 : 0;
    data.stop_p = document.getElementById('stop_p').checked ? 1 : 0;

    fetch(`${API_URL}/vorgaenge.php${id ? '?id=' + id : ''}`, {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            location.reload();
        } else {
            alert('Fehler: ' + result.error);
        }
    });
}
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
