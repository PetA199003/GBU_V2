-- Update-Skript V4: Bibliothek-Tabelle erweitern
-- Fügt STOP-Felder, Gefährdungsart und Kategorien zur Bibliothek hinzu

USE gefaehrdungsbeurteilung;

-- ============================================
-- GEFÄHRDUNG_BIBLIOTHEK ERWEITERN
-- ============================================

-- Prüfen und Spalten hinzufügen (MySQL-kompatibel ohne IF NOT EXISTS)

-- STOP-Prinzip Felder
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'gefaehrdungsbeurteilung'
    AND TABLE_NAME = 'gefaehrdung_bibliothek'
    AND COLUMN_NAME = 'stop_s');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE gefaehrdung_bibliothek ADD COLUMN stop_s TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'gefaehrdungsbeurteilung'
    AND TABLE_NAME = 'gefaehrdung_bibliothek'
    AND COLUMN_NAME = 'stop_t');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE gefaehrdung_bibliothek ADD COLUMN stop_t TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'gefaehrdungsbeurteilung'
    AND TABLE_NAME = 'gefaehrdung_bibliothek'
    AND COLUMN_NAME = 'stop_o');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE gefaehrdung_bibliothek ADD COLUMN stop_o TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'gefaehrdungsbeurteilung'
    AND TABLE_NAME = 'gefaehrdung_bibliothek'
    AND COLUMN_NAME = 'stop_p');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE gefaehrdung_bibliothek ADD COLUMN stop_p TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Gefährdungsart-ID
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'gefaehrdungsbeurteilung'
    AND TABLE_NAME = 'gefaehrdung_bibliothek'
    AND COLUMN_NAME = 'gefaehrdungsart_id');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE gefaehrdung_bibliothek ADD COLUMN gefaehrdungsart_id INT UNSIGNED DEFAULT NULL',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Kategorie-ID
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'gefaehrdungsbeurteilung'
    AND TABLE_NAME = 'gefaehrdung_bibliothek'
    AND COLUMN_NAME = 'kategorie_id');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE gefaehrdung_bibliothek ADD COLUMN kategorie_id INT UNSIGNED DEFAULT NULL',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Unterkategorie-ID
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'gefaehrdungsbeurteilung'
    AND TABLE_NAME = 'gefaehrdung_bibliothek'
    AND COLUMN_NAME = 'unterkategorie_id');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE gefaehrdung_bibliothek ADD COLUMN unterkategorie_id INT UNSIGNED DEFAULT NULL',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Fertig!
SELECT 'Datenbank-Update V4 (Bibliothek-Erweiterung) erfolgreich!' as Status;
