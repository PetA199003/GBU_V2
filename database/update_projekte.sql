-- Update-Skript: Unternehmen -> Projekte
-- Führe dieses Skript auf deiner VM aus

USE gefaehrdungsbeurteilung;

-- Foreign Key Checks deaktivieren
SET FOREIGN_KEY_CHECKS = 0;

-- Alte Tabellen löschen falls vorhanden
DROP TABLE IF EXISTS `benutzer_projekte`;
DROP TABLE IF EXISTS `projekte`;

-- Foreign Key Checks wieder aktivieren
SET FOREIGN_KEY_CHECKS = 1;

-- Neue Projekte-Tabelle erstellen

CREATE TABLE `projekte` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(200) NOT NULL,
    `location` VARCHAR(200) NOT NULL,
    `zeitraum_von` DATE NOT NULL,
    `zeitraum_bis` DATE NOT NULL,
    `aufbau_datum` DATE DEFAULT NULL,
    `abbau_datum` DATE DEFAULT NULL,
    `indoor_outdoor` ENUM('indoor', 'outdoor', 'beides') NOT NULL DEFAULT 'indoor',
    `beschreibung` TEXT DEFAULT NULL,
    `status` ENUM('geplant', 'aktiv', 'abgeschlossen') NOT NULL DEFAULT 'geplant',
    `erstellt_von` INT UNSIGNED DEFAULT NULL,
    `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `aktualisiert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_erstellt_von` (`erstellt_von`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Benutzer-Projekt-Zuweisung
CREATE TABLE `benutzer_projekte` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `benutzer_id` INT UNSIGNED NOT NULL,
    `projekt_id` INT UNSIGNED NOT NULL,
    `zugewiesen_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `zugewiesen_von` INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_benutzer_projekt` (`benutzer_id`, `projekt_id`),
    KEY `idx_projekt` (`projekt_id`),
    CONSTRAINT `fk_bp_benutzer` FOREIGN KEY (`benutzer_id`) REFERENCES `benutzer` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bp_projekt` FOREIGN KEY (`projekt_id`) REFERENCES `projekte` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Gefährdungsbeurteilungen: projekt_id Spalte hinzufügen (falls nicht vorhanden)
-- Prüfung ob Spalte existiert und hinzufügen falls nicht
SET @dbname = DATABASE();
SET @tablename = 'gefaehrdungsbeurteilungen';
SET @columnname = 'projekt_id';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
    AND TABLE_NAME = @tablename
    AND COLUMN_NAME = @columnname
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' INT UNSIGNED DEFAULT NULL AFTER id')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Index hinzufügen (ignoriert Fehler falls bereits vorhanden)
ALTER TABLE `gefaehrdungsbeurteilungen` ADD INDEX `idx_projekt` (`projekt_id`);

-- Fertig!
SELECT 'Datenbank-Update erfolgreich!' as Status;
