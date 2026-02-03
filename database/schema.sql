-- Gefährdungsbeurteilung Datenbank-Schema
-- Erstellt für MySQL 5.7+

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Datenbank erstellen
CREATE DATABASE IF NOT EXISTS `gefaehrdungsbeurteilung`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `gefaehrdungsbeurteilung`;

-- --------------------------------------------------------
-- Tabelle: benutzer
-- --------------------------------------------------------
DROP TABLE IF EXISTS `benutzer`;
CREATE TABLE `benutzer` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `benutzername` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `passwort` VARCHAR(255) NOT NULL,
    `vorname` VARCHAR(50) NOT NULL,
    `nachname` VARCHAR(50) NOT NULL,
    `rolle` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=Betrachter, 2=Bearbeiter, 3=Admin',
    `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
    `letzter_login` DATETIME DEFAULT NULL,
    `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `aktualisiert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_benutzername` (`benutzername`),
    UNIQUE KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabelle: unternehmen (für Multi-Mandanten-Fähigkeit)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `unternehmen`;
CREATE TABLE `unternehmen` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(200) NOT NULL,
    `strasse` VARCHAR(200) DEFAULT NULL,
    `plz` VARCHAR(10) DEFAULT NULL,
    `ort` VARCHAR(100) DEFAULT NULL,
    `telefon` VARCHAR(50) DEFAULT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `erstellt_von` INT UNSIGNED DEFAULT NULL,
    `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `aktualisiert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_erstellt_von` (`erstellt_von`),
    CONSTRAINT `fk_unternehmen_benutzer` FOREIGN KEY (`erstellt_von`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabelle: arbeitsbereiche
-- --------------------------------------------------------
DROP TABLE IF EXISTS `arbeitsbereiche`;
CREATE TABLE `arbeitsbereiche` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `unternehmen_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(200) NOT NULL,
    `beschreibung` TEXT DEFAULT NULL,
    `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `aktualisiert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_unternehmen` (`unternehmen_id`),
    CONSTRAINT `fk_arbeitsbereich_unternehmen` FOREIGN KEY (`unternehmen_id`) REFERENCES `unternehmen` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabelle: gefaehrdungsbeurteilungen
-- --------------------------------------------------------
DROP TABLE IF EXISTS `gefaehrdungsbeurteilungen`;
CREATE TABLE `gefaehrdungsbeurteilungen` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `unternehmen_id` INT UNSIGNED NOT NULL,
    `arbeitsbereich_id` INT UNSIGNED DEFAULT NULL,
    `titel` VARCHAR(200) NOT NULL,
    `ersteller_name` VARCHAR(100) NOT NULL,
    `erstelldatum` DATE NOT NULL,
    `ueberarbeitungsdatum` DATE DEFAULT NULL,
    `status` ENUM('entwurf', 'aktiv', 'archiviert') NOT NULL DEFAULT 'entwurf',
    `bemerkungen` TEXT DEFAULT NULL,
    `erstellt_von` INT UNSIGNED DEFAULT NULL,
    `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `aktualisiert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_unternehmen` (`unternehmen_id`),
    KEY `idx_arbeitsbereich` (`arbeitsbereich_id`),
    KEY `idx_erstellt_von` (`erstellt_von`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_gb_unternehmen` FOREIGN KEY (`unternehmen_id`) REFERENCES `unternehmen` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_gb_arbeitsbereich` FOREIGN KEY (`arbeitsbereich_id`) REFERENCES `arbeitsbereiche` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_gb_benutzer` FOREIGN KEY (`erstellt_von`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabelle: gefaehrdung_kategorien
-- --------------------------------------------------------
DROP TABLE IF EXISTS `gefaehrdung_kategorien`;
CREATE TABLE `gefaehrdung_kategorien` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nummer` VARCHAR(10) NOT NULL,
    `name` VARCHAR(200) NOT NULL,
    `beschreibung` TEXT DEFAULT NULL,
    `sortierung` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_nummer` (`nummer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabelle: gefaehrdung_faktoren (Gefährdungs- und Belastungsfaktoren)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `gefaehrdung_faktoren`;
CREATE TABLE `gefaehrdung_faktoren` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kategorie_id` INT UNSIGNED NOT NULL,
    `nummer` VARCHAR(20) NOT NULL,
    `name` VARCHAR(200) NOT NULL,
    `beschreibung` TEXT DEFAULT NULL,
    `sortierung` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_kategorie` (`kategorie_id`),
    CONSTRAINT `fk_faktor_kategorie` FOREIGN KEY (`kategorie_id`) REFERENCES `gefaehrdung_kategorien` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabelle: taetigkeiten
-- --------------------------------------------------------
DROP TABLE IF EXISTS `taetigkeiten`;
CREATE TABLE `taetigkeiten` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `gefaehrdungsbeurteilung_id` INT UNSIGNED NOT NULL,
    `position` VARCHAR(20) NOT NULL,
    `name` VARCHAR(200) NOT NULL,
    `beschreibung` TEXT DEFAULT NULL,
    `sortierung` INT NOT NULL DEFAULT 0,
    `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `aktualisiert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_gb` (`gefaehrdungsbeurteilung_id`),
    CONSTRAINT `fk_taetigkeit_gb` FOREIGN KEY (`gefaehrdungsbeurteilung_id`) REFERENCES `gefaehrdungsbeurteilungen` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabelle: vorgaenge (einzelne Gefährdungseinträge)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `vorgaenge`;
CREATE TABLE `vorgaenge` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `taetigkeit_id` INT UNSIGNED NOT NULL,
    `position` VARCHAR(20) NOT NULL,
    `vorgang_beschreibung` VARCHAR(500) NOT NULL,
    `gefaehrdung` TEXT NOT NULL,
    `gefaehrdung_faktor_id` INT UNSIGNED DEFAULT NULL,
    `schadenschwere` TINYINT UNSIGNED NOT NULL CHECK (`schadenschwere` BETWEEN 1 AND 3),
    `wahrscheinlichkeit` TINYINT UNSIGNED NOT NULL CHECK (`wahrscheinlichkeit` BETWEEN 1 AND 3),
    `risikobewertung` TINYINT UNSIGNED GENERATED ALWAYS AS (`schadenschwere` * `schadenschwere` * `wahrscheinlichkeit`) STORED,
    `stop_s` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Substitution',
    `stop_t` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Technisch',
    `stop_o` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Organisatorisch',
    `stop_p` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Persönlich',
    `massnahmen` TEXT DEFAULT NULL,
    `massnahme_schadenschwere` TINYINT UNSIGNED DEFAULT NULL CHECK (`massnahme_schadenschwere` BETWEEN 1 AND 3),
    `massnahme_wahrscheinlichkeit` TINYINT UNSIGNED DEFAULT NULL CHECK (`massnahme_wahrscheinlichkeit` BETWEEN 1 AND 3),
    `massnahme_risikobewertung` TINYINT UNSIGNED GENERATED ALWAYS AS (`massnahme_schadenschwere` * `massnahme_schadenschwere` * `massnahme_wahrscheinlichkeit`) STORED,
    `sonstige_bemerkungen` TEXT DEFAULT NULL,
    `gesetzliche_regelungen` TEXT DEFAULT NULL,
    `maengel_behoben_von` VARCHAR(100) DEFAULT NULL,
    `maengel_behoben_am` DATE DEFAULT NULL,
    `sortierung` INT NOT NULL DEFAULT 0,
    `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `aktualisiert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_taetigkeit` (`taetigkeit_id`),
    KEY `idx_faktor` (`gefaehrdung_faktor_id`),
    KEY `idx_risiko` (`risikobewertung`),
    CONSTRAINT `fk_vorgang_taetigkeit` FOREIGN KEY (`taetigkeit_id`) REFERENCES `taetigkeiten` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_vorgang_faktor` FOREIGN KEY (`gefaehrdung_faktor_id`) REFERENCES `gefaehrdung_faktoren` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabelle: massnahmen_bibliothek (wiederverwendbare Maßnahmen)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `massnahmen_bibliothek`;
CREATE TABLE `massnahmen_bibliothek` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kategorie_id` INT UNSIGNED DEFAULT NULL,
    `titel` VARCHAR(200) NOT NULL,
    `beschreibung` TEXT NOT NULL,
    `stop_typ` ENUM('S', 'T', 'O', 'P') NOT NULL,
    `gesetzliche_grundlage` TEXT DEFAULT NULL,
    `erstellt_von` INT UNSIGNED DEFAULT NULL,
    `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `aktualisiert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_kategorie` (`kategorie_id`),
    KEY `idx_stop_typ` (`stop_typ`),
    CONSTRAINT `fk_massnahme_kategorie` FOREIGN KEY (`kategorie_id`) REFERENCES `gefaehrdung_kategorien` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_massnahme_benutzer` FOREIGN KEY (`erstellt_von`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabelle: gefaehrdung_bibliothek (wiederverwendbare Gefährdungen)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `gefaehrdung_bibliothek`;
CREATE TABLE `gefaehrdung_bibliothek` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kategorie_id` INT UNSIGNED DEFAULT NULL,
    `faktor_id` INT UNSIGNED DEFAULT NULL,
    `titel` VARCHAR(200) NOT NULL,
    `beschreibung` TEXT NOT NULL,
    `typische_massnahmen` TEXT DEFAULT NULL,
    `gesetzliche_grundlage` TEXT DEFAULT NULL,
    `erstellt_von` INT UNSIGNED DEFAULT NULL,
    `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `aktualisiert_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_kategorie` (`kategorie_id`),
    KEY `idx_faktor` (`faktor_id`),
    CONSTRAINT `fk_gefbib_kategorie` FOREIGN KEY (`kategorie_id`) REFERENCES `gefaehrdung_kategorien` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_gefbib_faktor` FOREIGN KEY (`faktor_id`) REFERENCES `gefaehrdung_faktoren` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_gefbib_benutzer` FOREIGN KEY (`erstellt_von`) REFERENCES `benutzer` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabelle: audit_log (Änderungsprotokoll)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE `audit_log` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `benutzer_id` INT UNSIGNED DEFAULT NULL,
    `aktion` VARCHAR(50) NOT NULL,
    `tabelle` VARCHAR(50) NOT NULL,
    `datensatz_id` INT UNSIGNED NOT NULL,
    `alte_werte` JSON DEFAULT NULL,
    `neue_werte` JSON DEFAULT NULL,
    `ip_adresse` VARCHAR(45) DEFAULT NULL,
    `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_benutzer` (`benutzer_id`),
    KEY `idx_tabelle_datensatz` (`tabelle`, `datensatz_id`),
    KEY `idx_erstellt_am` (`erstellt_am`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Standarddaten einfügen
-- --------------------------------------------------------

-- Admin-Benutzer (Passwort: admin123)
INSERT INTO `benutzer` (`benutzername`, `email`, `passwort`, `vorname`, `nachname`, `rolle`) VALUES
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System', 'Administrator', 3);

-- Gefährdungskategorien nach TRBS/TRGS
INSERT INTO `gefaehrdung_kategorien` (`nummer`, `name`, `beschreibung`, `sortierung`) VALUES
('1', 'Mechanische Gefährdungen', 'Gefährdungen durch bewegte Maschinenteile, Transportmittel, etc.', 1),
('2', 'Elektrische Gefährdungen', 'Gefährdungen durch elektrischen Strom', 2),
('3', 'Gefahrstoffe', 'Gefährdungen durch gefährliche Stoffe', 3),
('4', 'Biologische Arbeitsstoffe', 'Gefährdungen durch biologische Einwirkungen', 4),
('5', 'Brand- und Explosionsgefährdungen', 'Gefährdungen durch Brände und Explosionen', 5),
('6', 'Thermische Gefährdungen', 'Gefährdungen durch Hitze und Kälte', 6),
('7', 'Gefährdungen durch spezielle physikalische Einwirkungen', 'Lärm, Vibration, Strahlung, etc.', 7),
('8', 'Gefährdungen durch Arbeitsumgebungsbedingungen', 'Beleuchtung, Klima, Raumbedarf, etc.', 8),
('9', 'Physische Belastung/Arbeitsschwere', 'Körperliche Belastungen', 9),
('10', 'Psychische Faktoren', 'Psychische Belastungen am Arbeitsplatz', 10),
('11', 'Sonstige Gefährdungen', 'Weitere nicht kategorisierte Gefährdungen', 11);

-- Beispiel-Gefährdungsfaktoren für Kategorie 9 (Physische Belastung)
INSERT INTO `gefaehrdung_faktoren` (`kategorie_id`, `nummer`, `name`, `beschreibung`, `sortierung`) VALUES
((SELECT id FROM gefaehrdung_kategorien WHERE nummer = '9'), '9.1', 'Schwere dynamische Arbeit', 'Heben, Tragen, Bewegen von Lasten', 1),
((SELECT id FROM gefaehrdung_kategorien WHERE nummer = '9'), '9.2', 'Einseitig dynamische Arbeit', 'Repetitive Tätigkeiten', 2),
((SELECT id FROM gefaehrdung_kategorien WHERE nummer = '9'), '9.3', 'Haltungsarbeit/Haltearbeit', 'Statische Körperhaltungen', 3),
((SELECT id FROM gefaehrdung_kategorien WHERE nummer = '9'), '9.4', 'Kombination aus statischer und dynamischer Arbeit', 'Gemischte Belastungen', 4);

-- Beispiel-Gefährdungsfaktoren für Kategorie 8 (Arbeitsumgebungsbedingungen)
INSERT INTO `gefaehrdung_faktoren` (`kategorie_id`, `nummer`, `name`, `beschreibung`, `sortierung`) VALUES
((SELECT id FROM gefaehrdung_kategorien WHERE nummer = '8'), '8.1', 'Klima', 'Temperatur, Luftfeuchtigkeit, Luftbewegung', 1),
((SELECT id FROM gefaehrdung_kategorien WHERE nummer = '8'), '8.2', 'Beleuchtung', 'Unzureichende oder blendende Beleuchtung', 2),
((SELECT id FROM gefaehrdung_kategorien WHERE nummer = '8'), '8.3', 'Raum, Fläche', 'Beengte Verhältnisse, Verkehrswege', 3),
((SELECT id FROM gefaehrdung_kategorien WHERE nummer = '8'), '8.4', 'Sonstiges zur Arbeitsumgebung', 'Weitere Umgebungsfaktoren', 4);

-- Beispiel-Maßnahmen für die Bibliothek
INSERT INTO `massnahmen_bibliothek` (`kategorie_id`, `titel`, `beschreibung`, `stop_typ`, `gesetzliche_grundlage`, `erstellt_von`) VALUES
((SELECT id FROM gefaehrdung_kategorien WHERE nummer = '9'),
'Ergonomische Bürostühle',
'Austauschen und gegen ergonomische Bürostühle ersetzen: Regulierbare bzw. arretierbare Rückenlehne mit Lordosestütze und verstellbarem Gegendruck für das dynamische Sitzen, in Weite und Höhe verstellbare Armlehnen, Sitztiefen- und Sitzneigungsverstellbarkeit.',
'T',
'ArbSchG, ArbStättV, DGUV Information 215-410 "Leitfaden für die Gestaltung von Bildschirmarbeitsplätzen"',
1),

((SELECT id FROM gefaehrdung_kategorien WHERE nummer = '9'),
'Rückenschule anbieten',
'Rückenschule für Mitarbeiter anbieten zur Prävention von Rückenbeschwerden und Förderung der richtigen Körperhaltung.',
'O',
'ArbSchG',
1),

((SELECT id FROM gefaehrdung_kategorien WHERE nummer = '8'),
'Ausreichende Bürobeleuchtung',
'Austauschen der Bürolampen > 500 Lux bei besonderen Aufgaben, wie z.B. Handzeichnen oder Lesen von Papiervorlagen, 300 Lux für die Arbeitsplatzumgebung.',
'T',
'Technische Regel ASR A3.4 - "Beleuchtung"',
1),

((SELECT id FROM gefaehrdung_kategorien WHERE nummer = '8'),
'Zusätzliche Schreibtischlampe',
'Bereitstellung zusätzlicher Schreibtischlampen für individuelle Beleuchtungsanforderungen.',
'T',
'Technische Regel ASR A3.4 - "Beleuchtung"',
1),

((SELECT id FROM gefaehrdung_kategorien WHERE nummer = '8'),
'Einbau einer Klimaanlage',
'Eine angenehme Raumtemperatur liegt bei 20 bis 22° Celsius. In den Sommermonaten gilt eine Obergrenze von 26° Celsius.',
'T',
'Technische Regel für Arbeitsstätten - ASR A 3.5 - Raumtemperatur, ArbStättV',
1),

((SELECT id FROM gefaehrdung_kategorien WHERE nummer = '8'),
'Regelmäßiges Lüften',
'Regelmäßiges Lüften zur Verbesserung der Raumluftqualität und Temperaturregulierung.',
'O',
'ASR A3.6',
1),

((SELECT id FROM gefaehrdung_kategorien WHERE nummer = '9'),
'Bildschirmgröße anpassen',
'Bildschirmgröße und Format passend zur Arbeitsaufgabe auswählen bzw. bereitstellen. 19 Zoll, ca. 48 cm Bildschirmdiagonale für Büroanwendungen. Stand der Technik für Büroarbeit sind Bilddiagonalen von 21 Zoll und mehr im Normalformat, Grafik- und Multimediaanwendungen oder große Tabellen erfordern größere Bildschirme.',
'T',
'ArbSchG, ArbStättV, DGUV Information 215-410 "Leitfaden für die Gestaltung von Bildschirmarbeitsplätzen"',
1),

((SELECT id FROM gefaehrdung_kategorien WHERE nummer = '9'),
'Bildschirmbrille bereitstellen',
'Bei Bedarf Bildschirmbrille für Mitarbeiter bereitstellen, um Augenbelastung zu reduzieren.',
'P',
'ArbMedVV, DGUV Information 215-410',
1);

-- Beispiel Gefährdungen für die Bibliothek
INSERT INTO `gefaehrdung_bibliothek` (`kategorie_id`, `faktor_id`, `titel`, `beschreibung`, `typische_massnahmen`, `gesetzliche_grundlage`, `erstellt_von`) VALUES
((SELECT id FROM gefaehrdung_kategorien WHERE nummer = '9'),
(SELECT id FROM gefaehrdung_faktoren WHERE nummer = '9.3'),
'Langes Sitzen am Bildschirmarbeitsplatz',
'Haltungsschäden, Rücken-/Nackenschmerzen, Fehlbelastung der Wirbelsäule und der Muskulatur, Zwangshaltung',
'Ergonomische Bürostühle, Rückenschule, Steh-Sitz-Arbeitsplatz',
'ArbSchG, ArbStättV, DGUV Information 215-410',
1),

((SELECT id FROM gefaehrdung_kategorien WHERE nummer = '8'),
(SELECT id FROM gefaehrdung_faktoren WHERE nummer = '8.2'),
'Ausreichende Beleuchtung am Bildschirmarbeitsplatz',
'Belastung der Augen und des Sehvermögens, Schädigung der Augen, Kopfschmerzen',
'Bürolampen > 500 Lux, zusätzliche Schreibtischlampe',
'Technische Regel ASR A3.4 - "Beleuchtung"',
1),

((SELECT id FROM gefaehrdung_kategorien WHERE nummer = '8'),
(SELECT id FROM gefaehrdung_faktoren WHERE nummer = '8.1'),
'Raumklima/Raumtemperatur',
'Erkältungskrankheiten, Bindehautentzündungen, trockene Schleimhäute, Kopfschmerzen und Konzentrationsstörungen auslösen. Beeinträchtigung der körperlichen und geistigen Leistungsfähigkeit.',
'Klimaanlage, regelmäßiges Lüften',
'Technische Regel für Arbeitsstätten - ASR A 3.5 - Raumtemperatur, ArbStättV',
1),

((SELECT id FROM gefaehrdung_kategorien WHERE nummer = '9'),
(SELECT id FROM gefaehrdung_faktoren WHERE nummer = '9.3'),
'Bildschirmgröße entspricht nicht den Mindestanforderungen',
'Belastung der Augen und des Sehvermögens durch zu kleine Bildschirme',
'Bildschirmgröße anpassen, Bildschirmbrille',
'ArbSchG, ArbStättV, DGUV Information 215-410',
1);

SET FOREIGN_KEY_CHECKS = 1;
