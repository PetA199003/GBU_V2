<?php
/**
 * Digitale Signatur für Sicherheitsunterweisung (iPad-optimiert)
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$unterweisungId = $_GET['id'] ?? null;

if (!$unterweisungId) {
    die('Unterweisung-ID erforderlich');
}

$db = Database::getInstance();

// Unterweisung laden
$unterweisung = $db->fetchOne("
    SELECT pu.*, p.name as projekt_name, p.location
    FROM projekt_unterweisungen pu
    JOIN projekte p ON pu.projekt_id = p.id
    WHERE pu.id = ?
", [$unterweisungId]);

if (!$unterweisung) {
    die('Unterweisung nicht gefunden');
}

// Signatur speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'sign') {
        $teilnehmerId = $_POST['teilnehmer_id'] ?? null;
        $signatur = $_POST['signatur'] ?? null;

        if ($teilnehmerId && $signatur) {
            $db->update('unterweisung_teilnehmer', [
                'unterschrift' => $signatur,
                'unterschrieben_am' => date('Y-m-d H:i:s')
            ], 'id = :id AND unterweisung_id = :uid', [
                'id' => $teilnehmerId,
                'uid' => $unterweisungId
            ]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Daten fehlen']);
        }
        exit;
    }
}

// Teilnehmer ohne Unterschrift laden
$teilnehmerOffen = $db->fetchAll("
    SELECT * FROM unterweisung_teilnehmer
    WHERE unterweisung_id = ? AND unterschrift IS NULL
    ORDER BY nachname, vorname
", [$unterweisungId]);

// Alle Teilnehmer für Status
$alleTeilnehmer = $db->fetchAll("
    SELECT * FROM unterweisung_teilnehmer
    WHERE unterweisung_id = ?
    ORDER BY nachname, vorname
", [$unterweisungId]);

$unterschriebenCount = count(array_filter($alleTeilnehmer, fn($t) => $t['unterschrift']));
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Digitale Unterschrift - <?= sanitize($unterweisung['projekt_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * {
            -webkit-tap-highlight-color: transparent;
        }
        body {
            background: linear-gradient(135deg, #1a1c2e 0%, #2d3748 100%);
            min-height: 100vh;
            color: white;
            overflow-x: hidden;
        }
        .header {
            background: rgba(255,255,255,0.1);
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .main-content {
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        .teilnehmer-card {
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .teilnehmer-card:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
        }
        .teilnehmer-card.active {
            background: #0d6efd;
        }
        .signature-area {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }
        #signatureCanvas {
            display: block;
            width: 100%;
            height: 200px;
            border: 2px dashed #ccc;
            border-radius: 8px;
            background: #fff;
            touch-action: none;
        }
        #signatureCanvas.signing {
            border-color: #0d6efd;
            border-style: solid;
        }
        .btn-sign {
            font-size: 1.2rem;
            padding: 15px 40px;
        }
        .progress-info {
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 10px 15px;
        }
        .all-done {
            text-align: center;
            padding: 60px 20px;
        }
        .all-done i {
            font-size: 80px;
            color: #28a745;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0"><?= sanitize($unterweisung['projekt_name']) ?></h5>
                <small class="text-white-50"><?= sanitize($unterweisung['location']) ?></small>
            </div>
            <div class="progress-info">
                <i class="bi bi-check-circle me-1"></i>
                <span id="signedCount"><?= $unterschriebenCount ?></span> / <?= count($alleTeilnehmer) ?> unterschrieben
            </div>
        </div>
    </div>

    <div class="main-content">
        <?php if (empty($teilnehmerOffen)): ?>
        <div class="all-done">
            <i class="bi bi-check-circle-fill"></i>
            <h2 class="mt-4">Alle haben unterschrieben!</h2>
            <p class="text-white-50">Die Sicherheitsunterweisung ist vollständig.</p>
            <a href="<?= BASE_URL ?>/unterweisung.php?projekt_id=<?= $unterweisung['projekt_id'] ?>" class="btn btn-primary mt-3">
                <i class="bi bi-arrow-left me-2"></i>Zurück zur Übersicht
            </a>
        </div>
        <?php else: ?>

        <h4 class="mb-3">Bitte wählen Sie Ihren Namen:</h4>

        <div id="teilnehmerList">
            <?php foreach ($teilnehmerOffen as $t): ?>
            <div class="teilnehmer-card" data-id="<?= $t['id'] ?>" data-name="<?= sanitize($t['vorname'] . ' ' . $t['nachname']) ?>">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?= sanitize($t['nachname']) ?>, <?= sanitize($t['vorname']) ?></strong>
                        <?php if ($t['firma']): ?>
                        <br><small class="text-white-50"><?= sanitize($t['firma']) ?></small>
                        <?php endif; ?>
                    </div>
                    <i class="bi bi-chevron-right"></i>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Signatur-Bereich (versteckt bis Auswahl) -->
        <div id="signatureSection" class="signature-area" style="display: none;">
            <h5 class="text-dark mb-3">
                Unterschrift für: <span id="selectedName" class="text-primary"></span>
            </h5>

            <canvas id="signatureCanvas"></canvas>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <button type="button" class="btn btn-outline-secondary" onclick="clearSignature()">
                    <i class="bi bi-eraser me-2"></i>Löschen
                </button>
                <button type="button" class="btn btn-outline-dark" onclick="cancelSignature()">
                    Abbrechen
                </button>
                <button type="button" class="btn btn-success btn-sign" onclick="saveSignature()">
                    <i class="bi bi-check-lg me-2"></i>Bestätigen
                </button>
            </div>

            <p class="text-muted small mt-3 mb-0">
                <i class="bi bi-info-circle me-1"></i>
                Mit Ihrer Unterschrift bestätigen Sie, dass Sie an der Sicherheitsunterweisung teilgenommen und den Inhalt verstanden haben.
            </p>
        </div>

        <?php endif; ?>
    </div>

    <script>
        let canvas, ctx;
        let isDrawing = false;
        let selectedTeilnehmerId = null;
        let lastX = 0, lastY = 0;

        document.addEventListener('DOMContentLoaded', function() {
            // Teilnehmer-Auswahl
            document.querySelectorAll('.teilnehmer-card').forEach(card => {
                card.addEventListener('click', function() {
                    selectTeilnehmer(this.dataset.id, this.dataset.name);
                });
            });
        });

        function initCanvas() {
            canvas = document.getElementById('signatureCanvas');
            if (!canvas) return;

            ctx = canvas.getContext('2d');

            // Canvas-Größe setzen (wichtig: nach display: block)
            const rect = canvas.getBoundingClientRect();
            const dpr = window.devicePixelRatio || 1;

            canvas.width = rect.width * dpr;
            canvas.height = rect.height * dpr;

            ctx.scale(dpr, dpr);
            ctx.strokeStyle = '#000';
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.fillStyle = '#fff';
            ctx.fillRect(0, 0, rect.width, rect.height);

            // Event-Listener entfernen falls vorhanden
            canvas.removeEventListener('touchstart', handleTouchStart);
            canvas.removeEventListener('touchmove', handleTouchMove);
            canvas.removeEventListener('touchend', handleTouchEnd);
            canvas.removeEventListener('mousedown', handleMouseDown);
            canvas.removeEventListener('mousemove', handleMouseMove);
            canvas.removeEventListener('mouseup', handleMouseUp);
            canvas.removeEventListener('mouseleave', handleMouseUp);

            // Touch-Events (für iPad/Pencil)
            canvas.addEventListener('touchstart', handleTouchStart, { passive: false });
            canvas.addEventListener('touchmove', handleTouchMove, { passive: false });
            canvas.addEventListener('touchend', handleTouchEnd, { passive: false });

            // Mouse-Events (für Desktop)
            canvas.addEventListener('mousedown', handleMouseDown);
            canvas.addEventListener('mousemove', handleMouseMove);
            canvas.addEventListener('mouseup', handleMouseUp);
            canvas.addEventListener('mouseleave', handleMouseUp);
        }

        function selectTeilnehmer(id, name) {
            selectedTeilnehmerId = id;
            document.getElementById('selectedName').textContent = name;
            document.getElementById('signatureSection').style.display = 'block';
            document.getElementById('teilnehmerList').style.display = 'none';

            // Canvas initialisieren NACHDEM es sichtbar ist
            setTimeout(function() {
                initCanvas();
            }, 50);

            // Scroll zur Signatur
            document.getElementById('signatureSection').scrollIntoView({ behavior: 'smooth' });
        }

        function cancelSignature() {
            selectedTeilnehmerId = null;
            document.getElementById('signatureSection').style.display = 'none';
            document.getElementById('teilnehmerList').style.display = 'block';
        }

        function getTouchPos(e) {
            const rect = canvas.getBoundingClientRect();
            const touch = e.touches[0] || e.changedTouches[0];
            return {
                x: touch.clientX - rect.left,
                y: touch.clientY - rect.top
            };
        }

        function getMousePos(e) {
            const rect = canvas.getBoundingClientRect();
            return {
                x: e.clientX - rect.left,
                y: e.clientY - rect.top
            };
        }

        function handleTouchStart(e) {
            e.preventDefault();
            isDrawing = true;
            canvas.classList.add('signing');
            const pos = getTouchPos(e);
            lastX = pos.x;
            lastY = pos.y;

            // Punkt zeichnen bei einzelnem Tap
            ctx.beginPath();
            ctx.arc(lastX, lastY, 1, 0, Math.PI * 2);
            ctx.fill();
        }

        function handleTouchMove(e) {
            if (!isDrawing) return;
            e.preventDefault();
            const pos = getTouchPos(e);

            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();

            lastX = pos.x;
            lastY = pos.y;
        }

        function handleTouchEnd(e) {
            e.preventDefault();
            isDrawing = false;
        }

        function handleMouseDown(e) {
            isDrawing = true;
            canvas.classList.add('signing');
            const pos = getMousePos(e);
            lastX = pos.x;
            lastY = pos.y;
        }

        function handleMouseMove(e) {
            if (!isDrawing) return;
            const pos = getMousePos(e);

            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();

            lastX = pos.x;
            lastY = pos.y;
        }

        function handleMouseUp() {
            isDrawing = false;
        }

        function clearSignature() {
            if (!canvas || !ctx) return;
            const rect = canvas.getBoundingClientRect();
            ctx.fillStyle = '#fff';
            ctx.fillRect(0, 0, rect.width, rect.height);
            ctx.strokeStyle = '#000';
            canvas.classList.remove('signing');
        }

        function saveSignature() {
            if (!selectedTeilnehmerId) {
                alert('Bitte wählen Sie zuerst einen Namen aus.');
                return;
            }

            // Prüfen ob Unterschrift vorhanden
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const data = imageData.data;
            let hasSignature = false;

            // Suche nach nicht-weissen Pixeln
            for (let i = 0; i < data.length; i += 4) {
                // Wenn Pixel nicht weiss ist (R, G, B < 255)
                if (data[i] < 250 || data[i+1] < 250 || data[i+2] < 250) {
                    hasSignature = true;
                    break;
                }
            }

            if (!hasSignature) {
                alert('Bitte unterschreiben Sie zuerst.');
                return;
            }

            const signatureData = canvas.toDataURL('image/png');

            // Speichern
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=sign&teilnehmer_id=${selectedTeilnehmerId}&signatur=${encodeURIComponent(signatureData)}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Karte entfernen
                    const card = document.querySelector(`.teilnehmer-card[data-id="${selectedTeilnehmerId}"]`);
                    if (card) card.remove();

                    // Counter aktualisieren
                    const countEl = document.getElementById('signedCount');
                    countEl.textContent = parseInt(countEl.textContent) + 1;

                    // Zurück zur Liste oder fertig
                    const remaining = document.querySelectorAll('.teilnehmer-card').length;
                    if (remaining === 0) {
                        location.reload();
                    } else {
                        cancelSignature();
                    }
                } else {
                    alert('Fehler beim Speichern: ' + (data.error || 'Unbekannt'));
                }
            })
            .catch(err => {
                alert('Verbindungsfehler');
                console.error(err);
            });
        }
    </script>
</body>
</html>
