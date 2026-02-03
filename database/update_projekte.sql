-- Update-Skript: Unternehmen -> Projekte
-- Führe dieses Skript auf deiner VM aus

USE gefaehrdungsbeurteilung;

-- Neue Projekte-Tabelle erstellen
DROP TABLE IF EXISTS `projekte`;

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
DROP TABLE IF EXISTS `benutzer_projekte`;

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
ALTER TABLE `gefaehrdungsbeurteilungen`
ADD COLUMN IF NOT EXISTS `projekt_id` INT UNSIGNED DEFAULT NULL AFTER `id`;

-- Index hinzufügen
ALTER TABLE `gefaehrdungsbeurteilungen`
ADD INDEX IF NOT EXISTS `idx_projekt` (`projekt_id`);

-- Fertig!
SELECT 'Datenbank-Update erfolgreich!' as Status;
