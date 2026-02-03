<?php
/**
 * Neue Gefährdungsbeurteilung erstellen
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Gefaehrdungsbeurteilung.php';

requireRole(ROLE_EDITOR);

$gbClass = new Gefaehrdungsbeurteilung();
$db = Database::getInstance();

// Unternehmen laden
$unternehmen = $db->fetchAll("SELECT * FROM unternehmen ORDER BY name");

if (empty($unternehmen)) {
    setFlashMessage('warning', 'Bitte erstellen Sie zuerst ein Unternehmen.');
    redirect('admin/unternehmen.php');
}

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    if (empty($_POST['titel'])) {
        $errors[] = 'Bitte geben Sie einen Titel ein.';
    }

    if (empty($_POST['unternehmen_id'])) {
        $errors[] = 'Bitte wählen Sie ein Unternehmen aus.';
    }

    if (empty($_POST['ersteller_name'])) {
        $errors[] = 'Bitte geben Sie den Namen des Erstellers ein.';
    }

    if (empty($errors)) {
        $gbId = $gbClass->create([
            'unternehmen_id' => $_POST['unternehmen_id'],
            'arbeitsbereich_id' => $_POST['arbeitsbereich_id'] ?: null,
            'titel' => $_POST['titel'],
            'ersteller_name' => $_POST['ersteller_name'],
            'erstelldatum' => $_POST['erstelldatum'] ?: date('Y-m-d'),
            'status' => 'entwurf',
            'bemerkungen' => $_POST['bemerkungen'] ?: null
        ]);

        setFlashMessage('success', 'Gefährdungsbeurteilung wurde erstellt.');
        redirect('beurteilung_edit.php?id=' . $gbId);
    }
}

$pageTitle = 'Neue Gefährdungsbeurteilung';
require_once __DIR__ . '/templates/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="bi bi-file-earmark-plus me-2"></i>Neue Gefährdungsbeurteilung
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/beurteilungen.php">Beurteilungen</a></li>
                            <li class="breadcrumb-item active">Neue Beurteilung</li>
                        </ol>
                    </nav>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                    <li><?= sanitize($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="titel" class="form-label">Titel der Gefährdungsbeurteilung *</label>
                            <input type="text"
                                   class="form-control"
                                   id="titel"
                                   name="titel"
                                   required
                                   placeholder="z.B. Büro & Bildschirmarbeitsplatz"
                                   value="<?= sanitize($_POST['titel'] ?? '') ?>">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="unternehmen_id" class="form-label">Unternehmen *</label>
                                <select class="form-select" id="unternehmen_id" name="unternehmen_id" required>
                                    <option value="">Bitte wählen...</option>
                                    <?php foreach ($unternehmen as $u): ?>
                                    <option value="<?= $u['id'] ?>"
                                            <?= ($_POST['unternehmen_id'] ?? '') == $u['id'] ? 'selected' : '' ?>>
                                        <?= sanitize($u['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="arbeitsbereich_id" class="form-label">Arbeitsbereich</label>
                                <select class="form-select" id="arbeitsbereich_id" name="arbeitsbereich_id">
                                    <option value="">Kein spezifischer Bereich</option>
                                </select>
                                <div class="form-text">Wird nach Auswahl des Unternehmens geladen</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="ersteller_name" class="form-label">Ersteller (Name) *</label>
                                <input type="text"
                                       class="form-control"
                                       id="ersteller_name"
                                       name="ersteller_name"
                                       required
                                       value="<?= sanitize($_POST['ersteller_name'] ?? getCurrentUser()['voller_name']) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="erstelldatum" class="form-label">Erstelldatum</label>
                                <input type="date"
                                       class="form-control"
                                       id="erstelldatum"
                                       name="erstelldatum"
                                       value="<?= $_POST['erstelldatum'] ?? date('Y-m-d') ?>">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="bemerkungen" class="form-label">Bemerkungen</label>
                            <textarea class="form-control"
                                      id="bemerkungen"
                                      name="bemerkungen"
                                      rows="3"
                                      placeholder="Optionale Anmerkungen..."><?= sanitize($_POST['bemerkungen'] ?? '') ?></textarea>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-2"></i>Erstellen und Gefährdungen hinzufügen
                            </button>
                            <a href="<?= BASE_URL ?>/beurteilungen.php" class="btn btn-outline-secondary">
                                Abbrechen
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Arbeitsbereiche nach Unternehmensauswahl laden
document.getElementById('unternehmen_id').addEventListener('change', function() {
    const unternehmensId = this.value;
    const arbeitsbereichSelect = document.getElementById('arbeitsbereich_id');

    arbeitsbereichSelect.innerHTML = '<option value="">Kein spezifischer Bereich</option>';

    if (unternehmensId) {
        fetch('<?= BASE_URL ?>/api/arbeitsbereiche.php?unternehmen_id=' + unternehmensId)
            .then(response => response.json())
            .then(data => {
                data.forEach(ab => {
                    const option = document.createElement('option');
                    option.value = ab.id;
                    option.textContent = ab.name;
                    arbeitsbereichSelect.appendChild(option);
                });
            });
    }
});
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
