<?php
/**
 * Übersicht aller Gefährdungsbeurteilungen
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Gefaehrdungsbeurteilung.php';

requireLogin();

$gb = new Gefaehrdungsbeurteilung();
$db = Database::getInstance();

// Filter
$filters = [
    'unternehmen_id' => $_GET['unternehmen'] ?? null,
    'status' => $_GET['status'] ?? null,
    'search' => $_GET['q'] ?? null
];

$beurteilungen = $gb->getAll($filters);
$unternehmen = $db->fetchAll("SELECT * FROM unternehmen ORDER BY name");

$pageTitle = 'Gefährdungsbeurteilungen';
require_once __DIR__ . '/templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="bi bi-file-earmark-text me-2"></i>Gefährdungsbeurteilungen
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Gefährdungsbeurteilungen</li>
                </ol>
            </nav>
        </div>
        <?php if (hasRole(ROLE_EDITOR)): ?>
        <a href="<?= BASE_URL ?>/beurteilung_neu.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-2"></i>Neue Beurteilung
        </a>
        <?php endif; ?>
    </div>

    <!-- Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Suche</label>
                    <input type="text" name="q" class="form-control"
                           placeholder="Titel oder Ersteller..."
                           value="<?= sanitize($filters['search'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Unternehmen</label>
                    <select name="unternehmen" class="form-select">
                        <option value="">Alle Unternehmen</option>
                        <?php foreach ($unternehmen as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $filters['unternehmen_id'] == $u['id'] ? 'selected' : '' ?>>
                            <?= sanitize($u['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Alle Status</option>
                        <option value="entwurf" <?= $filters['status'] === 'entwurf' ? 'selected' : '' ?>>Entwurf</option>
                        <option value="aktiv" <?= $filters['status'] === 'aktiv' ? 'selected' : '' ?>>Aktiv</option>
                        <option value="archiviert" <?= $filters['status'] === 'archiviert' ? 'selected' : '' ?>>Archiviert</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-outline-primary w-100">
                        <i class="bi bi-search me-1"></i>Filtern
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($beurteilungen)): ?>
    <!-- Leerer Zustand -->
    <div class="card">
        <div class="card-body empty-state">
            <i class="bi bi-file-earmark-text"></i>
            <h5>Keine Gefährdungsbeurteilungen gefunden</h5>
            <?php if ($filters['search'] || $filters['unternehmen_id'] || $filters['status']): ?>
            <p class="text-muted">Versuchen Sie es mit anderen Filterkriterien.</p>
            <a href="<?= BASE_URL ?>/beurteilungen.php" class="btn btn-outline-primary">Filter zurücksetzen</a>
            <?php else: ?>
            <p class="text-muted">Erstellen Sie Ihre erste Gefährdungsbeurteilung.</p>
            <?php if (hasRole(ROLE_EDITOR)): ?>
            <a href="<?= BASE_URL ?>/beurteilung_neu.php" class="btn btn-primary">
                <i class="bi bi-plus-lg me-2"></i>Neue Beurteilung erstellen
            </a>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <!-- Liste der Beurteilungen -->
    <div class="table-responsive">
        <table class="table table-hover bg-white">
            <thead class="table-light">
                <tr>
                    <th>Titel</th>
                    <th>Unternehmen</th>
                    <th>Ersteller</th>
                    <th>Datum</th>
                    <th>Status</th>
                    <th class="text-center">Einträge</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($beurteilungen as $b): ?>
                <tr>
                    <td>
                        <a href="<?= BASE_URL ?>/beurteilung.php?id=<?= $b['id'] ?>" class="text-decoration-none">
                            <strong><?= sanitize($b['titel']) ?></strong>
                        </a>
                        <?php if ($b['arbeitsbereich_name']): ?>
                        <br><small class="text-muted"><?= sanitize($b['arbeitsbereich_name']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= sanitize($b['unternehmen_name']) ?></td>
                    <td><?= sanitize($b['ersteller_name']) ?></td>
                    <td>
                        <?= date('d.m.Y', strtotime($b['erstelldatum'])) ?>
                        <?php if ($b['ueberarbeitungsdatum']): ?>
                        <br><small class="text-muted">Überarbeitet: <?= date('d.m.Y', strtotime($b['ueberarbeitungsdatum'])) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-badge status-<?= $b['status'] ?>">
                            <?= ucfirst($b['status']) ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-secondary"><?= $b['taetigkeit_count'] ?> Tätigkeiten</span>
                        <span class="badge bg-primary"><?= $b['vorgang_count'] ?> Gefährdungen</span>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="<?= BASE_URL ?>/beurteilung.php?id=<?= $b['id'] ?>"
                               class="btn btn-outline-primary" title="Ansehen">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if (hasRole(ROLE_EDITOR)): ?>
                            <a href="<?= BASE_URL ?>/beurteilung_edit.php?id=<?= $b['id'] ?>"
                               class="btn btn-outline-secondary" title="Bearbeiten">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="<?= BASE_URL ?>/beurteilung_duplicate.php?id=<?= $b['id'] ?>"
                               class="btn btn-outline-info" title="Duplizieren">
                                <i class="bi bi-copy"></i>
                            </a>
                            <?php endif; ?>
                            <a href="<?= BASE_URL ?>/api/export.php?type=pdf&id=<?= $b['id'] ?>"
                               class="btn btn-outline-danger" title="PDF exportieren" target="_blank">
                                <i class="bi bi-file-pdf"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
