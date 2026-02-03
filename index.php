<?php
/**
 * Dashboard
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Gefaehrdungsbeurteilung.php';

requireLogin();

$db = Database::getInstance();
$gbClass = new Gefaehrdungsbeurteilung();

// Statistiken laden
$stats = [
    'beurteilungen_gesamt' => $db->fetchOne("SELECT COUNT(*) as cnt FROM gefaehrdungsbeurteilungen")['cnt'],
    'beurteilungen_aktiv' => $db->fetchOne("SELECT COUNT(*) as cnt FROM gefaehrdungsbeurteilungen WHERE status = 'aktiv'")['cnt'],
    'beurteilungen_entwurf' => $db->fetchOne("SELECT COUNT(*) as cnt FROM gefaehrdungsbeurteilungen WHERE status = 'entwurf'")['cnt'],
    'gefaehrdungen_gesamt' => $db->fetchOne("SELECT COUNT(*) as cnt FROM vorgaenge")['cnt'],
    'hohe_risiken' => $db->fetchOne("SELECT COUNT(*) as cnt FROM vorgaenge WHERE risikobewertung >= 9")['cnt'],
    'unternehmen' => $db->fetchOne("SELECT COUNT(*) as cnt FROM unternehmen")['cnt']
];

// Letzte Beurteilungen
$letzteBeurteilungen = $db->fetchAll("
    SELECT gb.*, u.name as unternehmen_name
    FROM gefaehrdungsbeurteilungen gb
    LEFT JOIN unternehmen u ON gb.unternehmen_id = u.id
    ORDER BY gb.aktualisiert_am DESC
    LIMIT 5
");

// Risiko-Verteilung
$risikoVerteilung = $db->fetchAll("
    SELECT
        CASE
            WHEN risikobewertung <= 2 THEN 'Gering'
            WHEN risikobewertung <= 4 THEN 'Mittel'
            WHEN risikobewertung <= 8 THEN 'Hoch'
            ELSE 'Sehr hoch'
        END as risiko_level,
        COUNT(*) as anzahl
    FROM vorgaenge
    GROUP BY risiko_level
    ORDER BY FIELD(risiko_level, 'Sehr hoch', 'Hoch', 'Mittel', 'Gering')
");

$pageTitle = 'Dashboard';
require_once __DIR__ . '/templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="bi bi-speedometer2 me-2"></i>Dashboard
            </h1>
            <p class="text-muted mb-0">Willkommen, <?= sanitize(getCurrentUser()['voller_name']) ?>!</p>
        </div>
        <?php if (hasRole(ROLE_EDITOR)): ?>
        <a href="<?= BASE_URL ?>/beurteilung_neu.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-2"></i>Neue Beurteilung
        </a>
        <?php endif; ?>
    </div>

    <!-- Statistik-Karten -->
    <div class="row mb-4">
        <div class="col-md-6 col-lg-3 mb-3">
            <div class="card dashboard-card bg-primary text-white h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-0"><?= $stats['beurteilungen_gesamt'] ?></h2>
                        <p class="mb-0">Beurteilungen gesamt</p>
                    </div>
                    <i class="bi bi-file-earmark-text card-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3 mb-3">
            <div class="card dashboard-card bg-success text-white h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-0"><?= $stats['beurteilungen_aktiv'] ?></h2>
                        <p class="mb-0">Aktive Beurteilungen</p>
                    </div>
                    <i class="bi bi-check-circle card-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3 mb-3">
            <div class="card dashboard-card bg-warning text-dark h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-0"><?= $stats['gefaehrdungen_gesamt'] ?></h2>
                        <p class="mb-0">Erfasste Gefährdungen</p>
                    </div>
                    <i class="bi bi-exclamation-triangle card-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3 mb-3">
            <div class="card dashboard-card bg-danger text-white h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-0"><?= $stats['hohe_risiken'] ?></h2>
                        <p class="mb-0">Hohe Risiken (R≥9)</p>
                    </div>
                    <i class="bi bi-shield-exclamation card-icon"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Letzte Beurteilungen -->
        <div class="col-lg-8 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Letzte Beurteilungen</h5>
                    <a href="<?= BASE_URL ?>/beurteilungen.php" class="btn btn-sm btn-outline-primary">Alle anzeigen</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($letzteBeurteilungen)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-file-earmark-text display-6 mb-2"></i>
                        <p>Noch keine Beurteilungen vorhanden.</p>
                        <?php if (hasRole(ROLE_EDITOR)): ?>
                        <a href="<?= BASE_URL ?>/beurteilung_neu.php" class="btn btn-primary">
                            Erste Beurteilung erstellen
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Titel</th>
                                    <th>Unternehmen</th>
                                    <th>Status</th>
                                    <th>Aktualisiert</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($letzteBeurteilungen as $gb): ?>
                                <tr>
                                    <td>
                                        <a href="<?= BASE_URL ?>/beurteilung.php?id=<?= $gb['id'] ?>" class="text-decoration-none">
                                            <?= sanitize($gb['titel']) ?>
                                        </a>
                                    </td>
                                    <td><?= sanitize($gb['unternehmen_name']) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= $gb['status'] ?>">
                                            <?= ucfirst($gb['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d.m.Y H:i', strtotime($gb['aktualisiert_am'])) ?></td>
                                    <td>
                                        <a href="<?= BASE_URL ?>/beurteilung.php?id=<?= $gb['id'] ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Risiko-Übersicht -->
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Risiko-Verteilung</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($risikoVerteilung)): ?>
                    <p class="text-muted text-center">Keine Daten vorhanden</p>
                    <?php else: ?>
                    <?php
                    $risikoFarben = [
                        'Gering' => '#92D050',
                        'Mittel' => '#FFFF00',
                        'Hoch' => '#FFC000',
                        'Sehr hoch' => '#FF0000'
                    ];
                    $gesamt = array_sum(array_column($risikoVerteilung, 'anzahl'));
                    ?>
                    <?php foreach ($risikoVerteilung as $rv): ?>
                    <?php $prozent = $gesamt > 0 ? round(($rv['anzahl'] / $gesamt) * 100) : 0; ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span><?= $rv['risiko_level'] ?></span>
                            <span><?= $rv['anzahl'] ?> (<?= $prozent ?>%)</span>
                        </div>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar"
                                 role="progressbar"
                                 style="width: <?= $prozent ?>%; background-color: <?= $risikoFarben[$rv['risiko_level']] ?>; color: <?= $rv['risiko_level'] === 'Sehr hoch' ? 'white' : '#333' ?>"
                                 aria-valuenow="<?= $prozent ?>"
                                 aria-valuemin="0"
                                 aria-valuemax="100">
                                <?= $prozent ?>%
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Legende -->
                    <div class="mt-4 pt-3 border-top">
                        <h6>Risikobewertung</h6>
                        <small class="text-muted">
                            <strong>R = S² × W</strong><br>
                            S = Schadenschwere (1-3)<br>
                            W = Wahrscheinlichkeit (1-3)
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Schnellzugriff -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-lightning me-2"></i>Schnellzugriff</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 col-6 mb-3">
                            <a href="<?= BASE_URL ?>/beurteilungen.php" class="btn btn-outline-primary w-100 py-3">
                                <i class="bi bi-file-earmark-text d-block mb-2" style="font-size: 1.5rem;"></i>
                                Beurteilungen
                            </a>
                        </div>
                        <?php if (hasRole(ROLE_EDITOR)): ?>
                        <div class="col-md-3 col-6 mb-3">
                            <a href="<?= BASE_URL ?>/bibliothek/gefaehrdungen.php" class="btn btn-outline-warning w-100 py-3">
                                <i class="bi bi-exclamation-triangle d-block mb-2" style="font-size: 1.5rem;"></i>
                                Gefährdungs-Bibliothek
                            </a>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <a href="<?= BASE_URL ?>/bibliothek/massnahmen.php" class="btn btn-outline-success w-100 py-3">
                                <i class="bi bi-check2-circle d-block mb-2" style="font-size: 1.5rem;"></i>
                                Maßnahmen-Bibliothek
                            </a>
                        </div>
                        <?php endif; ?>
                        <?php if (hasRole(ROLE_ADMIN)): ?>
                        <div class="col-md-3 col-6 mb-3">
                            <a href="<?= BASE_URL ?>/admin/benutzer.php" class="btn btn-outline-secondary w-100 py-3">
                                <i class="bi bi-people d-block mb-2" style="font-size: 1.5rem;"></i>
                                Benutzerverwaltung
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
