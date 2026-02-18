<?php
/**
 * Export-Funktionen für Projekte (PDF/Excel)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/lang.php';

requireLogin();

$type = $_GET['type'] ?? 'projekt';
$format = $_GET['format'] ?? 'pdf';
$id = $_GET['id'] ?? null;

if (!$id) {
    die('Projekt-ID erforderlich');
}

$db = Database::getInstance();

// Projekt laden (inkl. Firmenname)
$projekt = $db->fetchOne("
    SELECT p.*, f.name as firma_name
    FROM projekte p
    LEFT JOIN firmen f ON p.firma_id = f.id
    WHERE p.id = ?
", [$id]);

if (!$projekt) {
    die('Projekt nicht gefunden');
}

// Sprache aus Projekt-Einstellung
$lang = $projekt['sprache'] ?? 'de';

// Berechtigung prüfen
$userId = $_SESSION['user_id'];
$isAdmin = hasRole(ROLE_ADMIN);

if (!$isAdmin) {
    $access = $db->fetchOne(
        "SELECT berechtigung FROM benutzer_projekte WHERE benutzer_id = ? AND projekt_id = ?",
        [$userId, $id]
    );
    if (!$access) {
        die('Keine Berechtigung für dieses Projekt');
    }
}

// Gefährdungen laden
$gefaehrdungen = $db->fetchAll("
    SELECT pg.*,
           pg.titel_en, pg.beschreibung_en,
           pg.massnahme_s_en, pg.massnahme_t_en, pg.massnahme_o_en, pg.massnahme_p_en,
           pg.verantwortlich_en,
           ga.name as gefaehrdungsart_name, ga.nummer as gefaehrdungsart_nummer,
           ga.name_en as gefaehrdungsart_name_en,
           ak.name as kategorie_name, ak.nummer as kategorie_nummer,
           ak.name_en as kategorie_name_en,
           auk.name as unterkategorie_name, auk.nummer as unterkategorie_nummer,
           auk.name_en as unterkategorie_name_en
    FROM projekt_gefaehrdungen pg
    LEFT JOIN gefaehrdungsarten ga ON pg.gefaehrdungsart_id = ga.id
    LEFT JOIN arbeits_kategorien ak ON pg.kategorie_id = ak.id
    LEFT JOIN arbeits_unterkategorien auk ON pg.unterkategorie_id = auk.id
    WHERE pg.projekt_id = ?
    ORDER BY ak.nummer, auk.nummer, pg.titel
", [$id]);

// Nach Kategorien gruppieren
$gefNachKategorie = [];
foreach ($gefaehrdungen as $gef) {
    $katKey = $gef['kategorie_id'] ? $gef['kategorie_id'] : 0;
    $katName = $gef['kategorie_name'] ? $gef['kategorie_nummer'] . '. ' . tField($gef, 'kategorie_name', $lang) : t('without_category', $lang);
    if (!isset($gefNachKategorie[$katKey])) {
        $gefNachKategorie[$katKey] = [
            'name' => $katName,
            'nummer' => $gef['kategorie_nummer'] ?? 999,
            'items' => []
        ];
    }
    $gefNachKategorie[$katKey]['items'][] = $gef;
}
uasort($gefNachKategorie, fn($a, $b) => ($a['nummer'] ?? 999) <=> ($b['nummer'] ?? 999));

// Ersteller laden
$ersteller = $db->fetchOne("SELECT vorname, nachname FROM benutzer WHERE id = ?", [$projekt['erstellt_von']]);
$erstellerName = $ersteller ? $ersteller['vorname'] . ' ' . $ersteller['nachname'] : 'Unbekannt';

// Platzhalter-Variablen vorbereiten
$placeholderVars = [
    'unternehmen' => $projekt['firma_name'] ?? '',
    'projekt'     => $projekt['name'] ?? '',
    'ort'         => $projekt['location'] ?? '',
    'datum_von'   => $projekt['zeitraum_von'] ? date('d.m.Y', strtotime($projekt['zeitraum_von'])) : '',
    'datum_bis'   => $projekt['zeitraum_bis'] ? date('d.m.Y', strtotime($projekt['zeitraum_bis'])) : '',
    'zeitraum'    => ($projekt['zeitraum_von'] && $projekt['zeitraum_bis'])
        ? date('d.m.', strtotime($projekt['zeitraum_von'])) . ' - ' . date('d.m.Y', strtotime($projekt['zeitraum_bis']))
        : '',
    'unterweiser' => $erstellerName,
];

if ($format === 'excel' || $format === 'csv') {
    generateExcel($projekt, $gefaehrdungen, $gefNachKategorie, $erstellerName, $lang, $placeholderVars);
} else {
    generatePDFView($projekt, $gefaehrdungen, $gefNachKategorie, $erstellerName, $lang, $placeholderVars);
}

function generatePDFView($projekt, $gefaehrdungen, $gefNachKategorie, $erstellerName, $lang, $vars) {
    ?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('export_title', $lang) ?> - <?= htmlspecialchars($projekt['name']) ?></title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 8pt;
            line-height: 1.3;
            padding: 10mm;
        }
        h1 { font-size: 16pt; margin-bottom: 10px; }
        h2 { font-size: 10pt; margin: 15px 0 8px; background: #0d6efd; color: white; padding: 5px 10px; page-break-after: avoid; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            page-break-inside: auto;
            font-size: 7.5pt;
        }
        tr { page-break-inside: avoid; page-break-after: auto; }
        th, td {
            border: 1px solid #000;
            padding: 4px 6px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #d9d9d9;
            font-weight: bold;
            font-size: 7.5pt;
            text-align: center;
        }
        .header-table { margin-bottom: 15px; border: none; }
        .header-table td { border: none; padding: 2px 8px; font-size: 9pt; }
        .risk-low { background: #92D050 !important; }
        .risk-medium { background: #FFFF00 !important; }
        .risk-high { background: #FFC000 !important; }
        .risk-very-high { background: #FF0000 !important; color: white !important; }
        .stop-badge { display: inline-block; padding: 1px 5px; border-radius: 2px; font-size: 7pt; font-weight: bold; margin-right: 2px; }
        .stop-s { background: #dc3545; color: white; }
        .stop-t { background: #ffc107; color: black; }
        .stop-o { background: #0dcaf0; color: black; }
        .stop-p { background: #198754; color: white; }
        .text-center { text-align: center; }
        .small { font-size: 7pt; }
        .legend { margin-bottom: 15px; padding: 10px; background: #f5f5f5; border: 1px solid #ccc; font-size: 8pt; }
        .legend-row { margin-bottom: 3px; }
        .legend-item { display: inline-block; margin-right: 15px; }
        .massnahme-item { margin-bottom: 3px; }
        .massnahme-label { font-weight: bold; }
        @media print {
            @page {
                size: A4 landscape;
                margin: 8mm;
            }
            body { padding: 0; }
            .no-print { display: none !important; }
            h2 { background: #0d6efd !important; color: white !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            th { background: #d9d9d9 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .risk-low, .risk-medium, .risk-high, .risk-very-high { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .stop-s, .stop-t, .stop-o, .stop-p { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
        .print-btn {
            position: fixed;
            top: 10px;
            right: 10px;
            padding: 10px 20px;
            background: #0d6efd;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 14px;
            border-radius: 4px;
            z-index: 1000;
        }
        .print-btn:hover { background: #0b5ed7; }
        .back-btn {
            position: fixed;
            top: 10px;
            right: 200px;
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            border: none;
            cursor: pointer;
            font-size: 14px;
            border-radius: 4px;
            text-decoration: none;
            z-index: 1000;
        }
        .back-btn:hover { background: #5c636a; }
        .nummer { font-weight: bold; white-space: nowrap; }
    </style>
</head>
<body>
    <a href="<?= BASE_URL ?>/projekt.php?id=<?= $projekt['id'] ?>" class="back-btn no-print"><?= t('btn_back_project', $lang) ?></a>
    <button class="print-btn no-print" onclick="window.print()"><?= t('btn_print', $lang) ?></button>

    <h1><?= t('export_title', $lang) ?> - <?= htmlspecialchars($projekt['name']) ?></h1>

    <table class="header-table">
        <tr>
            <td style="width: 50%;">
                <strong><?= t('project', $lang) ?>:</strong> <?= htmlspecialchars($projekt['name']) ?><br>
                <strong><?= t('location', $lang) ?>:</strong> <?= htmlspecialchars($projekt['location']) ?>
            </td>
            <td style="width: 50%;">
                <strong><?= t('period', $lang) ?>:</strong> <?= date('d.m.Y', strtotime($projekt['zeitraum_von'])) ?> - <?= date('d.m.Y', strtotime($projekt['zeitraum_bis'])) ?><br>
                <?php if ($projekt['aufbau_datum']): ?>
                <strong><?= t('setup', $lang) ?>:</strong> <?= date('d.m.Y', strtotime($projekt['aufbau_datum'])) ?><br>
                <?php endif; ?>
                <strong><?= t('creator', $lang) ?>:</strong> <?= htmlspecialchars($erstellerName) ?>
            </td>
        </tr>
    </table>

    <div class="legend">
        <strong><?= t('legend_severity', $lang) ?>:</strong><br>
        <span class="legend-item"><?= t('severity_1', $lang) ?></span><br>
        <span class="legend-item"><?= t('severity_2', $lang) ?></span><br>
        <span class="legend-item"><?= t('severity_3', $lang) ?></span><br><br>
        <strong><?= t('legend_probability', $lang) ?>:</strong><br>
        <span class="legend-item"><?= t('probability_1', $lang) ?></span><br>
        <span class="legend-item"><?= t('probability_2', $lang) ?></span><br>
        <span class="legend-item"><?= t('probability_3', $lang) ?></span><br><br>
        <strong><?= t('risk_formula', $lang) ?></strong> |
        <span class="legend-item"><span class="stop-s">S</span> <?= t('stop_s_label', $lang) ?></span>
        <span class="legend-item"><span class="stop-t">T</span> <?= t('stop_t_label', $lang) ?></span>
        <span class="legend-item"><span class="stop-o">O</span> <?= t('stop_o_label', $lang) ?></span>
        <span class="legend-item"><span class="stop-p">P</span> <?= t('stop_p_label', $lang) ?></span>
    </div>

    <?php if (empty($gefaehrdungen)): ?>
    <p><em><?= t('no_hazards', $lang) ?></em></p>
    <?php else: ?>

    <?php foreach ($gefNachKategorie as $katId => $katData): ?>
    <h2><?= htmlspecialchars($katData['name']) ?></h2>

    <table>
        <thead>
            <tr>
                <th style="width: 6%;"><?= t('col_nr', $lang) ?></th>
                <th style="width: 18%;"><?= t('col_hazard', $lang) ?></th>
                <th style="width: 12%;"><?= t('col_hazard_type', $lang) ?></th>
                <th style="width: 4%;" class="text-center">S</th>
                <th style="width: 4%;" class="text-center">W</th>
                <th style="width: 4%;" class="text-center">R</th>
                <th style="width: 8%;" class="text-center">STOP</th>
                <th style="width: 22%;"><?= t('col_measures', $lang) ?></th>
                <th style="width: 4%;" class="text-center">S'</th>
                <th style="width: 4%;" class="text-center">W'</th>
                <th style="width: 4%;" class="text-center">R'</th>
                <th style="width: 10%;"><?= t('col_responsible', $lang) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $lfdNr = 0;
            foreach ($katData['items'] as $gef):
                $lfdNr++;
                $nummerPrefix = $katData['nummer'] != 999 ? $katData['nummer'] . '.' : '';
                if ($gef['unterkategorie_nummer']) {
                    $nummerPrefix .= $gef['unterkategorie_nummer'] . '.';
                }
                $vollNummer = $nummerPrefix . $lfdNr;

                $rScore = $gef['risikobewertung'] ?? 0;
                $rClass = $rScore <= 2 ? 'risk-low' : ($rScore <= 4 ? 'risk-medium' : ($rScore <= 8 ? 'risk-high' : 'risk-very-high'));

                $rScoreNach = $gef['risikobewertung_nach'] ?? 0;
                $rClassNach = $rScoreNach ? ($rScoreNach <= 2 ? 'risk-low' : ($rScoreNach <= 4 ? 'risk-medium' : ($rScoreNach <= 8 ? 'risk-high' : 'risk-very-high'))) : '';
            ?>
            <tr>
                <td class="nummer"><?= $vollNummer ?></td>
                <td>
                    <strong><?= htmlspecialchars(replacePlaceholders(tField($gef, 'titel', $lang), $vars)) ?></strong>
                    <?php if ($gef['unterkategorie_name']): ?>
                    <br><span class="small"><?= $katData['nummer'] ?>.<?= $gef['unterkategorie_nummer'] ?> <?= htmlspecialchars(tField($gef, 'unterkategorie_name', $lang)) ?></span>
                    <?php endif; ?>
                </td>
                <td class="small">
                    <?php if ($gef['gefaehrdungsart_name']): ?>
                    <?= $gef['gefaehrdungsart_nummer'] ?>. <?= htmlspecialchars(tField($gef, 'gefaehrdungsart_name', $lang)) ?>
                    <?php endif; ?>
                </td>
                <td class="text-center"><?= $gef['schadenschwere'] ?></td>
                <td class="text-center"><?= $gef['wahrscheinlichkeit'] ?></td>
                <td class="text-center <?= $rClass ?>"><?= $rScore ?></td>
                <td class="text-center">
                    <?php if ($gef['stop_s']): ?><span class="stop-badge stop-s">S</span><?php endif; ?>
                    <?php if ($gef['stop_t']): ?><span class="stop-badge stop-t">T</span><?php endif; ?>
                    <?php if ($gef['stop_o']): ?><span class="stop-badge stop-o">O</span><?php endif; ?>
                    <?php if ($gef['stop_p']): ?><span class="stop-badge stop-p">P</span><?php endif; ?>
                </td>
                <td class="small">
                    <?php
                    $hasMassnahmen = false;
                    if (!empty($gef['massnahme_s']) || ($lang === 'en' && !empty($gef['massnahme_s_en']))):
                        $hasMassnahmen = true;
                    ?>
                    <div class="massnahme-item"><span class="massnahme-label stop-badge stop-s">S</span> <?= htmlspecialchars(replacePlaceholders(tField($gef, 'massnahme_s', $lang), $vars)) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($gef['massnahme_t']) || ($lang === 'en' && !empty($gef['massnahme_t_en']))):
                        $hasMassnahmen = true;
                    ?>
                    <div class="massnahme-item"><span class="massnahme-label stop-badge stop-t">T</span> <?= htmlspecialchars(replacePlaceholders(tField($gef, 'massnahme_t', $lang), $vars)) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($gef['massnahme_o']) || ($lang === 'en' && !empty($gef['massnahme_o_en']))):
                        $hasMassnahmen = true;
                    ?>
                    <div class="massnahme-item"><span class="massnahme-label stop-badge stop-o">O</span> <?= htmlspecialchars(replacePlaceholders(tField($gef, 'massnahme_o', $lang), $vars)) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($gef['massnahme_p']) || ($lang === 'en' && !empty($gef['massnahme_p_en']))):
                        $hasMassnahmen = true;
                    ?>
                    <div class="massnahme-item"><span class="massnahme-label stop-badge stop-p">P</span> <?= htmlspecialchars(replacePlaceholders(tField($gef, 'massnahme_p', $lang), $vars)) ?></div>
                    <?php endif; ?>
                    <?php if (!$hasMassnahmen && !empty($gef['massnahmen'])): ?>
                    <?= nl2br(htmlspecialchars($gef['massnahmen'])) ?>
                    <?php endif; ?>
                </td>
                <td class="text-center"><?= $gef['schadenschwere_nach'] ?: '-' ?></td>
                <td class="text-center"><?= $gef['wahrscheinlichkeit_nach'] ?: '-' ?></td>
                <td class="text-center <?= $rClassNach ?>"><?= $rScoreNach ?: '-' ?></td>
                <td class="small"><?= htmlspecialchars(replacePlaceholders(tField($gef, 'verantwortlich', $lang), $vars)) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($projekt['beschreibung']): ?>
    <h2><?= t('project_description', $lang) ?></h2>
    <p style="padding: 5px;"><?= nl2br(htmlspecialchars($projekt['beschreibung'])) ?></p>
    <?php endif; ?>

    <p style="margin-top: 15px; font-size: 7pt; color: #666; text-align: center;">
        <?= t('created_on', $lang) ?> <?= date('d.m.Y H:i') ?>
    </p>
</body>
</html>
    <?php
}

function generateExcel($projekt, $gefaehrdungen, $gefNachKategorie, $erstellerName, $lang, $vars) {
    // CSV-Export (kann in Excel geöffnet werden)
    $filename = 'GBU_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $projekt['name']) . '_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // BOM für UTF-8 in Excel
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');

    // Header
    fputcsv($output, [t('export_title', $lang)], ';');
    fputcsv($output, [t('project', $lang) . ': ' . $projekt['name']], ';');
    fputcsv($output, [t('location', $lang) . ': ' . $projekt['location']], ';');
    fputcsv($output, [t('period', $lang) . ': ' . date('d.m.Y', strtotime($projekt['zeitraum_von'])) . ' - ' . date('d.m.Y', strtotime($projekt['zeitraum_bis']))], ';');
    fputcsv($output, [t('creator', $lang) . ': ' . $erstellerName], ';');
    fputcsv($output, ['Export: ' . date('d.m.Y H:i')], ';');
    fputcsv($output, [], ';');

    // Spaltenüberschriften
    fputcsv($output, [
        t('col_nr', $lang),
        t('csv_category', $lang),
        t('csv_subcategory', $lang),
        t('csv_hazard_title', $lang),
        t('csv_description', $lang),
        t('csv_hazard_type', $lang),
        t('csv_severity', $lang),
        t('csv_probability', $lang),
        t('csv_risk', $lang),
        'STOP-S',
        'STOP-T',
        'STOP-O',
        'STOP-P',
        t('csv_measure_s', $lang),
        t('csv_measure_t', $lang),
        t('csv_measure_o', $lang),
        t('csv_measure_p', $lang),
        t('csv_severity_after', $lang),
        t('csv_probability_after', $lang),
        t('csv_risk_after', $lang),
        t('csv_responsible', $lang)
    ], ';');

    // Daten
    foreach ($gefNachKategorie as $katId => $katData) {
        $lfdNr = 0;
        foreach ($katData['items'] as $gef) {
            $lfdNr++;
            $nummerPrefix = $katData['nummer'] != 999 ? $katData['nummer'] . '.' : '';
            if ($gef['unterkategorie_nummer']) {
                $nummerPrefix .= $gef['unterkategorie_nummer'] . '.';
            }
            $vollNummer = $nummerPrefix . $lfdNr;

            fputcsv($output, [
                $vollNummer,
                $katData['name'],
                tField($gef, 'unterkategorie_name', $lang),
                replacePlaceholders(tField($gef, 'titel', $lang), $vars),
                replacePlaceholders(tField($gef, 'beschreibung', $lang), $vars),
                ($gef['gefaehrdungsart_nummer'] ?? '') . ' ' . tField($gef, 'gefaehrdungsart_name', $lang),
                $gef['schadenschwere'],
                $gef['wahrscheinlichkeit'],
                $gef['risikobewertung'],
                $gef['stop_s'] ? 'X' : '',
                $gef['stop_t'] ? 'X' : '',
                $gef['stop_o'] ? 'X' : '',
                $gef['stop_p'] ? 'X' : '',
                replacePlaceholders(tField($gef, 'massnahme_s', $lang), $vars),
                replacePlaceholders(tField($gef, 'massnahme_t', $lang), $vars),
                replacePlaceholders(tField($gef, 'massnahme_o', $lang), $vars),
                replacePlaceholders(tField($gef, 'massnahme_p', $lang), $vars),
                $gef['schadenschwere_nach'] ?? '',
                $gef['wahrscheinlichkeit_nach'] ?? '',
                $gef['risikobewertung_nach'] ?? '',
                replacePlaceholders(tField($gef, 'verantwortlich', $lang), $vars)
            ], ';');
        }
    }

    fclose($output);
}
