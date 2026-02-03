<?php
/**
 * Projekt-Karte (Template für Wiederverwendung)
 * Erwartet: $p (Projekt-Array mit berechtigung)
 */
$canEdit = ($p['berechtigung'] ?? 'ansehen') === 'bearbeiten' || hasRole(ROLE_ADMIN);
?>
<div class="col-lg-4 col-md-6 mb-4 projekt-card" data-status="<?= $p['status'] ?>">
    <div class="card h-100 border-<?= $p['status'] === 'aktiv' ? 'success' : ($p['status'] === 'geplant' ? 'warning' : 'secondary') ?>">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0">
                    <?= sanitize($p['name']) ?>
                </h5>
                <small class="text-muted">
                    <i class="bi bi-geo-alt me-1"></i><?= sanitize($p['location']) ?>
                </small>
            </div>
            <div class="d-flex flex-column align-items-end gap-1">
                <span class="badge bg-<?= $p['status'] === 'aktiv' ? 'success' : ($p['status'] === 'geplant' ? 'warning text-dark' : 'secondary') ?>">
                    <?= ucfirst($p['status']) ?>
                </span>
                <?php if ($canEdit): ?>
                <span class="badge bg-primary" title="Bearbeiten erlaubt">
                    <i class="bi bi-pencil"></i>
                </span>
                <?php else: ?>
                <span class="badge bg-secondary" title="Nur Ansehen">
                    <i class="bi bi-eye"></i>
                </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-6">
                    <small class="text-muted d-block">Zeitraum</small>
                    <strong><?= date('d.m.Y', strtotime($p['zeitraum_von'])) ?></strong><br>
                    <small>bis <?= date('d.m.Y', strtotime($p['zeitraum_bis'])) ?></small>
                </div>
                <div class="col-6 text-end">
                    <small class="text-muted d-block">Art</small>
                    <span class="badge bg-<?= $p['indoor_outdoor'] === 'indoor' ? 'info' : ($p['indoor_outdoor'] === 'outdoor' ? 'success' : 'primary') ?>">
                        <i class="bi bi-<?= $p['indoor_outdoor'] === 'indoor' ? 'house' : ($p['indoor_outdoor'] === 'outdoor' ? 'sun' : 'circle-half') ?> me-1"></i>
                        <?= ucfirst($p['indoor_outdoor']) ?>
                    </span>
                </div>
            </div>

            <?php if ($p['aufbau_datum'] || $p['abbau_datum']): ?>
            <div class="row mb-3 small">
                <?php if ($p['aufbau_datum']): ?>
                <div class="col-6">
                    <i class="bi bi-box-arrow-down text-success me-1"></i>
                    <span class="text-muted">Aufbau:</span>
                    <?= date('d.m.', strtotime($p['aufbau_datum'])) ?>
                </div>
                <?php endif; ?>
                <?php if ($p['abbau_datum']): ?>
                <div class="col-6">
                    <i class="bi bi-box-arrow-up text-danger me-1"></i>
                    <span class="text-muted">Abbau:</span>
                    <?= date('d.m.', strtotime($p['abbau_datum'])) ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Gefährdungen Statistik -->
            <div class="d-flex justify-content-between align-items-center border-top pt-3">
                <div>
                    <i class="bi bi-exclamation-triangle text-warning me-1"></i>
                    <strong><?= $p['gefaehrdungen_count'] ?? 0 ?></strong> Gefährdungen
                </div>
            </div>
        </div>
        <div class="card-footer">
            <a href="<?= BASE_URL ?>/projekt.php?id=<?= $p['id'] ?>" class="btn btn-primary w-100">
                <i class="bi bi-<?= $canEdit ? 'pencil' : 'eye' ?> me-2"></i>
                <?= $canEdit ? 'Bearbeiten' : 'Ansehen' ?>
            </a>
        </div>
    </div>
</div>
