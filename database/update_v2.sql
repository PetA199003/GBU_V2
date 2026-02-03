-- Update-Skript V2: Erweiterte Projekt- und Gefährdungsstruktur
-- Dieses Skript erweitert das System um Berechtigungen und Standard-Gefährdungen

USE gefaehrdungsbeurteilung;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- 1. TABELLEN ANPASSEN
-- ============================================

-- Benutzer-Projekte um Berechtigung erweitern
ALTER TABLE `benutzer_projekte`
ADD COLUMN IF NOT EXISTS `berechtigung` ENUM('ansehen', 'bearbeiten') NOT NULL DEFAULT 'ansehen' AFTER `projekt_id`;

-- Falls IF NOT EXISTS nicht klappt, ignorieren wir den Fehler
-- ALTER TABLE `benutzer_projekte` ADD COLUMN `berechtigung` ENUM('ansehen', 'bearbeiten') NOT NULL DEFAULT 'ansehen' AFTER `projekt_id`;

-- ============================================
-- 2. GEFÄHRDUNGS-TAGS (für automatische Zuweisung)
-- ============================================

DROP TABLE IF EXISTS `gefaehrdung_tags`;
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

DROP TABLE IF EXISTS `gefaehrdung_bibliothek_tags`;
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

DROP TABLE IF EXISTS `projekt_tags`;
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

-- Spalte für Standard-Gefährdung hinzufügen
ALTER TABLE `gefaehrdung_bibliothek`
ADD COLUMN `ist_standard` TINYINT(1) NOT NULL DEFAULT 0 AFTER `gesetzliche_grundlage`,
ADD COLUMN `standard_schadenschwere` INT DEFAULT 2 AFTER `ist_standard`,
ADD COLUMN `standard_wahrscheinlichkeit` INT DEFAULT 2 AFTER `standard_schadenschwere`;

-- ============================================
-- 6. PROJEKT-GEFÄHRDUNGEN (zugewiesene Gefährdungen pro Projekt)
-- ============================================

DROP TABLE IF EXISTS `projekt_gefaehrdungen`;
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
    `risikobewertung` INT GENERATED ALWAYS AS (schadenschwere * schadenschwere * wahrscheinlichkeit) STORED,
    `massnahmen` TEXT DEFAULT NULL,
    `massnahmen_art` ENUM('S', 'T', 'O', 'P') DEFAULT NULL,
    `verantwortlich` VARCHAR(200) DEFAULT NULL,
    `termin` DATE DEFAULT NULL,
    `status` ENUM('offen', 'in_bearbeitung', 'erledigt') NOT NULL DEFAULT 'offen',
    `schadenschwere_nach` INT DEFAULT NULL,
    `wahrscheinlichkeit_nach` INT DEFAULT NULL,
    `risikobewertung_nach` INT GENERATED ALWAYS AS (
        CASE WHEN schadenschwere_nach IS NOT NULL AND wahrscheinlichkeit_nach IS NOT NULL
        THEN schadenschwere_nach * schadenschwere_nach * wahrscheinlichkeit_nach
        ELSE NULL END
    ) STORED,
    `erstellt_von` INT UNSIGNED DEFAULT NULL,
    `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `aktualisiert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_projekt` (`projekt_id`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_pg_projekt` FOREIGN KEY (`projekt_id`) REFERENCES `projekte` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Fertig!
SELECT 'Datenbank-Update V2 erfolgreich!' as Status;
