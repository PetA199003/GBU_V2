-- Update-Skript V2 (Einfache Version ohne IF NOT EXISTS)
-- Für ältere MySQL-Versionen

USE gefaehrdungsbeurteilung;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- 1. ALTE TABELLEN LÖSCHEN (falls vorhanden)
-- ============================================

DROP TABLE IF EXISTS `gefaehrdung_bibliothek_tags`;
DROP TABLE IF EXISTS `projekt_tags`;
DROP TABLE IF EXISTS `projekt_gefaehrdungen`;
DROP TABLE IF EXISTS `gefaehrdung_tags`;

-- ============================================
-- 2. GEFÄHRDUNGS-TAGS (für automatische Zuweisung)
-- ============================================

CREATE TABLE `gefaehrdung_tags` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `beschreibung` VARCHAR(255) DEFAULT NULL,
    `farbe` VARCHAR(7) DEFAULT '#6c757d',
    `sortierung` INT DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Standard-Tags einfügen
INSERT INTO `gefaehrdung_tags` (`name`, `beschreibung`, `farbe`, `sortierung`) VALUES
('indoor', 'Gilt für Indoor-Veranstaltungen', '#0d6efd', 1),
('outdoor', 'Gilt für Outdoor-Veranstaltungen', '#198754', 2),
('stapler', 'Bei Einsatz von Gabelstaplern', '#ffc107', 3),
('scheren_arbeitsbuehne', 'Bei Einsatz von Scherenbühnen', '#fd7e14', 4),
('arbeiten_hoehe', 'Arbeiten in der Höhe', '#dc3545', 5),
('elektro', 'Elektrische Arbeiten', '#6f42c1', 6),
('schwere_lasten', 'Arbeiten mit schweren Lasten', '#20c997', 7),
('aufbau', 'Nur während Aufbauphase', '#17a2b8', 8),
('abbau', 'Nur während Abbauphase', '#6c757d', 9),
('standard', 'Wird immer hinzugefügt', '#212529', 0);

-- ============================================
-- 3. GEFÄHRDUNG-TAG VERKNÜPFUNG
-- ============================================

CREATE TABLE `gefaehrdung_bibliothek_tags` (
    `gefaehrdung_id` INT UNSIGNED NOT NULL,
    `tag_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`gefaehrdung_id`, `tag_id`),
    CONSTRAINT `fk_gbt_gefaehrdung` FOREIGN KEY (`gefaehrdung_id`) REFERENCES `gefaehrdung_bibliothek` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_gbt_tag` FOREIGN KEY (`tag_id`) REFERENCES `gefaehrdung_tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. PROJEKT-TAGS (welche Bedingungen gelten)
-- ============================================

CREATE TABLE `projekt_tags` (
    `projekt_id` INT UNSIGNED NOT NULL,
    `tag_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`projekt_id`, `tag_id`),
    CONSTRAINT `fk_pt_projekt` FOREIGN KEY (`projekt_id`) REFERENCES `projekte` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pt_tag` FOREIGN KEY (`tag_id`) REFERENCES `gefaehrdung_tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. GEFÄHRDUNG_BIBLIOTHEK ERWEITERN
-- ============================================

-- Neue Spalten zur gefaehrdung_bibliothek hinzufügen
-- Fehler werden ignoriert falls Spalten schon existieren
ALTER TABLE `gefaehrdung_bibliothek` ADD COLUMN `ist_standard` TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `gefaehrdung_bibliothek` ADD COLUMN `standard_schadenschwere` INT DEFAULT 2;
ALTER TABLE `gefaehrdung_bibliothek` ADD COLUMN `standard_wahrscheinlichkeit` INT DEFAULT 2;

-- ============================================
-- 6. PROJEKT-GEFÄHRDUNGEN (zugewiesene Gefährdungen pro Projekt)
-- ============================================

CREATE TABLE `projekt_gefaehrdungen` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `projekt_id` INT UNSIGNED NOT NULL,
    `gefaehrdung_bibliothek_id` INT UNSIGNED DEFAULT NULL,
    `titel` VARCHAR(255) NOT NULL,
    `beschreibung` TEXT NOT NULL,
    `kategorie_id` INT UNSIGNED DEFAULT NULL,
    `faktor_id` INT UNSIGNED DEFAULT NULL,
    `schadenschwere` INT NOT NULL DEFAULT 2,
    `wahrscheinlichkeit` INT NOT NULL DEFAULT 2,
    `risikobewertung` INT DEFAULT NULL,
    `massnahmen` TEXT DEFAULT NULL,
    `massnahmen_art` ENUM('S', 'T', 'O', 'P') DEFAULT NULL,
    `verantwortlich` VARCHAR(200) DEFAULT NULL,
    `termin` DATE DEFAULT NULL,
    `status` ENUM('offen', 'in_bearbeitung', 'erledigt') NOT NULL DEFAULT 'offen',
    `schadenschwere_nach` INT DEFAULT NULL,
    `wahrscheinlichkeit_nach` INT DEFAULT NULL,
    `risikobewertung_nach` INT DEFAULT NULL,
    `erstellt_von` INT UNSIGNED DEFAULT NULL,
    `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `aktualisiert_am` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_projekt` (`projekt_id`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_pg_projekt` FOREIGN KEY (`projekt_id`) REFERENCES `projekte` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Trigger für Risikobewertung
DELIMITER //
CREATE TRIGGER trg_projekt_gef_insert BEFORE INSERT ON projekt_gefaehrdungen
FOR EACH ROW
BEGIN
    SET NEW.risikobewertung = NEW.schadenschwere * NEW.schadenschwere * NEW.wahrscheinlichkeit;
    IF NEW.schadenschwere_nach IS NOT NULL AND NEW.wahrscheinlichkeit_nach IS NOT NULL THEN
        SET NEW.risikobewertung_nach = NEW.schadenschwere_nach * NEW.schadenschwere_nach * NEW.wahrscheinlichkeit_nach;
    END IF;
END//

CREATE TRIGGER trg_projekt_gef_update BEFORE UPDATE ON projekt_gefaehrdungen
FOR EACH ROW
BEGIN
    SET NEW.risikobewertung = NEW.schadenschwere * NEW.schadenschwere * NEW.wahrscheinlichkeit;
    IF NEW.schadenschwere_nach IS NOT NULL AND NEW.wahrscheinlichkeit_nach IS NOT NULL THEN
        SET NEW.risikobewertung_nach = NEW.schadenschwere_nach * NEW.schadenschwere_nach * NEW.wahrscheinlichkeit_nach;
    ELSE
        SET NEW.risikobewertung_nach = NULL;
    END IF;
END//
DELIMITER ;

-- ============================================
-- 7. BENUTZER_PROJEKTE BERECHTIGUNG
-- ============================================

-- Spalte berechtigung hinzufügen (Fehler ignorieren falls existiert)
ALTER TABLE `benutzer_projekte` ADD COLUMN `berechtigung` ENUM('ansehen', 'bearbeiten') NOT NULL DEFAULT 'ansehen';

SET FOREIGN_KEY_CHECKS = 1;

-- Fertig!
SELECT 'Datenbank-Update V2 erfolgreich!' as Status;
