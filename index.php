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

// Benutzer-Projekte laden (ohne archivierte)
if ($isAdmin) {
    $meineProjekte = $db->fetchAll("
        SELECT p.*,
               (SELECT COUNT(*) FROM projekt_gefaehrdungen WHERE projekt_id = p.id) as gef_count,
               (SELECT COUNT(*) FROM projekt_gefaehrdungen WHERE projekt_id = p.id AND (massnahmen IS NULL OR massnahmen = '')) as ohne_massnahmen
        FROM projekte p
        WHERE p.status != 'archiviert'
        ORDER BY p.status = 'aktiv' DESC, p.zeitraum_von ASC
        LIMIT 5
    ");
    $projektCount = $db->fetchOne("SELECT COUNT(*) as cnt FROM projekte WHERE status != 'archiviert'")['cnt'];
} else {
    $meineProjekte = $db->fetchAll("
        SELECT p.*,
               bp.berechtigung,
               (SELECT COUNT(*) FROM projekt_gefaehrdungen WHERE projekt_id = p.id) as gef_count,
               (SELECT COUNT(*) FROM projekt_gefaehrdungen WHERE projekt_id = p.id AND (massnahmen IS NULL OR massnahmen = '')) as ohne_massnahmen
        FROM projekte p
        JOIN benutzer_projekte bp ON p.id = bp.projekt_id
        WHERE bp.benutzer_id = ? AND p.status != 'archiviert'
        ORDER BY p.status = 'aktiv' DESC, p.zeitraum_von ASC
        LIMIT 5
    ", [$userId]);
    $projektCount = $db->fetchOne("
        SELECT COUNT(*) as cnt FROM projekte p
        JOIN benutzer_projekte bp ON p.id = bp.projekt_id
        WHERE bp.benutzer_id = ? AND p.status != 'archiviert'
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
                        <?php if (hasRole(ROLE_EDITOR)): ?>
                        <a href="<?= BASE_URL ?>/bibliothek/gefaehrdungen.php" class="btn btn-outline-success py-3">
                            <i class="bi bi-book me-2"></i>Bibliothek
                        </a>
                        <?php endif; ?>

                        <?php if (hasRole(ROLE_ADMIN)): ?>
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

</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
