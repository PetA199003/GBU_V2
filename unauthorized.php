<?php
/**
 * Keine Berechtigung
 */

require_once __DIR__ . '/config/config.php';

$pageTitle = 'Keine Berechtigung';
require_once __DIR__ . '/templates/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 text-center py-5">
            <i class="bi bi-shield-x text-danger" style="font-size: 5rem;"></i>
            <h1 class="h3 mt-4">Keine Berechtigung</h1>
            <p class="text-muted">Sie haben nicht die erforderlichen Rechte, um auf diese Seite zuzugreifen.</p>
            <a href="<?= BASE_URL ?>/index.php" class="btn btn-primary">
                <i class="bi bi-house me-2"></i>Zurück zum Dashboard
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
