<?php
/**
 * Meine Projekte - Ansicht für Benutzer
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

requireLogin();

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$isAdmin = hasRole(ROLE_ADMIN);

// Projekte des Benutzers laden (oder alle für Admin) - ohne archivierte
if ($isAdmin) {
    $projekte = $db->fetchAll("
        SELECT p.*,
               CONCAT(b.vorname, ' ', b.nachname) as erstellt_von_name,
               (SELECT COUNT(*) FROM projekt_gefaehrdungen WHERE projekt_id = p.id) as gefaehrdungen_count,
               'bearbeiten' as berechtigung
        FROM projekte p
        LEFT JOIN benutzer b ON p.erstellt_von = b.id
        WHERE p.status != 'archiviert'
        ORDER BY p.status = 'aktiv' DESC, p.zeitraum_von DESC
    ");
} else {
    $projekte = $db->fetchAll("
        SELECT p.*,
               CONCAT(b.vorname, ' ', b.nachname) as erstellt_von_name,
               (SELECT COUNT(*) FROM projekt_gefaehrdungen WHERE projekt_id = p.id) as gefaehrdungen_count,
               bp.berechtigung
        FROM projekte p
        JOIN benutzer_projekte bp ON p.id = bp.projekt_id
        LEFT JOIN benutzer b ON p.erstellt_von = b.id
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
            <p class="text-muted mb-0">Projekte, die Ihnen zugewiesen sind</p>
        </div>
        <?php if ($isAdmin): ?>
        <a href="<?= BASE_URL ?>/admin/projekte.php" class="btn btn-outline-primary">
            <i class="bi bi-gear me-2"></i>Projekte verwalten
        </a>
        <?php endif; ?>
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

    <!-- Filter nach Status -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#alle">
                Alle <span class="badge bg-secondary"><?= count($projekte) ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#aktiv">
                Aktiv <span class="badge bg-success"><?= count(array_filter($projekte, fn($p) => $p['status'] === 'aktiv')) ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#geplant">
                Geplant <span class="badge bg-warning text-dark"><?= count(array_filter($projekte, fn($p) => $p['status'] === 'geplant')) ?></span>
            </a>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="alle">
            <div class="row">
                <?php foreach ($projekte as $p): ?>
                <?php include __DIR__ . '/templates/_projekt_card.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="tab-pane fade" id="aktiv">
            <div class="row">
                <?php foreach (array_filter($projekte, fn($p) => $p['status'] === 'aktiv') as $p): ?>
                <?php include __DIR__ . '/templates/_projekt_card.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="tab-pane fade" id="geplant">
            <div class="row">
                <?php foreach (array_filter($projekte, fn($p) => $p['status'] === 'geplant') as $p): ?>
                <?php include __DIR__ . '/templates/_projekt_card.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
