<?php
/**
 * Übersetzungen für Exports (DE/EN)
 * Portal-UI bleibt deutsch — diese Datei wird nur in Export-Dateien verwendet.
 */

$TRANSLATIONS = [
    'de' => [
        // === Gefährdungsbeurteilung PDF Export ===
        'export_title'        => 'Gefährdungsbeurteilung',
        'project'             => 'Projekt',
        'location'            => 'Location',
        'period'              => 'Zeitraum',
        'setup'               => 'Aufbau',
        'teardown'            => 'Abbau',
        'creator'             => 'Ersteller',
        'created_on'          => 'Erstellt am',
        'no_hazards'          => 'Keine Gefährdungen erfasst.',
        'project_description' => 'Projektbeschreibung',
        'without_category'    => 'Ohne Kategorie',

        // Tabellen-Header GBU PDF
        'col_nr'              => 'Nr.',
        'col_hazard'          => 'Gefährdung',
        'col_hazard_type'     => 'Gefährdungsart',
        'col_measures'        => 'Maßnahmen',
        'col_responsible'     => 'Verantw.',

        // Legende
        'legend_severity'     => 'Legende Schadenschwere (S)',
        'legend_probability'  => 'Legende Wahrscheinlichkeit (W)',
        'severity_1'          => '1 = Leichte Verletzungen / Erkrankungen',
        'severity_2'          => '2 = Mittlere Verletzungen / Erkrankungen',
        'severity_3'          => '3 = Schwere Verletzungen / bleibende Schäden / Möglicher Tod',
        'probability_1'       => '1 = unwahrscheinlich',
        'probability_2'       => '2 = wahrscheinlich',
        'probability_3'       => '3 = sehr wahrscheinlich',
        'risk_formula'        => 'R = Risiko (S² × W)',
        'stop_s_label'        => 'Substitution',
        'stop_t_label'        => 'Technisch',
        'stop_o_label'        => 'Organisatorisch',
        'stop_p_label'        => 'Persönlich (PSA)',

        // CSV-Header
        'csv_category'        => 'Kategorie',
        'csv_subcategory'     => 'Unterkategorie',
        'csv_hazard_title'    => 'Gefährdung (Titel)',
        'csv_description'     => 'Beschreibung',
        'csv_hazard_type'     => 'Gefährdungsart',
        'csv_severity'        => 'Schadenschwere (S)',
        'csv_probability'     => 'Wahrscheinlichkeit (W)',
        'csv_risk'            => 'Risiko (R)',
        'csv_measure_s'       => 'Maßnahme S (Substitution)',
        'csv_measure_t'       => 'Maßnahme T (Technisch)',
        'csv_measure_o'       => 'Maßnahme O (Organisatorisch)',
        'csv_measure_p'       => 'Maßnahme P (Persönlich)',
        'csv_severity_after'  => 'S nach Maßnahme',
        'csv_probability_after' => 'W nach Maßnahme',
        'csv_risk_after'      => 'R nach Maßnahme',
        'csv_responsible'     => 'Verantwortlich',

        // === Unterweisung Export ===
        'uw_title'            => 'Regeln für Arbeiten bei Produktionen und Veranstaltungen',
        'uw_event'            => 'Veranstaltung',
        'uw_date_location'    => 'Datum und Ort',
        'uw_created_by'       => 'Erstellt von',
        'uw_on_date'          => 'am',

        // === Teilnehmerliste Export ===
        'tl_title'            => 'Bestätigung der Unterweisung',
        'tl_conducted_by'     => 'Unterweisung durchgeführt von',
        'tl_on_date'          => 'am',
        'tl_event'            => 'Veranstaltung',
        'tl_location'         => 'Ort',
        'tl_signature'        => 'Unterschrift',
        'tl_signed'           => 'unterschrieben',
        'tl_info_text'        => 'Die Unterweisung wurde basierend auf der erstellten Gefährdungsbeurteilung und der aktuellen Gesetzeslage durchgeführt.',
        'tl_confirm_text'     => 'Mit meiner Unterschrift bestätige ich, dass ich an der Unterweisung teilgenommen und den Inhalt verstanden habe.',
        'tl_name'             => 'Name',
        'tl_firstname'        => 'Vorname',
        'tl_company'          => 'Firma',
        'tl_datetime'         => 'Datum / Uhrzeit',
        'tl_time_suffix'      => 'Uhr',
        'tl_to'               => 'bis',

        // Risiko-Stufen
        'risk_low'            => 'Gering',
        'risk_medium'         => 'Mittel',
        'risk_high'           => 'Hoch',
        'risk_very_high'      => 'Sehr hoch',

        // Buttons
        'btn_print'           => 'Drucken / PDF',
        'btn_back_project'    => '← Zurück zum Projekt',
        'btn_back_unterweisung' => '← Zurück zur Unterweisung',
    ],

    'en' => [
        // === Risk Assessment PDF Export ===
        'export_title'        => 'Risk Assessment',
        'project'             => 'Project',
        'location'            => 'Location',
        'period'              => 'Period',
        'setup'               => 'Setup',
        'teardown'            => 'Teardown',
        'creator'             => 'Created by',
        'created_on'          => 'Created on',
        'no_hazards'          => 'No hazards recorded.',
        'project_description' => 'Project Description',
        'without_category'    => 'Uncategorised',

        // Table headers GBU PDF
        'col_nr'              => 'No.',
        'col_hazard'          => 'Hazard',
        'col_hazard_type'     => 'Hazard Type',
        'col_measures'        => 'Measures',
        'col_responsible'     => 'Resp.',

        // Legend
        'legend_severity'     => 'Legend Severity (S)',
        'legend_probability'  => 'Legend Probability (P)',
        'severity_1'          => '1 = Minor injuries / illness',
        'severity_2'          => '2 = Moderate injuries / illness',
        'severity_3'          => '3 = Severe injuries / permanent damage / possible death',
        'probability_1'       => '1 = unlikely',
        'probability_2'       => '2 = likely',
        'probability_3'       => '3 = very likely',
        'risk_formula'        => 'R = Risk (S² × P)',
        'stop_s_label'        => 'Substitution',
        'stop_t_label'        => 'Technical',
        'stop_o_label'        => 'Organisational',
        'stop_p_label'        => 'Personal (PPE)',

        // CSV headers
        'csv_category'        => 'Category',
        'csv_subcategory'     => 'Subcategory',
        'csv_hazard_title'    => 'Hazard (Title)',
        'csv_description'     => 'Description',
        'csv_hazard_type'     => 'Hazard Type',
        'csv_severity'        => 'Severity (S)',
        'csv_probability'     => 'Probability (P)',
        'csv_risk'            => 'Risk (R)',
        'csv_measure_s'       => 'Measure S (Substitution)',
        'csv_measure_t'       => 'Measure T (Technical)',
        'csv_measure_o'       => 'Measure O (Organisational)',
        'csv_measure_p'       => 'Measure P (Personal/PPE)',
        'csv_severity_after'  => 'S after measures',
        'csv_probability_after' => 'P after measures',
        'csv_risk_after'      => 'R after measures',
        'csv_responsible'     => 'Responsible',

        // === Safety Briefing Export ===
        'uw_title'            => 'Safety Rules for Productions and Events',
        'uw_event'            => 'Event',
        'uw_date_location'    => 'Date and Location',
        'uw_created_by'       => 'Created by',
        'uw_on_date'          => 'on',

        // === Participant List Export ===
        'tl_title'            => 'Confirmation of Safety Briefing',
        'tl_conducted_by'     => 'Briefing conducted by',
        'tl_on_date'          => 'on',
        'tl_event'            => 'Event',
        'tl_location'         => 'Location',
        'tl_signature'        => 'Signature',
        'tl_signed'           => 'signed',
        'tl_info_text'        => 'The safety briefing was conducted based on the risk assessment and current legislation.',
        'tl_confirm_text'     => 'With my signature I confirm that I attended the safety briefing and understood its contents.',
        'tl_name'             => 'Last Name',
        'tl_firstname'        => 'First Name',
        'tl_company'          => 'Company',
        'tl_datetime'         => 'Date / Time',
        'tl_time_suffix'      => '',
        'tl_to'               => 'to',

        // Risk levels
        'risk_low'            => 'Low',
        'risk_medium'         => 'Medium',
        'risk_high'           => 'High',
        'risk_very_high'      => 'Very High',

        // Buttons
        'btn_print'           => 'Print / PDF',
        'btn_back_project'    => '← Back to Project',
        'btn_back_unterweisung' => '← Back to Briefing',
    ],
];

/**
 * Übersetzt ein festes Label.
 * Fallback auf Deutsch wenn Key in Zielsprache nicht vorhanden.
 */
function t(string $key, string $lang = 'de'): string {
    global $TRANSLATIONS;
    return $TRANSLATIONS[$lang][$key] ?? $TRANSLATIONS['de'][$key] ?? $key;
}

/**
 * Gibt den übersetzten DB-Feldwert zurück.
 * Bei lang=en wird field_en verwendet, wenn vorhanden. Sonst Fallback auf DE.
 */
function tField(array $row, string $field, string $lang = 'de'): string {
    if ($lang === 'en') {
        $enField = $field . '_en';
        if (!empty($row[$enField])) {
            return $row[$enField];
        }
    }
    return $row[$field] ?? '';
}

/**
 * Risikostufe in der jeweiligen Sprache.
 */
function getRiskLevelTranslated(int $score, string $lang = 'de'): string {
    if ($score <= 2) return t('risk_low', $lang);
    if ($score <= 4) return t('risk_medium', $lang);
    if ($score <= 8) return t('risk_high', $lang);
    return t('risk_very_high', $lang);
}

/**
 * Ersetzt Platzhalter-Variablen in einem Text.
 *
 * Verfügbare Platzhalter:
 *   %Unternehmen   — Firmenname
 *   %Projekt        — Projektname
 *   %Ort            — Location / Veranstaltungsort
 *   %DatumVon       — Zeitraum von (dd.mm.yyyy)
 *   %DatumBis       — Zeitraum bis (dd.mm.yyyy)
 *   %Zeitraum       — Zeitraum komplett (dd.mm. - dd.mm.yyyy)
 *   %Unterweiser    — Durchgeführt von (Name des Unterweisers)
 *
 * @param string $text  Der Text mit Platzhaltern
 * @param array  $vars  Assoziatives Array mit den Werten
 * @return string       Text mit ersetzten Platzhaltern
 */
function replacePlaceholders(string $text, array $vars): string {
    $placeholders = [
        '%Unternehmen' => $vars['unternehmen'] ?? '',
        '%Projekt'     => $vars['projekt'] ?? '',
        '%Ort'         => $vars['ort'] ?? '',
        '%DatumVon'    => $vars['datum_von'] ?? '',
        '%DatumBis'    => $vars['datum_bis'] ?? '',
        '%Zeitraum'    => $vars['zeitraum'] ?? '',
        '%Unterweiser' => $vars['unterweiser'] ?? '',
    ];
    return str_replace(array_keys($placeholders), array_values($placeholders), $text);
}
