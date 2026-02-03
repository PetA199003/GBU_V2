<?php
/**
 * Dashboard
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

requireLogin();

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$isAdmin = hasRole(ROLE_ADMIN);

// Benutzer-Projekte laden
if ($isAdmin) {
    $meineProjekte = $db->fetchAll("
        SELECT p.*,
               (SELECT COUNT(*) FROM projekt_gefaehrdungen WHERE projekt_id = p.id) as gef_count,
               (SELECT COUNT(*) FROM projekt_gefaehrdungen WHERE projekt_id = p.id AND (massnahmen IS NULL OR massnahmen = '')) as ohne_massnahmen
        FROM projekte p
        WHERE p.status = 'aktiv'
        ORDER BY p.zeitraum_von ASC
        LIMIT 5
    ");
    $projektCount = $db->fetchOne("SELECT COUNT(*) as cnt FROM projekte")['cnt'];
} else {
    $meineProjekte = $db->fetchAll("
        SELECT p.*,
               bp.berechtigung,
               (SELECT COUNT(*) FROM projekt_gefaehrdungen WHERE projekt_id = p.id) as gef_count,
               (SELECT COUNT(*) FROM projekt_gefaehrdungen WHERE projekt_id = p.id AND (massnahmen IS NULL OR massnahmen = '')) as ohne_massnahmen
        FROM projekte p
        JOIN benutzer_projekte bp ON p.id = bp.projekt_id
        WHERE bp.benutzer_id = ? AND p.status IN ('aktiv', 'geplant')
        ORDER BY p.status = 'aktiv' DESC, p.zeitraum_von ASC
        LIMIT 5
    ", [$userId]);
    $projektCount = $db->fetchOne("
        SELECT COUNT(*) as cnt FROM projekte p
        JOIN benutzer_projekte bp ON p.id = bp.projekt_id
        WHERE bp.benutzer_id = ?
    ", [$userId])['cnt'];
}

// Statistiken
$stats = [];

if ($isAdmin) {
    // Admin sieht globale Statistiken
    $stats['projekte_aktiv'] = $db->fetchOne("SELECT COUNT(*) as cnt FROM projekte WHERE status = 'aktiv'")['cnt'];
    $stats['gefaehrdungen_gesamt'] = $db->fetchOne("SELECT COUNT(*) as cnt FROM projekt_gefaehrdungen")['cnt'];
    $stats['hohe_risiken'] = $db->fetchOne("SELECT COUNT(*) as cnt FROM projekt_gefaehrdungen WHERE risikobewertung >= 9")['cnt'];
    $stats['ohne_massnahmen'] = $db->fetchOne("SELECT COUNT(*) as cnt FROM projekt_gefaehrdungen WHERE massnahmen IS NULL OR massnahmen = ''")['cnt'];
} else {
    // Benutzer sieht nur seine Projekte
    $stats['projekte_aktiv'] = $db->fetchOne("
        SELECT COUNT(*) as cnt FROM projekte p
        JOIN benutzer_projekte bp ON p.id = bp.projekt_id
        WHERE bp.benutzer_id = ? AND p.status = 'aktiv'
    ", [$userId])['cnt'];

    $stats['gefaehrdungen_gesamt'] = $db->fetchOne("
        SELECT COUNT(*) as cnt FROM projekt_gefaehrdungen pg
        JOIN projekte p ON pg.projekt_id = p.id
        JOIN benutzer_projekte bp ON p.id = bp.projekt_id
        WHERE bp.benutzer_id = ?
    ", [$userId])['cnt'];

    $stats['hohe_risiken'] = $db->fetchOne("
        SELECT COUNT(*) as cnt FROM projekt_gefaehrdungen pg
        JOIN projekte p ON pg.projekt_id = p.id
        JOIN benutzer_projekte bp ON p.id = bp.projekt_id
        WHERE bp.benutzer_id = ? AND pg.risikobewertung >= 9
    ", [$userId])['cnt'];

    $stats['ohne_massnahmen'] = $db->fetchOne("
        SELECT COUNT(*) as cnt FROM projekt_gefaehrdungen pg
        JOIN projekte p ON pg.projekt_id = p.id
        JOIN benutzer_projekte bp ON p.id = bp.projekt_id
        WHERE bp.benutzer_id = ? AND (pg.massnahmen IS NULL OR pg.massnahmen = '')
    ", [$userId])['cnt'];
}

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
        <?php if (hasRole(ROLE_ADMIN)): ?>
        <a href="<?= BASE_URL ?>/admin/projekte.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-2"></i>Neues Projekt
        </a>
        <?php endif; ?>
    </div>

    <!-- Statistik-Karten -->
    <div class="row mb-4">
        <div class="col-md-6 col-lg-3 mb-3">
            <div class="card dashboard-card bg-primary text-white h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-0"><?= $projektCount ?></h2>
                        <p class="mb-0">Meine Projekte</p>
                    </div>
                    <i class="bi bi-folder card-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3 mb-3">
            <div class="card dashboard-card bg-success text-white h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-0"><?= $stats['projekte_aktiv'] ?></h2>
                        <p class="mb-0">Aktive Projekte</p>
                    </div>
                    <i class="bi bi-check-circle card-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3 mb-3">
            <div class="card dashboard-card bg-warning text-dark h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-0"><?= $stats['ohne_massnahmen'] ?></h2>
                        <p class="mb-0">Ohne Maßnahmen</p>
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
        <!-- Meine Projekte -->
        <div class="col-lg-8 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-folder me-2"></i>Meine Projekte</h5>
                    <a href="<?= BASE_URL ?>/projekte.php" class="btn btn-sm btn-outline-primary">Alle anzeigen</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($meineProjekte)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-folder-x display-6 mb-2"></i>
                        <p>Noch keine Projekte zugewiesen.</p>
                        <?php if ($isAdmin): ?>
                        <a href="<?= BASE_URL ?>/admin/projekte.php" class="btn btn-primary">
                            Erstes Projekt erstellen
                        </a>
                        <?php else: ?>
                        <p class="small">Bitte wenden Sie sich an einen Administrator.</p>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Projekt</th>
                                    <th>Zeitraum</th>
                                    <th>Status</th>
                                    <th>Gefährdungen</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($meineProjekte as $p): ?>
                                <tr>
                                    <td>
                                        <a href="<?= BASE_URL ?>/projekt.php?id=<?= $p['id'] ?>" class="text-decoration-none">
                                            <strong><?= sanitize($p['name']) ?></strong>
                                        </a>
                                        <br>
                                        <small class="text-muted">
                                            <i class="bi bi-geo-alt"></i> <?= sanitize($p['location']) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small>
                                            <?= date('d.m.Y', strtotime($p['zeitraum_von'])) ?><br>
                                            bis <?= date('d.m.Y', strtotime($p['zeitraum_bis'])) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $p['status'] === 'aktiv' ? 'success' : ($p['status'] === 'geplant' ? 'warning text-dark' : 'secondary') ?>">
                                            <?= ucfirst($p['status']) ?>
                                        </span>
                                        <br>
                                        <span class="badge bg-<?= $p['indoor_outdoor'] === 'indoor' ? 'info' : ($p['indoor_outdoor'] === 'outdoor' ? 'success' : 'primary') ?>">
                                            <?= ucfirst($p['indoor_outdoor']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?= $p['gef_count'] ?> gesamt</span>
                                        <?php if ($p['ohne_massnahmen'] > 0): ?>
                                        <br><span class="badge bg-warning text-dark"><?= $p['ohne_massnahmen'] ?> ohne Maßn.</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?= BASE_URL ?>/projekt.php?id=<?= $p['id'] ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-arrow-right"></i>
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

        <!-- Schnellzugriff -->
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-lightning me-2"></i>Schnellzugriff</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="<?= BASE_URL ?>/projekte.php" class="btn btn-outline-primary py-3">
                            <i class="bi bi-folder me-2"></i>Meine Projekte
                        </a>

                        <?php if (hasRole(ROLE_EDITOR)): ?>
                        <a href="<?= BASE_URL ?>/bibliothek/gefaehrdungen.php" class="btn btn-outline-warning py-3">
                            <i class="bi bi-exclamation-triangle me-2"></i>Gefährdungs-Bibliothek
                        </a>
                        <a href="<?= BASE_URL ?>/bibliothek/massnahmen.php" class="btn btn-outline-success py-3">
                            <i class="bi bi-check2-circle me-2"></i>Maßnahmen-Bibliothek
                        </a>
                        <?php endif; ?>

                        <?php if (hasRole(ROLE_ADMIN)): ?>
                        <hr>
                        <a href="<?= BASE_URL ?>/admin/projekte.php" class="btn btn-outline-secondary py-3">
                            <i class="bi bi-folder-plus me-2"></i>Projekte verwalten
                        </a>
                        <a href="<?= BASE_URL ?>/admin/benutzer.php" class="btn btn-outline-secondary py-3">
                            <i class="bi bi-people me-2"></i>Benutzerverwaltung
                        </a>
                        <a href="<?= BASE_URL ?>/admin/kategorien.php" class="btn btn-outline-secondary py-3">
                            <i class="bi bi-tags me-2"></i>Kategorien verwalten
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Risiko-Hinweis (wenn hohe Risiken vorhanden) -->
    <?php if ($stats['hohe_risiken'] > 0): ?>
    <div class="row">
        <div class="col-12">
            <div class="alert alert-danger d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill me-3" style="font-size: 1.5rem;"></i>
                <div>
                    <strong>Achtung!</strong> Es gibt <?= $stats['hohe_risiken'] ?> Gefährdung(en) mit hohem Risiko (R ≥ 9).
                    Diese sollten prioritär behandelt werden.
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.card-icon {
    font-size: 2.5rem;
    opacity: 0.5;
}
.dashboard-card {
    transition: transform 0.2s;
}
.dashboard-card:hover {
    transform: translateY(-2px);
}
</style>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
