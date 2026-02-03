-- Update-Skript V3: Neue Gefährdungsstruktur
-- Gefährdungen = Feste 13 Kategorien
-- Kategorien = Vom Benutzer erstellbar (1. Allgemein, 2. Be- und Entladen, etc.)
-- Unterkategorien = z.B. 2.1 Entladen über Rampe

USE gefaehrdungsbeurteilung;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- 1. GEFÄHRDUNGSARTEN (Die 13 festen Kategorien)
-- ============================================

DROP TABLE IF EXISTS `gefaehrdungsarten`;
CREATE TABLE `gefaehrdungsarten` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nummer` INT NOT NULL,
    `name` VARCHAR(200) NOT NULL,
    `beschreibung` TEXT DEFAULT NULL,
    `sortierung` INT DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Die 13 festen Gefährdungsarten einfügen
INSERT INTO `gefaehrdungsarten` (`nummer`, `name`, `beschreibung`, `sortierung`) VALUES
(1, 'Mechanische Gefährdung', 'Gefährdungen durch bewegte Teile, Quetschen, Stoßen, etc.', 1),
(2, 'Sturzgefahr', 'Stolpern, Rutschen, Stürzen', 2),
(3, 'Elektrische Gefahren', 'Gefährdungen durch elektrischen Strom', 3),
(4, 'Gesundheitsgefährdende Stoffe (chemische/biologische)', 'Gefahrstoffe, biologische Arbeitsstoffe', 4),
(5, 'Brand- und Explosionsgefahren', 'Brandgefahr, Explosionsgefahr', 5),
(6, 'Thermische Gefährdung', 'Hitze, Kälte, heiße/kalte Oberflächen', 6),
(7, 'Spezielle physikalische Belastungen', 'Lärm, Vibration, Strahlung', 7),
(8, 'Belastungen durch Arbeitsumgebungsbedingungen', 'Klima, Beleuchtung, Platzverhältnisse', 8),
(9, 'Belastungen am Bewegungsapparat', 'Heben, Tragen, Zwangshaltungen', 9),
(10, 'Psychische Belastungen', 'Stress, Zeitdruck, Überforderung', 10),
(11, 'Unerwartete Aktionen', 'Unvorhergesehene Ereignisse, Fehlverhalten', 11),
(12, 'Ausfall Energieversorgung', 'Stromausfall, Versorgungsunterbrechung', 12),
(13, 'Arbeitsorganisation', 'Organisatorische Mängel, Kommunikation', 13);

-- ============================================
-- 2. ARBEITS-KATEGORIEN (Vom Benutzer erstellbar)
-- ============================================

DROP TABLE IF EXISTS `arbeits_unterkategorien`;
DROP TABLE IF EXISTS `arbeits_kategorien`;

CREATE TABLE `arbeits_kategorien` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `projekt_id` INT UNSIGNED DEFAULT NULL,
    `nummer` INT NOT NULL,
    `name` VARCHAR(200) NOT NULL,
    `beschreibung` TEXT DEFAULT NULL,
    `ist_global` TINYINT(1) NOT NULL DEFAULT 0,
    `erstellt_von` INT UNSIGNED DEFAULT NULL,
    `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_projekt` (`projekt_id`),
    KEY `idx_global` (`ist_global`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Standard-Kategorien (global verfügbar)
INSERT INTO `arbeits_kategorien` (`nummer`, `name`, `ist_global`) VALUES
(1, 'Allgemein', 1),
(2, 'Be- und Entladen', 1),
(3, 'Licht', 1),
(4, 'Ton', 1),
(5, 'Rigging', 1),
(6, 'Bühnenbau', 1),
(7, 'Video/LED', 1),
(8, 'Strom/Elektrik', 1),
(9, 'Dekoration', 1),
(10, 'Catering', 1);

-- ============================================
-- 3. ARBEITS-UNTERKATEGORIEN (z.B. 2.1 Entladen über Rampe)
-- ============================================

CREATE TABLE `arbeits_unterkategorien` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kategorie_id` INT UNSIGNED NOT NULL,
    `nummer` INT NOT NULL,
    `name` VARCHAR(200) NOT NULL,
    `beschreibung` TEXT DEFAULT NULL,
    `erstellt_von` INT UNSIGNED DEFAULT NULL,
    `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_kategorie` (`kategorie_id`),
    CONSTRAINT `fk_uk_kategorie` FOREIGN KEY (`kategorie_id`) REFERENCES `arbeits_kategorien` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Beispiel-Unterkategorien
INSERT INTO `arbeits_unterkategorien` (`kategorie_id`, `nummer`, `name`) VALUES
((SELECT id FROM arbeits_kategorien WHERE name = 'Be- und Entladen' LIMIT 1), 1, 'Entladen über Rampe'),
((SELECT id FROM arbeits_kategorien WHERE name = 'Be- und Entladen' LIMIT 1), 2, 'Beladen LKW'),
((SELECT id FROM arbeits_kategorien WHERE name = 'Be- und Entladen' LIMIT 1), 3, 'Transport zum Lager'),
((SELECT id FROM arbeits_kategorien WHERE name = 'Rigging' LIMIT 1), 1, 'Arbeiten auf Truss'),
((SELECT id FROM arbeits_kategorien WHERE name = 'Rigging' LIMIT 1), 2, 'Motorensteuerung'),
((SELECT id FROM arbeits_kategorien WHERE name = 'Bühnenbau' LIMIT 1), 1, 'Podeste aufbauen'),
((SELECT id FROM arbeits_kategorien WHERE name = 'Bühnenbau' LIMIT 1), 2, 'Geländer montieren');

-- ============================================
-- 4. PROJEKT_GEFAEHRDUNGEN ANPASSEN
-- ============================================

-- Alte Tabelle droppen und neu erstellen
DROP TABLE IF EXISTS `projekt_gefaehrdungen`;

CREATE TABLE `projekt_gefaehrdungen` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `projekt_id` INT UNSIGNED NOT NULL,
    `gefaehrdung_bibliothek_id` INT UNSIGNED DEFAULT NULL,

    -- Neue Struktur
    `gefaehrdungsart_id` INT UNSIGNED DEFAULT NULL,
    `kategorie_id` INT UNSIGNED DEFAULT NULL,
    `unterkategorie_id` INT UNSIGNED DEFAULT NULL,

    -- Gefährdung
    `titel` VARCHAR(255) NOT NULL,
    `beschreibung` TEXT NOT NULL,

    -- Risikobewertung VORHER
    `schadenschwere` INT NOT NULL DEFAULT 2,
    `wahrscheinlichkeit` INT NOT NULL DEFAULT 2,
    `risikobewertung` INT DEFAULT NULL,

    -- Maßnahmen (STOP-Prinzip als Mehrfachauswahl)
    `stop_s` TINYINT(1) NOT NULL DEFAULT 0,
    `stop_t` TINYINT(1) NOT NULL DEFAULT 0,
    `stop_o` TINYINT(1) NOT NULL DEFAULT 0,
    `stop_p` TINYINT(1) NOT NULL DEFAULT 0,
    `massnahmen` TEXT DEFAULT NULL,
    `gegenmassnahmen` TEXT DEFAULT NULL,
    `verantwortlich` VARCHAR(200) DEFAULT NULL,
    `termin` DATE DEFAULT NULL,

    -- Risikobewertung NACHHER
    `schadenschwere_nach` INT DEFAULT NULL,
    `wahrscheinlichkeit_nach` INT DEFAULT NULL,
    `risikobewertung_nach` INT DEFAULT NULL,

    -- Meta
    `erstellt_von` INT UNSIGNED DEFAULT NULL,
    `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `aktualisiert_am` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_projekt` (`projekt_id`),
    KEY `idx_gefaehrdungsart` (`gefaehrdungsart_id`),
    KEY `idx_kategorie` (`kategorie_id`),
    CONSTRAINT `fk_pg_projekt` FOREIGN KEY (`projekt_id`) REFERENCES `projekte` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pg_gefaehrdungsart` FOREIGN KEY (`gefaehrdungsart_id`) REFERENCES `gefaehrdungsarten` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_pg_kategorie` FOREIGN KEY (`kategorie_id`) REFERENCES `arbeits_kategorien` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_pg_unterkategorie` FOREIGN KEY (`unterkategorie_id`) REFERENCES `arbeits_unterkategorien` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Trigger für Risikobewertung
DROP TRIGGER IF EXISTS trg_projekt_gef_insert;
DROP TRIGGER IF EXISTS trg_projekt_gef_update;

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

SET FOREIGN_KEY_CHECKS = 1;

-- Fertig!
SELECT 'Datenbank-Update V3 (Struktur) erfolgreich!' as Status;
