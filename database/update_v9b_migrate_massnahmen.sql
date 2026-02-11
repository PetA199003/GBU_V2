-- Migration: Bestehende Maßnahmen zu massnahme_o (Organisatorisch) kopieren
-- und dann das alte Feld leeren

-- Zuerst sicherstellen, dass die neuen Spalten existieren
-- (Falls update_v9 noch nicht ausgeführt wurde)

-- Gefährdung-Bibliothek: Spalten hinzufügen falls nicht vorhanden
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gefaehrdung_bibliothek' AND COLUMN_NAME = 'massnahme_s') = 0,
    'ALTER TABLE gefaehrdung_bibliothek
        ADD COLUMN massnahme_s TEXT DEFAULT NULL AFTER stop_p,
        ADD COLUMN massnahme_t TEXT DEFAULT NULL AFTER massnahme_s,
        ADD COLUMN massnahme_o TEXT DEFAULT NULL AFTER massnahme_t,
        ADD COLUMN massnahme_p TEXT DEFAULT NULL AFTER massnahme_o',
    'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Projekt-Gefährdungen: Spalten hinzufügen falls nicht vorhanden
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projekt_gefaehrdungen' AND COLUMN_NAME = 'massnahme_s') = 0,
    'ALTER TABLE projekt_gefaehrdungen
        ADD COLUMN massnahme_s TEXT DEFAULT NULL AFTER stop_p,
        ADD COLUMN massnahme_t TEXT DEFAULT NULL AFTER massnahme_s,
        ADD COLUMN massnahme_o TEXT DEFAULT NULL AFTER massnahme_t,
        ADD COLUMN massnahme_p TEXT DEFAULT NULL AFTER massnahme_o',
    'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Migration Bibliothek: typische_massnahmen -> massnahme_o (nur wenn massnahme_o noch leer ist)
UPDATE gefaehrdung_bibliothek
SET massnahme_o = typische_massnahmen,
    stop_o = 1
WHERE typische_massnahmen IS NOT NULL
  AND typische_massnahmen != ''
  AND (massnahme_o IS NULL OR massnahme_o = '');

-- Altes Feld in Bibliothek leeren
UPDATE gefaehrdung_bibliothek
SET typische_massnahmen = NULL
WHERE massnahme_o IS NOT NULL AND massnahme_o != '';

-- Migration Projekt-Gefährdungen: massnahmen -> massnahme_o (nur wenn massnahme_o noch leer ist)
UPDATE projekt_gefaehrdungen
SET massnahme_o = massnahmen,
    stop_o = 1
WHERE massnahmen IS NOT NULL
  AND massnahmen != ''
  AND (massnahme_o IS NULL OR massnahme_o = '');

-- Altes Feld in Projekt-Gefährdungen leeren
UPDATE projekt_gefaehrdungen
SET massnahmen = NULL
WHERE massnahme_o IS NOT NULL AND massnahme_o != '';

-- Bestätigung
SELECT 'Migration abgeschlossen' AS Status,
       (SELECT COUNT(*) FROM gefaehrdung_bibliothek WHERE massnahme_o IS NOT NULL) AS 'Bibliothek mit O-Massnahmen',
       (SELECT COUNT(*) FROM projekt_gefaehrdungen WHERE massnahme_o IS NOT NULL) AS 'Projekte mit O-Massnahmen';
