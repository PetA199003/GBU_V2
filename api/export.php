<?php
/**
 * Export-Funktionen für Projekte (PDF/Excel)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

$type = $_GET['type'] ?? 'projekt';
$format = $_GET['format'] ?? 'pdf';
$id = $_GET['id'] ?? null;

if (!$id) {
    die('Projekt-ID erforderlich');
}

$db = Database::getInstance();

// Projekt laden
$projekt = $db->fetchOne("SELECT * FROM projekte WHERE id = ?", [$id]);

if (!$projekt) {
    die('Projekt nicht gefunden');
}

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
           ga.name as gefaehrdungsart_name, ga.nummer as gefaehrdungsart_nummer,
           ak.name as kategorie_name, ak.nummer as kategorie_nummer,
           auk.name as unterkategorie_name, auk.nummer as unterkategorie_nummer
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
    $katName = $gef['kategorie_name'] ? $gef['kategorie_nummer'] . '. ' . $gef['kategorie_name'] : 'Ohne Kategorie';
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

if ($format === 'excel' || $format === 'csv') {
    generateExcel($projekt, $gefaehrdungen, $gefNachKategorie, $erstellerName);
} else {
    generatePDFView($projekt, $gefaehrdungen, $gefNachKategorie, $erstellerName);
}

function generatePDFView($projekt, $gefaehrdungen, $gefNachKategorie, $erstellerName) {
    ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gefährdungsbeurteilung - <?= htmlspecialchars($projekt['name']) ?></title>
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
    <a href="<?= BASE_URL ?>/projekt.php?id=<?= $projekt['id'] ?>" class="back-btn no-print">← Zurück zum Projekt</a>
    <button class="print-btn no-print" onclick="window.print()">🖨️ Drucken / PDF</button>

    <h1>Gefährdungsbeurteilung - <?= htmlspecialchars($projekt['name']) ?></h1>

    <table class="header-table">
        <tr>
            <td style="width: 50%;">
                <strong>Projekt:</strong> <?= htmlspecialchars($projekt['name']) ?><br>
                <strong>Location:</strong> <?= htmlspecialchars($projekt['location']) ?>
            </td>
            <td style="width: 50%;">
                <strong>Zeitraum:</strong> <?= date('d.m.Y', strtotime($projekt['zeitraum_von'])) ?> - <?= date('d.m.Y', strtotime($projekt['zeitraum_bis'])) ?><br>
                <?php if ($projekt['aufbau_datum']): ?>
                <strong>Aufbau:</strong> <?= date('d.m.Y', strtotime($projekt['aufbau_datum'])) ?><br>
                <?php endif; ?>
                <strong>Ersteller:</strong> <?= htmlspecialchars($erstellerName) ?>
            </td>
        </tr>
    </table>

    <div class="legend">
        <strong>Legende Schadenschwere (S):</strong><br>
        <span class="legend-item">1 = Leichte Verletzungen / Erkrankungen</span><br>
        <span class="legend-item">2 = Mittlere Verletzungen / Erkrankungen</span><br>
        <span class="legend-item">3 = Schwere Verletzungen / bleibende Schaeden / Moeglicher Tod</span><br><br>
        <strong>Legende Wahrscheinlichkeit (W):</strong><br>
        <span class="legend-item">1 = unwahrscheinlich</span><br>
        <span class="legend-item">2 = wahrscheinlich</span><br>
        <span class="legend-item">3 = sehr wahrscheinlich</span><br><br>
        <strong>R</strong> = Risiko (S² × W) |
        <span class="legend-item"><span class="stop-s">S</span> Substitution</span>
        <span class="legend-item"><span class="stop-t">T</span> Technisch</span>
        <span class="legend-item"><span class="stop-o">O</span> Organisatorisch</span>
        <span class="legend-item"><span class="stop-p">P</span> Persoenlich (PSA)</span>
    </div>

    <?php if (empty($gefaehrdungen)): ?>
    <p><em>Keine Gefährdungen erfasst.</em></p>
    <?php else: ?>

    <?php foreach ($gefNachKategorie as $katId => $katData): ?>
    <h2><?= htmlspecialchars($katData['name']) ?></h2>

    <table>
        <thead>
            <tr>
                <th style="width: 6%;">Nr.</th>
                <th style="width: 18%;">Gefährdung</th>
                <th style="width: 12%;">Gefährdungsart</th>
                <th style="width: 4%;" class="text-center">S</th>
                <th style="width: 4%;" class="text-center">W</th>
                <th style="width: 4%;" class="text-center">R</th>
                <th style="width: 8%;" class="text-center">STOP</th>
                <th style="width: 22%;">Maßnahmen</th>
                <th style="width: 4%;" class="text-center">S'</th>
                <th style="width: 4%;" class="text-center">W'</th>
                <th style="width: 4%;" class="text-center">R'</th>
                <th style="width: 10%;">Verantw.</th>
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
                    <strong><?= htmlspecialchars($gef['titel']) ?></strong>
                    <?php if ($gef['unterkategorie_name']): ?>
                    <br><span class="small"><?= $katData['nummer'] ?>.<?= $gef['unterkategorie_nummer'] ?> <?= htmlspecialchars($gef['unterkategorie_name']) ?></span>
                    <?php endif; ?>
                </td>
                <td class="small">
                    <?php if ($gef['gefaehrdungsart_name']): ?>
                    <?= $gef['gefaehrdungsart_nummer'] ?>. <?= htmlspecialchars($gef['gefaehrdungsart_name']) ?>
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
                    if (!empty($gef['massnahme_s'])):
                        $hasMassnahmen = true;
                    ?>
                    <div class="massnahme-item"><span class="massnahme-label stop-badge stop-s">S</span> <?= htmlspecialchars($gef['massnahme_s']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($gef['massnahme_t'])):
                        $hasMassnahmen = true;
                    ?>
                    <div class="massnahme-item"><span class="massnahme-label stop-badge stop-t">T</span> <?= htmlspecialchars($gef['massnahme_t']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($gef['massnahme_o'])):
                        $hasMassnahmen = true;
                    ?>
                    <div class="massnahme-item"><span class="massnahme-label stop-badge stop-o">O</span> <?= htmlspecialchars($gef['massnahme_o']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($gef['massnahme_p'])):
                        $hasMassnahmen = true;
                    ?>
                    <div class="massnahme-item"><span class="massnahme-label stop-badge stop-p">P</span> <?= htmlspecialchars($gef['massnahme_p']) ?></div>
                    <?php endif; ?>
                    <?php if (!$hasMassnahmen && !empty($gef['massnahmen'])): ?>
                    <?= nl2br(htmlspecialchars($gef['massnahmen'])) ?>
                    <?php endif; ?>
                </td>
                <td class="text-center"><?= $gef['schadenschwere_nach'] ?: '-' ?></td>
                <td class="text-center"><?= $gef['wahrscheinlichkeit_nach'] ?: '-' ?></td>
                <td class="text-center <?= $rClassNach ?>"><?= $rScoreNach ?: '-' ?></td>
                <td class="small"><?= htmlspecialchars($gef['verantwortlich'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($projekt['beschreibung']): ?>
    <h2>Projektbeschreibung</h2>
    <p style="padding: 5px;"><?= nl2br(htmlspecialchars($projekt['beschreibung'])) ?></p>
    <?php endif; ?>

    <p style="margin-top: 15px; font-size: 7pt; color: #666; text-align: center;">
        Erstellt am <?= date('d.m.Y H:i') ?>
    </p>
</body>
</html>
    <?php
}

function generateExcel($projekt, $gefaehrdungen, $gefNachKategorie, $erstellerName) {
    // CSV-Export (kann in Excel geöffnet werden)
    $filename = 'GBU_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $projekt['name']) . '_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // BOM für UTF-8 in Excel
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');

    // Header
    fputcsv($output, ['Gefährdungsbeurteilung'], ';');
    fputcsv($output, ['Projekt: ' . $projekt['name']], ';');
    fputcsv($output, ['Location: ' . $projekt['location']], ';');
    fputcsv($output, ['Zeitraum: ' . date('d.m.Y', strtotime($projekt['zeitraum_von'])) . ' - ' . date('d.m.Y', strtotime($projekt['zeitraum_bis']))], ';');
    fputcsv($output, ['Ersteller: ' . $erstellerName], ';');
    fputcsv($output, ['Export: ' . date('d.m.Y H:i')], ';');
    fputcsv($output, [], ';');

    // Spaltenüberschriften
    fputcsv($output, [
        'Nr.',
        'Kategorie',
        'Unterkategorie',
        'Gefährdung (Titel)',
        'Beschreibung',
        'Gefährdungsart',
        'Schadenschwere (S)',
        'Wahrscheinlichkeit (W)',
        'Risiko (R)',
        'STOP-S',
        'STOP-T',
        'STOP-O',
        'STOP-P',
        'Maßnahme S (Substitution)',
        'Maßnahme T (Technisch)',
        'Maßnahme O (Organisatorisch)',
        'Maßnahme P (Persönlich)',
        'S nach Maßnahme',
        'W nach Maßnahme',
        'R nach Maßnahme',
        'Verantwortlich'
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
                $gef['unterkategorie_name'] ?? '',
                $gef['titel'],
                $gef['beschreibung'],
                ($gef['gefaehrdungsart_nummer'] ?? '') . ' ' . ($gef['gefaehrdungsart_name'] ?? ''),
                $gef['schadenschwere'],
                $gef['wahrscheinlichkeit'],
                $gef['risikobewertung'],
                $gef['stop_s'] ? 'X' : '',
                $gef['stop_t'] ? 'X' : '',
                $gef['stop_o'] ? 'X' : '',
                $gef['stop_p'] ? 'X' : '',
                $gef['massnahme_s'] ?? '',
                $gef['massnahme_t'] ?? '',
                $gef['massnahme_o'] ?? '',
                $gef['massnahme_p'] ?? '',
                $gef['schadenschwere_nach'] ?? '',
                $gef['wahrscheinlichkeit_nach'] ?? '',
                $gef['risikobewertung_nach'] ?? '',
                $gef['verantwortlich'] ?? ''
            ], ';');
        }
    }

    fclose($output);
}
