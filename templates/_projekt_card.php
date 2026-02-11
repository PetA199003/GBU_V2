<?php
/**
 * Projekt-Karte (Template für Wiederverwendung)
 * Erwartet: $p (Projekt-Array mit berechtigung)
 */
$canEdit = ($p['berechtigung'] ?? 'ansehen') === 'bearbeiten' || hasRole(ROLE_ADMIN);
$isEditorOrAdmin = hasRole(ROLE_EDITOR) || hasRole(ROLE_ADMIN);

// Zugewiesene Benutzer laden
$zugewieseneBenutzer = $db->fetchAll("
    SELECT b.id, b.vorname, b.nachname, bp.berechtigung
    FROM benutzer b
    JOIN benutzer_projekte bp ON b.id = bp.benutzer_id
    WHERE bp.projekt_id = ?
    ORDER BY b.nachname
", [$p['id']]);
?>
<div class="col-lg-4 col-md-6 mb-4 projekt-card" data-status="<?= $p['status'] ?>">
    <div class="card h-100 border-<?= $p['status'] === 'aktiv' ? 'success' : ($p['status'] === 'geplant' ? 'warning' : 'secondary') ?>">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h5 class="mb-0"><?= sanitize($p['name']) ?></h5>
                    <small class="text-muted">
                        <i class="bi bi-geo-alt me-1"></i><?= sanitize($p['location']) ?>
                    </small>
                </div>
                <?php if (!empty($p['firma_name'])): ?>
                <span class="badge bg-info"><?= sanitize($p['firma_name']) ?></span>
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

            <!-- Zugewiesene Benutzer -->
            <?php if (!empty($zugewieseneBenutzer)): ?>
            <div class="mt-3 pt-3 border-top">
                <small class="text-muted d-block mb-2">
                    <i class="bi bi-people me-1"></i>Zugewiesen (<?= count($zugewieseneBenutzer) ?>):
                </small>
                <div class="d-flex flex-wrap gap-1">
                    <?php foreach ($zugewieseneBenutzer as $bu): ?>
                    <span class="badge bg-<?= $bu['berechtigung'] === 'bearbeiten' ? 'primary' : 'secondary' ?>" title="<?= $bu['berechtigung'] === 'bearbeiten' ? 'Bearbeiter' : 'Betrachter' ?>">
                        <?= sanitize($bu['vorname'][0] . '. ' . $bu['nachname']) ?>
                        <?php if ($canEdit && $bu['id'] != $userId): ?>
                        <form method="POST" class="d-inline" style="margin:0">
                            <input type="hidden" name="action" value="remove_user">
                            <input type="hidden" name="projekt_id" value="<?= $p['id'] ?>">
                            <input type="hidden" name="benutzer_id" value="<?= $bu['id'] ?>">
                            <button type="submit" class="btn-close btn-close-white ms-1" style="font-size: 0.5rem;" onclick="return confirm('Benutzer entfernen?')"></button>
                        </form>
                        <?php endif; ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <div class="card-footer">
            <div class="d-flex gap-2">
                <a href="<?= BASE_URL ?>/projekt.php?id=<?= $p['id'] ?>" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-<?= $canEdit ? 'pencil' : 'eye' ?> me-2"></i>
                    <?= $canEdit ? 'Bearbeiten' : 'Ansehen' ?>
                </a>
                <?php if ($canEdit && $isEditorOrAdmin && !empty($kollegen)): ?>
                <button type="button" class="btn btn-outline-secondary" onclick="openZuweisungModal(<?= $p['id'] ?>)" title="Kollegen zuweisen">
                    <i class="bi bi-person-plus"></i>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
