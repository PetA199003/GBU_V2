-- Update-Skript V5: Bibliothek-Tabelle um Maßnahmen-Felder erweitern
-- Fügt Verantwortlich und Risiko nach Maßnahmen hinzu

USE gefaehrdungsbeurteilung;

-- ============================================
-- GEFÄHRDUNG_BIBLIOTHEK ERWEITERN
-- ============================================

-- Verantwortlich
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'gefaehrdungsbeurteilung'
    AND TABLE_NAME = 'gefaehrdung_bibliothek'
    AND COLUMN_NAME = 'verantwortlich');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE gefaehrdung_bibliothek ADD COLUMN verantwortlich VARCHAR(255) DEFAULT NULL',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Schadenschwere nach Maßnahmen
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'gefaehrdungsbeurteilung'
    AND TABLE_NAME = 'gefaehrdung_bibliothek'
    AND COLUMN_NAME = 'schadenschwere_nachher');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE gefaehrdung_bibliothek ADD COLUMN schadenschwere_nachher TINYINT UNSIGNED DEFAULT NULL',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Wahrscheinlichkeit nach Maßnahmen
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'gefaehrdungsbeurteilung'
    AND TABLE_NAME = 'gefaehrdung_bibliothek'
    AND COLUMN_NAME = 'wahrscheinlichkeit_nachher');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE gefaehrdung_bibliothek ADD COLUMN wahrscheinlichkeit_nachher TINYINT UNSIGNED DEFAULT NULL',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Fertig!
SELECT 'Datenbank-Update V5 (Bibliothek Maßnahmen-Erweiterung) erfolgreich!' as Status;
