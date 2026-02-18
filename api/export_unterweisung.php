<?php
/**
 * Export Sicherheitsunterweisung und Teilnehmerliste
 * Mit Zweisprachigkeit (DE/EN) basierend auf Projektsprache
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/lang.php';

requireLogin();

$unterweisungId = $_GET['id'] ?? null;
$type = $_GET['type'] ?? 'unterweisung'; // unterweisung oder teilnehmerliste

if (!$unterweisungId) {
    die('Unterweisung-ID erforderlich');
}

$db = Database::getInstance();

// Unterweisung laden (mit Firmen-Logo und Projektsprache)
$unterweisung = $db->fetchOne("
    SELECT pu.*, p.name as projekt_name, p.location, p.zeitraum_von, p.zeitraum_bis, p.firma_id, p.sprache, f.logo_url as firma_logo
    FROM projekt_unterweisungen pu
    JOIN projekte p ON pu.projekt_id = p.id
    LEFT JOIN firmen f ON p.firma_id = f.id
    WHERE pu.id = ?
", [$unterweisungId]);

if (!$unterweisung) {
    die('Unterweisung nicht gefunden');
}

// Projektsprache bestimmen
$lang = $unterweisung['sprache'] ?? 'de';

// Ausgewählte Bausteine laden (inkl. EN-Felder)
$bausteine = $db->fetchAll("
    SELECT ub.*, b.kategorie, b.kategorie_en, b.titel, b.titel_en, b.inhalt, b.inhalt_en, b.bild_url
    FROM unterweisung_bausteine ub
    JOIN unterweisungs_bausteine b ON ub.baustein_id = b.id
    WHERE ub.unterweisung_id = ?
    ORDER BY ub.sortierung
", [$unterweisungId]);

// Nach Kategorie gruppieren (sprachabhängig)
$bausteineNachKat = [];
foreach ($bausteine as $b) {
    $katName = tField($b, 'kategorie', $lang);
    if (!isset($bausteineNachKat[$katName])) {
        $bausteineNachKat[$katName] = [];
    }
    $bausteineNachKat[$katName][] = $b;
}

// Teilnehmer laden
$teilnehmer = $db->fetchAll("
    SELECT * FROM unterweisung_teilnehmer
    WHERE unterweisung_id = ?
    ORDER BY nachname, vorname
", [$unterweisungId]);

if ($type === 'teilnehmerliste') {
    generateTeilnehmerliste($unterweisung, $teilnehmer, $lang);
} else {
    generateUnterweisung($unterweisung, $bausteineNachKat, $lang);
}

function generateUnterweisung($unterweisung, $bausteineNachKat, $lang) {
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('uw_title', $lang) ?> - <?= htmlspecialchars($unterweisung['projekt_name']) ?></title>
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
        /* Footer auf jeder Druckseite (position: fixed wiederholt in Chrome) */
        .print-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8pt;
            color: #666;
            padding: 4px 10mm;
            border-top: 1px solid #ccc;
        }
        @page {
            margin: 10mm;
        }
        @media print {
            body { padding: 0; padding-bottom: 30px; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .no-print { display: none !important; }
            h2 { background: #FFC107 !important; -webkit-print-color-adjust: exact !important; }
            .kategorie-block {
                break-inside: avoid;
                page-break-inside: avoid;
            }
            .print-footer { display: block; }
        }
        @media screen {
            .print-footer { display: none; }
        }
        .print-btn { position: fixed; top: 10px; right: 10px; padding: 10px 20px; background: #0d6efd; color: white; border: none; cursor: pointer; border-radius: 4px; z-index: 1000; }
        .back-btn { position: fixed; top: 10px; right: 200px; padding: 10px 20px; background: #6c757d; color: white; border: none; cursor: pointer; border-radius: 4px; text-decoration: none; z-index: 1000; }
    </style>
</head>
<body>
    <a href="<?= BASE_URL ?>/unterweisung.php?projekt_id=<?= htmlspecialchars($unterweisung['projekt_id']) ?>" class="back-btn no-print"><?= t('btn_back_unterweisung', $lang) ?></a>
    <button class="print-btn no-print" onclick="window.print()"><?= t('btn_print', $lang) ?></button>

    <div class="print-footer">
        <?= htmlspecialchars($unterweisung['projekt_name']) ?>
    </div>

    <div id="content-wrapper">

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
        <h1><?= t('uw_title', $lang) ?></h1>
    </div>

    <div class="header-info">
        <table>
            <tr>
                <td class="label"><?= t('uw_event', $lang) ?>:</td>
                <td class="value"><?= htmlspecialchars($unterweisung['projekt_name']) ?></td>
            </tr>
            <tr>
                <td class="label"><?= t('uw_date_location', $lang) ?>:</td>
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
                    <?php
                    $bausteinTitel = tField($b, 'titel', $lang);
                    $bausteinInhalt = tField($b, 'inhalt', $lang);
                    ?>
                    <?php if (count($bausteine) > 1 || $bausteinTitel !== $kategorie): ?>
                    <strong><?= htmlspecialchars($bausteinTitel) ?></strong><br>
                    <?php endif; ?>
                    <?= nl2br(htmlspecialchars($bausteinInhalt)) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endforeach; ?>

    <p style="margin-top: 30px; font-size: 8pt; color: #666;">
        <?= t('uw_created_by', $lang) ?> <?= htmlspecialchars($unterweisung['durchgefuehrt_von'] ?? 'Unbekannt') ?> <?= t('uw_on_date', $lang) ?> <?= date('d.m.Y') ?>
    </p>

    </div>

</body>
</html>
<?php
}

function generateTeilnehmerliste($unterweisung, $teilnehmer, $lang) {
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('tl_title', $lang) ?> - <?= htmlspecialchars($unterweisung['projekt_name']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 10pt; line-height: 1.4; padding: 15mm; }
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        .page-header-content {
            flex: 1;
        }
        .page-header-logo {
            flex-shrink: 0;
            margin-left: 15px;
        }
        .page-header-logo img {
            max-width: 150px;
            max-height: 60px;
            object-fit: contain;
        }
        h1 {
            font-size: 13pt;
            text-align: center;
            background: #FFC107;
            padding: 8px 10px;
            margin-bottom: 10px;
        }
        .print-footer-tl {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8pt;
            color: #666;
            padding: 4px 10mm;
            border-top: 1px solid #ccc;
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
        table.teilnehmer tbody td { height: 40px; vertical-align: middle; padding: 4px 8px; line-height: 1.2; }
        table.teilnehmer td.unterschrift { padding-top: 2px; padding-bottom: 2px; }
        table.teilnehmer td.unterschrift img { max-height: 32px; max-width: 100%; display: block; }
        @media print {
            body { padding: 10mm; padding-bottom: 30px; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .no-print { display: none; }
            h1 { background: #FFC107 !important; -webkit-print-color-adjust: exact !important; }
            .print-footer-tl { display: block; }
        }
        @media screen {
            .print-footer-tl { display: none; }
        }
        .print-btn { position: fixed; top: 10px; right: 10px; padding: 10px 20px; background: #0d6efd; color: white; border: none; cursor: pointer; border-radius: 4px; }
        .back-btn { position: fixed; top: 10px; right: 200px; padding: 10px 20px; background: #6c757d; color: white; border: none; cursor: pointer; border-radius: 4px; text-decoration: none; }
    </style>
</head>
<body>
    <a href="<?= BASE_URL ?>/unterweisung.php?projekt_id=<?= htmlspecialchars($unterweisung['projekt_id']) ?>" class="back-btn no-print"><?= t('btn_back_unterweisung', $lang) ?></a>
    <button class="print-btn no-print" onclick="window.print()"><?= t('btn_print', $lang) ?></button>

    <?php
    // Firmen-Logo URL vorbereiten
    $firmaLogoUrl = $unterweisung['firma_logo'] ?? null;
    if ($firmaLogoUrl && !preg_match('/^https?:\/\//', $firmaLogoUrl)) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $firmaLogoUrl = $protocol . '://' . $host . $firmaLogoUrl;
    }
    ?>

    <div class="print-footer-tl">
        <?= htmlspecialchars($unterweisung['projekt_name']) ?> — <?= date('d.m.Y', strtotime($unterweisung['zeitraum_von'])) ?> <?= t('tl_to', $lang) ?> <?= date('d.m.Y', strtotime($unterweisung['zeitraum_bis'])) ?> / <?= htmlspecialchars($unterweisung['location']) ?>
    </div>

    <div class="page-header">
        <div class="page-header-content">
            <h1><?= t('tl_title', $lang) ?></h1>
        </div>
        <?php if ($firmaLogoUrl): ?>
        <div class="page-header-logo">
            <img src="<?= htmlspecialchars($firmaLogoUrl) ?>" alt="Firmenlogo" onerror="this.style.display='none'">
        </div>
        <?php endif; ?>
    </div>

    <div class="header-info">
        <div class="signature-box">
            <?php if (!empty($unterweisung['durchfuehrer_unterschrift'])): ?>
            <img src="<?= htmlspecialchars($unterweisung['durchfuehrer_unterschrift']) ?>" alt="<?= t('tl_signature', $lang) ?>">
            <?php endif; ?>
        </div>
        <div class="signature-label"><?= t('tl_signature', $lang) ?></div>

        <table>
            <tr>
                <td class="label"><?= t('tl_conducted_by', $lang) ?>:</td>
                <td class="value"><?= htmlspecialchars($unterweisung['durchgefuehrt_von'] ?? '') ?></td>
            </tr>
            <tr>
                <td class="label"><?= t('tl_on_date', $lang) ?>:</td>
                <td class="value">
                    <?= $unterweisung['durchgefuehrt_am'] ? date('d.m.Y', strtotime($unterweisung['durchgefuehrt_am'])) : date('d.m.Y') ?>
                    <?php if (!empty($unterweisung['durchfuehrer_unterschrieben_am'])): ?>
                    <span style="font-weight: normal; font-size: 8pt; color: #666;">
                        (<?= t('tl_signed', $lang) ?> <?= date('d.m.Y H:i', strtotime($unterweisung['durchfuehrer_unterschrieben_am'])) ?><?= $lang === 'de' ? ' Uhr' : '' ?>)
                    </span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td class="label"><?= t('tl_event', $lang) ?>:</td>
                <td class="value"><?= htmlspecialchars($unterweisung['projekt_name']) ?></td>
            </tr>
            <tr>
                <td class="label"><?= t('tl_location', $lang) ?>:</td>
                <td class="value"><?= htmlspecialchars($unterweisung['location']) ?></td>
            </tr>
        </table>
    </div>

    <p class="info-text">
        <?= t('tl_info_text', $lang) ?>
    </p>

    <p class="confirm-text">
        <?= t('tl_confirm_text', $lang) ?>
    </p>

    <table class="teilnehmer">
        <thead>
            <tr>
                <th style="width: 20%;"><?= t('tl_name', $lang) ?></th>
                <th style="width: 20%;"><?= t('tl_firstname', $lang) ?></th>
                <th style="width: 12%;"><?= t('tl_company', $lang) ?></th>
                <th style="width: 18%;"><?= t('tl_datetime', $lang) ?></th>
                <th style="width: 30%;"><?= t('tl_signature', $lang) ?></th>
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
            <?php foreach ($teilnehmer as $t_row): ?>
            <tr>
                <td><?= htmlspecialchars($t_row['nachname']) ?></td>
                <td><?= htmlspecialchars($t_row['vorname']) ?></td>
                <td style="font-size: 8pt;"><?= htmlspecialchars($t_row['firma'] ?? '') ?></td>
                <td style="font-size: 8pt;">
                    <?php if ($t_row['unterschrieben_am']): ?>
                    <?= date('d.m.Y', strtotime($t_row['unterschrieben_am'])) ?><br>
                    <span style="color: #666;"><?= date('H:i', strtotime($t_row['unterschrieben_am'])) ?><?php if (t('tl_time_suffix', $lang)): ?> <?= t('tl_time_suffix', $lang) ?><?php endif; ?></span>
                    <?php endif; ?>
                </td>
                <td class="unterschrift">
                    <?php if ($t_row['unterschrift']): ?>
                    <img src="<?= htmlspecialchars($t_row['unterschrift']) ?>" alt="<?= t('tl_signature', $lang) ?>" style="max-height: 35px; max-width: 100%;">
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>
<?php
}
