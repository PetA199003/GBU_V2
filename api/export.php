<?php
/**
 * Export-Funktionen (PDF/Excel)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Gefaehrdungsbeurteilung.php';

requireLogin();

$type = $_GET['type'] ?? 'pdf';
$id = $_GET['id'] ?? null;

if (!$id) {
    die('ID erforderlich');
}

$gbClass = new Gefaehrdungsbeurteilung();
$beurteilung = $gbClass->getById($id);

if (!$beurteilung) {
    die('Gefährdungsbeurteilung nicht gefunden');
}

if ($type === 'pdf') {
    // HTML-basiertes PDF (kann mit Browser-Druckfunktion oder wkhtmltopdf konvertiert werden)
    generatePDFView($beurteilung);
} elseif ($type === 'excel') {
    generateExcel($beurteilung);
}

function generatePDFView($beurteilung) {
    ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gefährdungsbeurteilung - <?= htmlspecialchars($beurteilung['titel']) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            padding: 15mm;
        }
        h1 { font-size: 16pt; margin-bottom: 10px; }
        h2 { font-size: 12pt; margin: 15px 0 5px; background: #0d6efd; color: white; padding: 5px 10px; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            page-break-inside: auto;
        }
        tr { page-break-inside: avoid; page-break-after: auto; }
        th, td {
            border: 1px solid #333;
            padding: 4px 6px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f0f0f0;
            font-weight: bold;
        }
        .header-table td { border: none; padding: 2px 5px; }
        .risk-1 { background: #92D050; }
        .risk-2 { background: #92D050; }
        .risk-3 { background: #FFFF00; }
        .risk-4 { background: #FFFF00; }
        .risk-6 { background: #FFC000; }
        .risk-8 { background: #FFC000; }
        .risk-9, .risk-12, .risk-18, .risk-27 { background: #FF0000; color: white; }
        .stop-s { background: #FF0000; color: white; padding: 2px 5px; border-radius: 3px; }
        .stop-t { background: #FFC000; padding: 2px 5px; border-radius: 3px; }
        .stop-o { background: #FFFF00; padding: 2px 5px; border-radius: 3px; }
        .stop-p { background: #92D050; padding: 2px 5px; border-radius: 3px; }
        .text-center { text-align: center; }
        .small { font-size: 8pt; }
        .legend { margin-bottom: 15px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; }
        .legend-item { display: inline-block; margin-right: 15px; }
        .signature-line { border-bottom: 1px solid #333; height: 40px; margin-bottom: 5px; }
        @media print {
            body { padding: 10mm; }
            .no-print { display: none; }
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
        }
        .print-btn:hover { background: #0b5ed7; }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">Drucken / PDF speichern</button>

    <h1>Gefährdungsbeurteilung</h1>
    <p style="margin-bottom: 15px;">nach §§ 5, 6 ArbSchG, § 3 ArbStättV, ASR V3 "Gefährdungsbeurteilung"</p>

    <table class="header-table">
        <tr>
            <td style="width: 50%;">
                <strong>Unternehmen:</strong> <?= htmlspecialchars($beurteilung['unternehmen_name']) ?><br>
                <?php if ($beurteilung['arbeitsbereich_name']): ?>
                <strong>Arbeitsbereich:</strong> <?= htmlspecialchars($beurteilung['arbeitsbereich_name']) ?><br>
                <?php endif; ?>
                <strong>Titel:</strong> <?= htmlspecialchars($beurteilung['titel']) ?>
            </td>
            <td style="width: 50%;">
                <strong>Ersteller:</strong> <?= htmlspecialchars($beurteilung['ersteller_name']) ?><br>
                <strong>Erstellt am:</strong> <?= date('d.m.Y', strtotime($beurteilung['erstelldatum'])) ?><br>
                <?php if ($beurteilung['ueberarbeitungsdatum']): ?>
                <strong>Überarbeitet am:</strong> <?= date('d.m.Y', strtotime($beurteilung['ueberarbeitungsdatum'])) ?>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <div class="legend">
        <strong>Legende:</strong><br>
        <span class="legend-item"><strong>S</strong> = Schadenschwere (1-3)</span>
        <span class="legend-item"><strong>W</strong> = Wahrscheinlichkeit (1-3)</span>
        <span class="legend-item"><strong>R</strong> = Risiko (S² × W)</span>
        <br>
        <span class="legend-item"><span class="stop-s">S</span> Substitution</span>
        <span class="legend-item"><span class="stop-t">T</span> Technisch</span>
        <span class="legend-item"><span class="stop-o">O</span> Organisatorisch</span>
        <span class="legend-item"><span class="stop-p">P</span> Persönlich (PSA)</span>
    </div>

    <?php foreach ($beurteilung['taetigkeiten'] as $taetigkeit): ?>
    <h2><?= htmlspecialchars($taetigkeit['position']) ?> - <?= htmlspecialchars($taetigkeit['name']) ?></h2>

    <?php if (!empty($taetigkeit['vorgaenge'])): ?>
    <table>
        <thead>
            <tr>
                <th style="width: 40px;">Pos.</th>
                <th style="width: 12%;">Vorgang</th>
                <th style="width: 15%;">Gefährdung</th>
                <th style="width: 10%;">Gef.-Faktor</th>
                <th style="width: 25px;" class="text-center">S</th>
                <th style="width: 25px;" class="text-center">W</th>
                <th style="width: 25px;" class="text-center">R</th>
                <th style="width: 60px;" class="text-center">STOP</th>
                <th style="width: 18%;">Maßnahmen</th>
                <th style="width: 25px;" class="text-center">S</th>
                <th style="width: 25px;" class="text-center">W</th>
                <th style="width: 25px;" class="text-center">R</th>
                <th style="width: 10%;">Regelungen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($taetigkeit['vorgaenge'] as $vorgang): ?>
            <tr>
                <td><?= htmlspecialchars($vorgang['position']) ?></td>
                <td><?= nl2br(htmlspecialchars($vorgang['vorgang_beschreibung'])) ?></td>
                <td><?= nl2br(htmlspecialchars($vorgang['gefaehrdung'])) ?></td>
                <td class="small">
                    <?php if ($vorgang['faktor_nummer']): ?>
                    <?= htmlspecialchars($vorgang['faktor_nummer']) ?><br>
                    <?= htmlspecialchars($vorgang['faktor_name']) ?>
                    <?php endif; ?>
                </td>
                <td class="text-center risk-<?= $vorgang['schadenschwere'] ?>"><?= $vorgang['schadenschwere'] ?></td>
                <td class="text-center risk-<?= $vorgang['wahrscheinlichkeit'] ?>"><?= $vorgang['wahrscheinlichkeit'] ?></td>
                <td class="text-center risk-<?= $vorgang['risikobewertung'] ?>"><?= $vorgang['risikobewertung'] ?></td>
                <td class="text-center">
                    <?php if ($vorgang['stop_s']): ?><span class="stop-s">S</span><?php endif; ?>
                    <?php if ($vorgang['stop_t']): ?><span class="stop-t">T</span><?php endif; ?>
                    <?php if ($vorgang['stop_o']): ?><span class="stop-o">O</span><?php endif; ?>
                    <?php if ($vorgang['stop_p']): ?><span class="stop-p">P</span><?php endif; ?>
                </td>
                <td class="small"><?= nl2br(htmlspecialchars($vorgang['massnahmen'])) ?></td>
                <td class="text-center <?= $vorgang['massnahme_schadenschwere'] ? 'risk-' . $vorgang['massnahme_schadenschwere'] : '' ?>">
                    <?= $vorgang['massnahme_schadenschwere'] ?: '-' ?>
                </td>
                <td class="text-center <?= $vorgang['massnahme_wahrscheinlichkeit'] ? 'risk-' . $vorgang['massnahme_wahrscheinlichkeit'] : '' ?>">
                    <?= $vorgang['massnahme_wahrscheinlichkeit'] ?: '-' ?>
                </td>
                <td class="text-center <?= $vorgang['massnahme_risikobewertung'] ? 'risk-' . $vorgang['massnahme_risikobewertung'] : '' ?>">
                    <?= $vorgang['massnahme_risikobewertung'] ?: '-' ?>
                </td>
                <td class="small"><?= nl2br(htmlspecialchars($vorgang['gesetzliche_regelungen'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p><em>Keine Gefährdungen erfasst.</em></p>
    <?php endif; ?>
    <?php endforeach; ?>

    <?php if ($beurteilung['bemerkungen']): ?>
    <h2>Bemerkungen</h2>
    <p><?= nl2br(htmlspecialchars($beurteilung['bemerkungen'])) ?></p>
    <?php endif; ?>

    <div style="margin-top: 30px;">
        <table class="header-table" style="width: 100%;">
            <tr>
                <td style="width: 33%; text-align: center;">
                    <div class="signature-line"></div>
                    <small>Ersteller / Datum</small>
                </td>
                <td style="width: 33%; text-align: center;">
                    <div class="signature-line"></div>
                    <small>Fachkraft für Arbeitssicherheit / Datum</small>
                </td>
                <td style="width: 33%; text-align: center;">
                    <div class="signature-line"></div>
                    <small>Geschäftsführung / Datum</small>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
    <?php
}

function generateExcel($beurteilung) {
    // CSV-Export (kann in Excel geöffnet werden)
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Gefaehrdungsbeurteilung_' . $beurteilung['id'] . '.csv"');

    // BOM für UTF-8 in Excel
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');

    // Header
    fputcsv($output, ['Gefährdungsbeurteilung: ' . $beurteilung['titel']], ';');
    fputcsv($output, ['Unternehmen: ' . $beurteilung['unternehmen_name']], ';');
    fputcsv($output, ['Ersteller: ' . $beurteilung['ersteller_name']], ';');
    fputcsv($output, ['Datum: ' . date('d.m.Y', strtotime($beurteilung['erstelldatum']))], ';');
    fputcsv($output, [], ';');

    // Spaltenüberschriften
    fputcsv($output, [
        'Tätigkeit',
        'Position',
        'Vorgang',
        'Gefährdung',
        'Gefährdungsfaktor',
        'Schadenschwere (S)',
        'Wahrscheinlichkeit (W)',
        'Risiko (R)',
        'STOP-S',
        'STOP-T',
        'STOP-O',
        'STOP-P',
        'Maßnahmen',
        'S nach Maßnahme',
        'W nach Maßnahme',
        'R nach Maßnahme',
        'Gesetzliche Regelungen',
        'Bemerkungen'
    ], ';');

    // Daten
    foreach ($beurteilung['taetigkeiten'] as $taetigkeit) {
        foreach ($taetigkeit['vorgaenge'] as $vorgang) {
            fputcsv($output, [
                $taetigkeit['position'] . ' - ' . $taetigkeit['name'],
                $vorgang['position'],
                $vorgang['vorgang_beschreibung'],
                $vorgang['gefaehrdung'],
                $vorgang['faktor_nummer'] . ' ' . $vorgang['faktor_name'],
                $vorgang['schadenschwere'],
                $vorgang['wahrscheinlichkeit'],
                $vorgang['risikobewertung'],
                $vorgang['stop_s'] ? 'X' : '',
                $vorgang['stop_t'] ? 'X' : '',
                $vorgang['stop_o'] ? 'X' : '',
                $vorgang['stop_p'] ? 'X' : '',
                $vorgang['massnahmen'],
                $vorgang['massnahme_schadenschwere'] ?: '',
                $vorgang['massnahme_wahrscheinlichkeit'] ?: '',
                $vorgang['massnahme_risikobewertung'] ?: '',
                $vorgang['gesetzliche_regelungen'],
                $vorgang['sonstige_bemerkungen']
            ], ';');
        }
    }

    fclose($output);
}
