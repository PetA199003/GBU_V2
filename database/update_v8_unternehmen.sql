-- Update-Skript V8: Unternehmen für Benutzer
-- Benutzer können einem Unternehmen zugewiesen werden
-- Benutzer sehen nur Kollegen aus dem gleichen Unternehmen

USE gefaehrdungsbeurteilung;

-- ============================================
-- 1. UNTERNEHMEN TABELLE (falls nicht vorhanden)
-- ============================================

CREATE TABLE IF NOT EXISTS `firmen` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(200) NOT NULL,
    `strasse` VARCHAR(200) DEFAULT NULL,
    `plz` VARCHAR(10) DEFAULT NULL,
    `ort` VARCHAR(100) DEFAULT NULL,
    `land` VARCHAR(100) DEFAULT 'Schweiz',
    `telefon` VARCHAR(50) DEFAULT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `webseite` VARCHAR(200) DEFAULT NULL,
    `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
    `erstellt_von` INT UNSIGNED DEFAULT NULL,
    `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `aktualisiert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. BENUTZER TABELLE ERWEITERN
-- ============================================

-- Firma-ID Spalte hinzufügen
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'gefaehrdungsbeurteilung'
    AND TABLE_NAME = 'benutzer'
    AND COLUMN_NAME = 'firma_id');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE benutzer ADD COLUMN firma_id INT UNSIGNED DEFAULT NULL AFTER rolle',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index hinzufügen
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = 'gefaehrdungsbeurteilung'
    AND TABLE_NAME = 'benutzer'
    AND INDEX_NAME = 'idx_firma');
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE benutzer ADD INDEX idx_firma (firma_id)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- 3. PROJEKTE TABELLE ERWEITERN
-- ============================================

-- Firma-ID Spalte hinzufügen (Projekt gehört zu einer Firma)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'gefaehrdungsbeurteilung'
    AND TABLE_NAME = 'projekte'
    AND COLUMN_NAME = 'firma_id');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE projekte ADD COLUMN firma_id INT UNSIGNED DEFAULT NULL AFTER id',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index hinzufügen
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = 'gefaehrdungsbeurteilung'
    AND TABLE_NAME = 'projekte'
    AND INDEX_NAME = 'idx_firma');
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE projekte ADD INDEX idx_firma (firma_id)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- 4. BEISPIEL-UNTERNEHMEN EINFÜGEN
-- ============================================

INSERT IGNORE INTO `firmen` (`id`, `name`, `ort`, `land`) VALUES
(1, 'HABEGGER AG', 'Regensdorf', 'Schweiz');

-- Fertig!
SELECT 'Datenbank-Update V8 (Unternehmen) erfolgreich!' as Status;
