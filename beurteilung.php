<?php
/**
 * Gefährdungsbeurteilung Ansicht (Read-Only)
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Gefaehrdungsbeurteilung.php';

requireLogin();

$gbClass = new Gefaehrdungsbeurteilung();

$id = $_GET['id'] ?? null;
if (!$id) {
    redirect('beurteilungen.php');
}

$beurteilung = $gbClass->getById($id);
if (!$beurteilung) {
    setFlashMessage('error', 'Gefährdungsbeurteilung nicht gefunden.');
    redirect('beurteilungen.php');
}

$pageTitle = $beurteilung['titel'];
require_once __DIR__ . '/templates/header.php';
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-start mb-4 no-print">
        <div>
            <h1 class="h3 mb-1">
                <i class="bi bi-file-earmark-text me-2"></i><?= sanitize($beurteilung['titel']) ?>
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/beurteilungen.php">Beurteilungen</a></li>
                    <li class="breadcrumb-item active">Ansicht</li>
                </ol>
            </nav>
        </div>
        <div class="btn-group">
            <?php if (hasRole(ROLE_EDITOR)): ?>
            <a href="<?= BASE_URL ?>/beurteilung_edit.php?id=<?= $id ?>" class="btn btn-primary">
                <i class="bi bi-pencil me-1"></i>Bearbeiten
            </a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/api/export.php?type=pdf&id=<?= $id ?>" class="btn btn-outline-danger" target="_blank">
                <i class="bi bi-file-pdf me-1"></i>PDF
            </a>
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i>Drucken
            </button>
        </div>
    </div>

    <!-- Kopfbereich für Druck -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Gefährdungsbeurteilung</h4>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <th style="width: 40%;">Unternehmen:</th>
                            <td><?= sanitize($beurteilung['unternehmen_name']) ?></td>
                        </tr>
                        <?php if ($beurteilung['arbeitsbereich_name']): ?>
                        <tr>
                            <th>Arbeitsbereich:</th>
                            <td><?= sanitize($beurteilung['arbeitsbereich_name']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th>Titel:</th>
                            <td><strong><?= sanitize($beurteilung['titel']) ?></strong></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <th style="width: 40%;">Ersteller:</th>
                            <td><?= sanitize($beurteilung['ersteller_name']) ?></td>
                        </tr>
                        <tr>
                            <th>Erstellt am:</th>
                            <td><?= date('d.m.Y', strtotime($beurteilung['erstelldatum'])) ?></td>
                        </tr>
                        <?php if ($beurteilung['ueberarbeitungsdatum']): ?>
                        <tr>
                            <th>Überarbeitet am:</th>
                            <td><?= date('d.m.Y', strtotime($beurteilung['ueberarbeitungsdatum'])) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th>Status:</th>
                            <td>
                                <span class="status-badge status-<?= $beurteilung['status'] ?>">
                                    <?= ucfirst($beurteilung['status']) ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Legende -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header py-2">
                    <strong>Schadenschwere (S) / Wahrscheinlichkeit (W)</strong>
                </div>
                <div class="card-body py-2">
                    <div class="d-flex flex-wrap gap-2">
                        <span class="scale-item scale-1">1 = Leicht/Unwahrscheinlich</span>
                        <span class="scale-item scale-2">2 = Mittel/Wahrscheinlich</span>
                        <span class="scale-item scale-3">3 = Schwer/Sehr wahrscheinlich</span>
                    </div>
                    <small class="text-muted d-block mt-2">Risikobewertung (R) = S² × W</small>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header py-2">
                    <strong>STOP-Prinzip</strong>
                </div>
                <div class="card-body py-2">
                    <div class="d-flex flex-wrap gap-3">
                        <span><span class="stop-badge stop-s">S</span> Substitution</span>
                        <span><span class="stop-badge stop-t">T</span> Technisch</span>
                        <span><span class="stop-badge stop-o">O</span> Organisatorisch</span>
                        <span><span class="stop-badge stop-p">P</span> Persönlich (PSA)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gefährdungsbeurteilung Tabelle -->
    <?php if (empty($beurteilung['taetigkeiten'])): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>Keine Tätigkeiten erfasst.
    </div>
    <?php else: ?>

    <?php foreach ($beurteilung['taetigkeiten'] as $taetigkeit): ?>
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                <span class="badge bg-light text-primary me-2"><?= sanitize($taetigkeit['position']) ?></span>
                <?= sanitize($taetigkeit['name']) ?>
            </h5>
        </div>

        <?php if (!empty($taetigkeit['vorgaenge'])): ?>
        <div class="table-responsive">
            <table class="table table-bordered gb-table mb-0">
                <thead>
                    <tr>
                        <th rowspan="2" style="width: 50px;">Position</th>
                        <th rowspan="2" style="width: 12%;">Vorgang</th>
                        <th rowspan="2" style="width: 15%;">Gefährdung</th>
                        <th rowspan="2" style="width: 10%;">Gefährdungs- und Belastungsfaktoren</th>
                        <th colspan="3" class="risk-header text-center">Risikobewertung</th>
                        <th rowspan="2" style="width: 80px;">STOP</th>
                        <th rowspan="2" style="width: 18%;">Maßnahmen</th>
                        <th colspan="3" class="measure-header text-center">Überprüfung der Wirksamkeit</th>
                        <th rowspan="2" style="width: 8%;">Gesetzliche Regelungen</th>
                        <th rowspan="2" style="width: 8%;">Mängel behoben</th>
                    </tr>
                    <tr>
                        <th class="risk-header" style="width: 35px;">S</th>
                        <th class="risk-header" style="width: 35px;">W</th>
                        <th class="risk-header" style="width: 35px;">R</th>
                        <th class="measure-header" style="width: 35px;">S</th>
                        <th class="measure-header" style="width: 35px;">W</th>
                        <th class="measure-header" style="width: 35px;">R</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($taetigkeit['vorgaenge'] as $vorgang): ?>
                    <tr>
                        <td class="position-cell"><?= sanitize($vorgang['position']) ?></td>
                        <td><?= nl2br(sanitize($vorgang['vorgang_beschreibung'])) ?></td>
                        <td><?= nl2br(sanitize($vorgang['gefaehrdung'])) ?></td>
                        <td>
                            <?php if ($vorgang['faktor_nummer']): ?>
                            <small><?= sanitize($vorgang['faktor_nummer']) ?></small><br>
                            <?= sanitize($vorgang['faktor_name']) ?>
                            <?php endif; ?>
                        </td>
                        <td class="risk-cell risk-<?= $vorgang['schadenschwere'] ?>"><?= $vorgang['schadenschwere'] ?></td>
                        <td class="risk-cell risk-<?= $vorgang['wahrscheinlichkeit'] ?>"><?= $vorgang['wahrscheinlichkeit'] ?></td>
                        <td class="risk-cell risk-<?= $vorgang['risikobewertung'] ?>"><?= $vorgang['risikobewertung'] ?></td>
                        <td class="text-center">
                            <?php if ($vorgang['stop_s']): ?><span class="stop-badge stop-s">S</span><?php endif; ?>
                            <?php if ($vorgang['stop_t']): ?><span class="stop-badge stop-t">T</span><?php endif; ?>
                            <?php if ($vorgang['stop_o']): ?><span class="stop-badge stop-o">O</span><?php endif; ?>
                            <?php if ($vorgang['stop_p']): ?><span class="stop-badge stop-p">P</span><?php endif; ?>
                        </td>
                        <td><?= nl2br(sanitize($vorgang['massnahmen'])) ?></td>
                        <td class="risk-cell <?= $vorgang['massnahme_schadenschwere'] ? 'risk-' . $vorgang['massnahme_schadenschwere'] : '' ?>">
                            <?= $vorgang['massnahme_schadenschwere'] ?: '-' ?>
                        </td>
                        <td class="risk-cell <?= $vorgang['massnahme_wahrscheinlichkeit'] ? 'risk-' . $vorgang['massnahme_wahrscheinlichkeit'] : '' ?>">
                            <?= $vorgang['massnahme_wahrscheinlichkeit'] ?: '-' ?>
                        </td>
                        <td class="risk-cell <?= $vorgang['massnahme_risikobewertung'] ? 'risk-' . $vorgang['massnahme_risikobewertung'] : '' ?>">
                            <?= $vorgang['massnahme_risikobewertung'] ?: '-' ?>
                        </td>
                        <td><small><?= nl2br(sanitize($vorgang['gesetzliche_regelungen'])) ?></small></td>
                        <td>
                            <?php if ($vorgang['maengel_behoben_am']): ?>
                            <?= date('d.m.Y', strtotime($vorgang['maengel_behoben_am'])) ?><br>
                            <small><?= sanitize($vorgang['maengel_behoben_von']) ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="card-body text-muted text-center">
            Keine Gefährdungen erfasst.
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>

    <!-- Bemerkungen -->
    <?php if ($beurteilung['bemerkungen']): ?>
    <div class="card mb-4">
        <div class="card-header">
            <strong>Bemerkungen</strong>
        </div>
        <div class="card-body">
            <?= nl2br(sanitize($beurteilung['bemerkungen'])) ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Unterschriften (für Druck) -->
    <div class="card mt-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 text-center">
                    <div class="border-bottom border-dark mb-2" style="height: 50px;"></div>
                    <small>Ersteller / Datum</small>
                </div>
                <div class="col-md-4 text-center">
                    <div class="border-bottom border-dark mb-2" style="height: 50px;"></div>
                    <small>Fachkraft für Arbeitssicherheit / Datum</small>
                </div>
                <div class="col-md-4 text-center">
                    <div class="border-bottom border-dark mb-2" style="height: 50px;"></div>
                    <small>Geschäftsführung / Datum</small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
