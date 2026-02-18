<?php
/**
 * Export Sicherheitsunterweisung und Teilnehmerliste
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$unterweisungId = $_GET['id'] ?? null;
$type = $_GET['type'] ?? 'unterweisung'; // unterweisung oder teilnehmerliste

if (!$unterweisungId) {
    die('Unterweisung-ID erforderlich');
}

$db = Database::getInstance();

// Unterweisung laden (mit Firmen-Logo)
$unterweisung = $db->fetchOne("
    SELECT pu.*, p.name as projekt_name, p.location, p.zeitraum_von, p.zeitraum_bis, p.firma_id, f.logo_url as firma_logo
    FROM projekt_unterweisungen pu
    JOIN projekte p ON pu.projekt_id = p.id
    LEFT JOIN firmen f ON p.firma_id = f.id
    WHERE pu.id = ?
", [$unterweisungId]);

if (!$unterweisung) {
    die('Unterweisung nicht gefunden');
}

// Ausgewählte Bausteine laden
$bausteine = $db->fetchAll("
    SELECT ub.*, b.kategorie, b.titel, b.inhalt, b.bild_url
    FROM unterweisung_bausteine ub
    JOIN unterweisungs_bausteine b ON ub.baustein_id = b.id
    WHERE ub.unterweisung_id = ?
    ORDER BY ub.sortierung
", [$unterweisungId]);

// Nach Kategorie gruppieren
$bausteineNachKat = [];
foreach ($bausteine as $b) {
    if (!isset($bausteineNachKat[$b['kategorie']])) {
        $bausteineNachKat[$b['kategorie']] = [];
    }
    $bausteineNachKat[$b['kategorie']][] = $b;
}

// Teilnehmer laden
$teilnehmer = $db->fetchAll("
    SELECT * FROM unterweisung_teilnehmer
    WHERE unterweisung_id = ?
    ORDER BY nachname, vorname
", [$unterweisungId]);

if ($type === 'teilnehmerliste') {
    generateTeilnehmerliste($unterweisung, $teilnehmer);
} else {
    generateUnterweisung($unterweisung, $bausteineNachKat);
}

function generateUnterweisung($unterweisung, $bausteineNachKat) {
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Sicherheitsunterweisung - <?= htmlspecialchars($unterweisung['projekt_name']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 10pt; line-height: 1.4; padding: 15mm; }
        h1 { font-size: 16pt; text-align: center; margin-bottom: 5px; }
        h2 {
            font-size: 11pt;
            background: #FFC107;
            padding: 5px 10px;
            margin: 15px 0 10px;
            border-bottom: 2px solid #000;
        }
        .header-info { margin: 15px 0; }
        .header-info table { width: 100%; border-collapse: collapse; }
        .header-info td { padding: 3px 10px; vertical-align: top; }
        .header-info .label { font-weight: normal; width: 150px; }
        .header-info .value { font-weight: bold; }
        .content-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .content-table th, .content-table td { border: 1px solid #000; padding: 5px 8px; vertical-align: top; }
        .content-table th { background: #f0f0f0; text-align: left; width: 120px; }
        .icon-cell { width: 100px; text-align: center; vertical-align: middle; }
        ul { margin: 0; padding-left: 20px; }
        li { margin-bottom: 3px; }
        .page-break { page-break-before: always; }
        .kategorie-block {
            break-inside: avoid;
            page-break-inside: avoid;
        }
        /* Wrapper-Tabelle für wiederholenden Footer auf jeder Druckseite */
        .print-wrapper { width: 100%; }
        .print-wrapper > thead { display: table-header-group; }
        .print-wrapper > tfoot { display: table-footer-group; }
        .print-wrapper > tbody { display: table-row-group; }
        .print-footer-content {
            text-align: center;
            font-size: 8pt;
            color: #666;
            padding-top: 8px;
            border-top: 1px solid #ccc;
        }
        /* Platzhalter damit Inhalt nicht vom Footer überdeckt wird */
        .footer-spacer { height: 15px; }
        @page {
            margin: 10mm;
        }
        @media print {
            body { padding: 0; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .no-print { display: none !important; }
            h2 { background: #FFC107 !important; -webkit-print-color-adjust: exact !important; }
            .kategorie-block {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }
        @media screen {
            .print-footer-content { display: none; }
            .footer-spacer { display: none; }
        }
        .print-btn { position: fixed; top: 10px; right: 10px; padding: 10px 20px; background: #0d6efd; color: white; border: none; cursor: pointer; border-radius: 4px; z-index: 1000; }
        .back-btn { position: fixed; top: 10px; right: 200px; padding: 10px 20px; background: #6c757d; color: white; border: none; cursor: pointer; border-radius: 4px; text-decoration: none; z-index: 1000; }
    </style>
</head>
<body>
    <a href="<?= BASE_URL ?>/unterweisung.php?projekt_id=<?= htmlspecialchars($unterweisung['projekt_id']) ?>" class="back-btn no-print">← Zurück zur Unterweisung</a>
    <button class="print-btn no-print" onclick="window.print()">Drucken / PDF</button>

    <table class="print-wrapper">
        <thead><tr><td>&nbsp;</td></tr></thead>
        <tfoot>
            <tr><td>
                <div class="print-footer-content">
                    Seite <span class="page-num"></span> — <?= htmlspecialchars($unterweisung['projekt_name']) ?>
                </div>
                <div class="footer-spacer"></div>
            </td></tr>
        </tfoot>
        <tbody><tr><td>

    <?php
    // Firmen-Logo URL vorbereiten
    $firmaLogoUrl = $unterweisung['firma_logo'] ?? null;
    if ($firmaLogoUrl && !preg_match('/^https?:\/\//', $firmaLogoUrl)) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $firmaLogoUrl = $protocol . '://' . $host . $firmaLogoUrl;
    }
    ?>

    <div style="position: relative;">
        <?php if ($firmaLogoUrl): ?>
        <img src="<?= htmlspecialchars($firmaLogoUrl) ?>" alt="Firmenlogo" style="position: absolute; top: 0; right: 0; max-width: 150px; max-height: 60px; object-fit: contain;" onerror="this.style.display='none'">
        <?php endif; ?>
        <h1>Regeln für Arbeiten bei Produktionen und Veranstaltungen</h1>
    </div>

    <div class="header-info">
        <table>
            <tr>
                <td class="label">Veranstaltung:</td>
                <td class="value"><?= htmlspecialchars($unterweisung['projekt_name']) ?></td>
            </tr>
            <tr>
                <td class="label">Datum und Ort:</td>
                <td class="value">
                    <?= date('d.m.', strtotime($unterweisung['zeitraum_von'])) ?> - <?= date('d.m.Y', strtotime($unterweisung['zeitraum_bis'])) ?>
                    / <?= htmlspecialchars($unterweisung['location']) ?>
                </td>
            </tr>
        </table>
    </div>

    <?php foreach ($bausteineNachKat as $kategorie => $bausteine): ?>
    <div class="kategorie-block">
        <h2><?= htmlspecialchars($kategorie) ?></h2>
        <table class="content-table">
            <?php foreach ($bausteine as $b):
                // Bild-URL korrigieren (relative zu absolute URL)
                $bildUrl = $b['bild_url'];
                if ($bildUrl && !preg_match('/^https?:\/\//', $bildUrl)) {
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'];
                    $bildUrl = $protocol . '://' . $host . $bildUrl;
                }
            ?>
            <tr>
                <td class="icon-cell">
                    <?php if ($bildUrl): ?>
                    <img src="<?= htmlspecialchars($bildUrl) ?>" style="max-width: 80px; max-height: 80px;" onerror="this.style.display='none'">
                    <?php else: ?>
                    &nbsp;
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (count($bausteine) > 1 || $b['titel'] !== $kategorie): ?>
                    <strong><?= htmlspecialchars($b['titel']) ?></strong><br>
                    <?php endif; ?>
                    <?= nl2br(htmlspecialchars($b['inhalt'])) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endforeach; ?>

    <p style="margin-top: 30px; font-size: 8pt; color: #666;">
        Erstellt von <?= htmlspecialchars($unterweisung['durchgefuehrt_von'] ?? 'Unbekannt') ?> am <?= date('d.m.Y') ?>
    </p>

        </td></tr></tbody>
    </table>

    <script>
    // Seitenzahlen im Footer: Gesamtanzahl berechnen und einfügen
    window.addEventListener('beforeprint', function() {
        var contentHeight = document.querySelector('.print-wrapper').offsetHeight;
        // A4: ca. 1045px bei 96dpi mit 10mm Rand
        var pageHeight = 1045;
        var totalPages = Math.ceil(contentHeight / pageHeight) || 1;
        document.querySelectorAll('.page-num').forEach(function(el) {
            el.textContent = '1 / ' + totalPages;
        });
    });
    </script>
</body>
</html>
<?php
}

function generateTeilnehmerliste($unterweisung, $teilnehmer) {
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Teilnehmerliste - <?= htmlspecialchars($unterweisung['projekt_name']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 10pt; line-height: 1.4; padding: 15mm; }
        h1 {
            font-size: 14pt;
            text-align: center;
            background: #FFC107;
            padding: 10px;
            margin-bottom: 5px;
        }
        .subtitle {
            text-align: center;
            font-size: 9pt;
            margin-bottom: 15px;
            padding: 5px;
            background: #FFC107;
        }
        .header-info { margin: 15px 0; }
        .header-info table { width: 100%; }
        .header-info td { padding: 3px 0; }
        .header-info .label { width: 180px; }
        .header-info .value { font-weight: bold; }
        .signature-box { float: right; width: 200px; border-bottom: 1px solid #000; height: 40px; margin-top: 10px; text-align: center; }
        .signature-box img { max-height: 38px; max-width: 100%; }
        .signature-label { float: right; width: 200px; text-align: center; font-size: 8pt; clear: both; }
        .info-text { margin: 15px 0; font-style: italic; }
        .confirm-text { margin: 20px 0; font-weight: bold; text-align: center; }
        table.teilnehmer { width: 100%; border-collapse: collapse; margin-top: 15px; }
        table.teilnehmer th, table.teilnehmer td { border: 1px solid #000; padding: 8px; }
        table.teilnehmer th { background: #f0f0f0; text-align: left; }
        table.teilnehmer td.unterschrift { height: 35px; }
        table.teilnehmer td.unterschrift img { max-height: 30px; }
        .page-number { text-align: center; font-size: 8pt; margin-top: 20px; }
        @media print {
            body { padding: 10mm; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .no-print { display: none; }
            h1, .subtitle { background: #FFC107 !important; -webkit-print-color-adjust: exact !important; }
        }
        .print-btn { position: fixed; top: 10px; right: 10px; padding: 10px 20px; background: #0d6efd; color: white; border: none; cursor: pointer; border-radius: 4px; }
        .back-btn { position: fixed; top: 10px; right: 200px; padding: 10px 20px; background: #6c757d; color: white; border: none; cursor: pointer; border-radius: 4px; text-decoration: none; }
    </style>
</head>
<body>
    <a href="<?= BASE_URL ?>/unterweisung.php?projekt_id=<?= htmlspecialchars($unterweisung['projekt_id']) ?>" class="back-btn no-print">← Zurück zur Unterweisung</a>
    <button class="print-btn no-print" onclick="window.print()">Drucken / PDF</button>

    <?php
    // Firmen-Logo URL vorbereiten
    $firmaLogoUrl = $unterweisung['firma_logo'] ?? null;
    if ($firmaLogoUrl && !preg_match('/^https?:\/\//', $firmaLogoUrl)) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $firmaLogoUrl = $protocol . '://' . $host . $firmaLogoUrl;
    }
    ?>

    <div style="position: relative;">
        <?php if ($firmaLogoUrl): ?>
        <img src="<?= htmlspecialchars($firmaLogoUrl) ?>" alt="Firmenlogo" style="position: absolute; top: 0; right: 0; max-width: 150px; max-height: 60px; object-fit: contain;" onerror="this.style.display='none'">
        <?php endif; ?>
        <h1>Bestätigung der Unterweisung</h1>
        <div class="subtitle">
            nach § 4 der Unfallverhütungsvorschrift<br>
            "Grundsätze der Prävention" DGUV Vorschrift 1 / VUV
        </div>
    </div>

    <div class="header-info">
        <div class="signature-box">
            <?php if (!empty($unterweisung['durchfuehrer_unterschrift'])): ?>
            <img src="<?= htmlspecialchars($unterweisung['durchfuehrer_unterschrift']) ?>" alt="Unterschrift Durchführer">
            <?php endif; ?>
        </div>
        <div class="signature-label">Unterschrift</div>

        <table>
            <tr>
                <td class="label">Unterweisung durchgeführt von:</td>
                <td class="value"><?= htmlspecialchars($unterweisung['durchgefuehrt_von'] ?? '') ?></td>
            </tr>
            <tr>
                <td class="label">am:</td>
                <td class="value">
                    <?= $unterweisung['durchgefuehrt_am'] ? date('d.m.Y', strtotime($unterweisung['durchgefuehrt_am'])) : date('d.m.Y') ?>
                    <?php if (!empty($unterweisung['durchfuehrer_unterschrieben_am'])): ?>
                    <span style="font-weight: normal; font-size: 8pt; color: #666;">
                        (unterschrieben <?= date('d.m.Y H:i', strtotime($unterweisung['durchfuehrer_unterschrieben_am'])) ?> Uhr)
                    </span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td class="label">Veranstaltung:</td>
                <td class="value"><?= htmlspecialchars($unterweisung['projekt_name']) ?></td>
            </tr>
            <tr>
                <td class="label">Ort:</td>
                <td class="value"><?= htmlspecialchars($unterweisung['location']) ?></td>
            </tr>
        </table>
    </div>

    <p class="info-text">
        Die Unterweisung wurde basierend auf der erstellten Gefährdungsbeurteilung und der aktuellen Gesetzeslage durchgeführt.
    </p>

    <p class="confirm-text">
        Mit meiner Unterschrift bestätige ich, dass ich an der Unterweisung teilgenommen und den Inhalt verstanden habe.
    </p>

    <table class="teilnehmer">
        <thead>
            <tr>
                <th style="width: 22%;">Name</th>
                <th style="width: 18%;">Vorname</th>
                <th style="width: 12%;">Firma</th>
                <th style="width: 18%;">Datum / Uhrzeit</th>
                <th style="width: 30%;">Unterschrift</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($teilnehmer)): ?>
            <?php for ($i = 0; $i < 15; $i++): ?>
            <tr>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td class="unterschrift">&nbsp;</td>
            </tr>
            <?php endfor; ?>
            <?php else: ?>
            <?php foreach ($teilnehmer as $t): ?>
            <tr>
                <td><?= htmlspecialchars($t['nachname']) ?></td>
                <td><?= htmlspecialchars($t['vorname']) ?></td>
                <td style="font-size: 8pt;"><?= htmlspecialchars($t['firma'] ?? '') ?></td>
                <td style="font-size: 9pt;">
                    <?php if ($t['unterschrieben_am']): ?>
                    <?= date('d.m.Y', strtotime($t['unterschrieben_am'])) ?><br>
                    <span style="color: #666;"><?= date('H:i', strtotime($t['unterschrieben_am'])) ?> Uhr</span>
                    <?php endif; ?>
                </td>
                <td class="unterschrift">
                    <?php if ($t['unterschrift']): ?>
                    <img src="<?= htmlspecialchars($t['unterschrift']) ?>" alt="Unterschrift" style="max-height: 35px; max-width: 100%;">
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <p class="page-number">Seite 1 von 1</p>
</body>
</html>
<?php
}
