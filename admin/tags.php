<?php
/**
 * Tag-Verwaltung (Admin)
 * Tags für automatische Gefährdungszuweisung
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

requireRole(ROLE_ADMIN);

$db = Database::getInstance();

// Aktion verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
        case 'update':
            $data = [
                'name' => strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $_POST['name'])),
                'beschreibung' => $_POST['beschreibung'] ?: null,
                'farbe' => $_POST['farbe'] ?? '#6c757d',
                'sortierung' => $_POST['sortierung'] ?? 0
            ];

            if (empty($data['name'])) {
                setFlashMessage('error', 'Name ist Pflichtfeld.');
            } else {
                if ($action === 'create') {
                    try {
                        $db->insert('gefaehrdung_tags', $data);
                        setFlashMessage('success', 'Tag wurde erstellt.');
                    } catch (Exception $e) {
                        setFlashMessage('error', 'Tag existiert bereits.');
                    }
                } else {
                    $id = $_POST['id'];
                    $db->update('gefaehrdung_tags', $data, 'id = :id', ['id' => $id]);
                    setFlashMessage('success', 'Tag wurde aktualisiert.');
                }
            }
            break;

        case 'delete':
            $id = $_POST['id'];
            // Prüfen ob Tag verwendet wird
            $count = $db->fetchOne("SELECT COUNT(*) as cnt FROM gefaehrdung_bibliothek_tags WHERE tag_id = ?", [$id])['cnt'];
            $count += $db->fetchOne("SELECT COUNT(*) as cnt FROM projekt_tags WHERE tag_id = ?", [$id])['cnt'];

            if ($count > 0) {
                setFlashMessage('error', "Tag wird noch verwendet ($count Verknüpfungen) und kann nicht gelöscht werden.");
            } else {
                $db->delete('gefaehrdung_tags', 'id = ?', [$id]);
                setFlashMessage('success', 'Tag wurde gelöscht.');
            }
            break;
    }

    redirect('admin/tags.php');
}

// Tags laden mit Verwendungs-Statistiken
$tags = $db->fetchAll("
    SELECT gt.*,
           (SELECT COUNT(*) FROM gefaehrdung_bibliothek_tags WHERE tag_id = gt.id) as gefaehrdung_count,
           (SELECT COUNT(*) FROM projekt_tags WHERE tag_id = gt.id) as projekt_count
    FROM gefaehrdung_tags gt
    ORDER BY gt.sortierung, gt.name
");

$pageTitle = 'Tag-Verwaltung';
require_once __DIR__ . '/../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="bi bi-bookmark me-2"></i>Tag-Verwaltung
            </h1>
            <p class="text-muted mb-0">Tags für automatische Gefährdungszuweisung bei Projekten</p>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tagModal">
            <i class="bi bi-plus-lg me-2"></i>Neuer Tag
        </button>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Wie funktionieren Tags?</strong><br>
                <ul class="mb-0 mt-2">
                    <li>Tags werden Gefährdungen in der Bibliothek zugewiesen</li>
                    <li>Beim Erstellen eines Projekts können Tags gewählt werden (z.B. "Stapler", "Arbeiten in der Höhe")</li>
                    <li>Mit dem Zauberstab-Button werden automatisch alle Gefährdungen mit passenden Tags zum Projekt hinzugefügt</li>
                    <li>"standard" und "indoor/outdoor" werden automatisch basierend auf Projekteinstellungen gesetzt</li>
                </ul>
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px">Sort.</th>
                            <th>Tag</th>
                            <th>Beschreibung</th>
                            <th>Verwendung</th>
                            <th style="width: 150px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tags as $tag): ?>
                        <tr>
                            <td><?= $tag['sortierung'] ?></td>
                            <td>
                                <span class="badge" style="background-color: <?= $tag['farbe'] ?>; font-size: 1rem;">
                                    <?= sanitize($tag['name']) ?>
                                </span>
                            </td>
                            <td><?= sanitize($tag['beschreibung'] ?? '-') ?></td>
                            <td>
                                <span class="badge bg-primary"><?= $tag['gefaehrdung_count'] ?> Gefährdungen</span>
                                <span class="badge bg-secondary"><?= $tag['projekt_count'] ?> Projekte</span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary"
                                            onclick="editTag(<?= htmlspecialchars(json_encode($tag)) ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php if ($tag['gefaehrdung_count'] == 0 && $tag['projekt_count'] == 0): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Tag wirklich löschen?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $tag['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Tag -->
<div class="modal fade" id="tagModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" id="tag_action" value="create">
                <input type="hidden" name="id" id="tag_id" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="tagModalTitle">Neuer Tag</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name (ohne Leerzeichen) *</label>
                        <input type="text" class="form-control" name="name" id="t_name" required
                               pattern="[a-zA-Z0-9_]+" title="Nur Buchstaben, Zahlen und Unterstriche">
                        <small class="text-muted">z.B. stapler, arbeiten_hoehe, indoor</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Beschreibung</label>
                        <input type="text" class="form-control" name="beschreibung" id="t_beschreibung"
                               placeholder="z.B. Gilt für Arbeiten in der Höhe">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Farbe</label>
                            <input type="color" class="form-control form-control-color w-100" name="farbe" id="t_farbe" value="#6c757d">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Sortierung</label>
                            <input type="number" class="form-control" name="sortierung" id="t_sortierung" value="0">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Vorschau</label><br>
                        <span class="badge" id="tagPreview" style="font-size: 1rem;">tag_name</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editTag(data) {
    document.getElementById('tag_action').value = 'update';
    document.getElementById('tag_id').value = data.id;
    document.getElementById('tagModalTitle').textContent = 'Tag bearbeiten';
    document.getElementById('t_name').value = data.name;
    document.getElementById('t_beschreibung').value = data.beschreibung || '';
    document.getElementById('t_farbe').value = data.farbe || '#6c757d';
    document.getElementById('t_sortierung').value = data.sortierung || 0;
    updatePreview();
    new bootstrap.Modal(document.getElementById('tagModal')).show();
}

function updatePreview() {
    const name = document.getElementById('t_name').value || 'tag_name';
    const farbe = document.getElementById('t_farbe').value;
    const preview = document.getElementById('tagPreview');
    preview.textContent = name;
    preview.style.backgroundColor = farbe;
}

document.getElementById('t_name').addEventListener('input', updatePreview);
document.getElementById('t_farbe').addEventListener('input', updatePreview);

document.getElementById('tagModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('tag_action').value = 'create';
    document.getElementById('tag_id').value = '';
    document.getElementById('tagModalTitle').textContent = 'Neuer Tag';
    this.querySelector('form').reset();
    updatePreview();
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
