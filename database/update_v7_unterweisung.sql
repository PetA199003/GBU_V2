-- Update-Skript V7: Sicherheitsunterweisung
-- Tabellen fuer Sicherheitsunterweisung und Teilnehmerlisten

USE gefaehrdungsbeurteilung;

-- ============================================
-- 1. UNTERWEISUNGS-BAUSTEINE (Stammdaten)
-- ============================================

CREATE TABLE IF NOT EXISTS `unterweisungs_bausteine` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kategorie` VARCHAR(100) NOT NULL,
    `titel` VARCHAR(200) NOT NULL,
    `inhalt` TEXT NOT NULL,
    `bild_url` VARCHAR(500) DEFAULT NULL,
    `sortierung` INT NOT NULL DEFAULT 0,
    `aktiv` TINYINT(1) NOT NULL DEFAULT 1,
    `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_kategorie` (`kategorie`),
    KEY `idx_sortierung` (`sortierung`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. PROJEKT-UNTERWEISUNGEN
-- ============================================

CREATE TABLE IF NOT EXISTS `projekt_unterweisungen` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `projekt_id` INT UNSIGNED NOT NULL,
    `titel` VARCHAR(200) NOT NULL DEFAULT 'Sicherheitsunterweisung',
    `durchgefuehrt_von` VARCHAR(200) DEFAULT NULL,
    `durchgefuehrt_am` DATE DEFAULT NULL,
    `erstellt_von` INT UNSIGNED DEFAULT NULL,
    `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `aktualisiert_am` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_projekt` (`projekt_id`),
    CONSTRAINT `fk_pu_projekt` FOREIGN KEY (`projekt_id`) REFERENCES `projekte` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. AUSGEWAEHLTE BAUSTEINE PRO UNTERWEISUNG
-- ============================================

CREATE TABLE IF NOT EXISTS `unterweisung_bausteine` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `unterweisung_id` INT UNSIGNED NOT NULL,
    `baustein_id` INT UNSIGNED NOT NULL,
    `sortierung` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_unterweisung_baustein` (`unterweisung_id`, `baustein_id`),
    CONSTRAINT `fk_ub_unterweisung` FOREIGN KEY (`unterweisung_id`) REFERENCES `projekt_unterweisungen` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ub_baustein` FOREIGN KEY (`baustein_id`) REFERENCES `unterweisungs_bausteine` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. TEILNEHMERLISTE
-- ============================================

CREATE TABLE IF NOT EXISTS `unterweisung_teilnehmer` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `unterweisung_id` INT UNSIGNED NOT NULL,
    `vorname` VARCHAR(100) NOT NULL,
    `nachname` VARCHAR(100) NOT NULL,
    `firma` VARCHAR(200) DEFAULT NULL,
    `unterschrift` LONGTEXT DEFAULT NULL,
    `unterschrieben_am` DATETIME DEFAULT NULL,
    `erstellt_am` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_unterweisung` (`unterweisung_id`),
    KEY `idx_nachname` (`nachname`),
    CONSTRAINT `fk_ut_unterweisung` FOREIGN KEY (`unterweisung_id`) REFERENCES `projekt_unterweisungen` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. STANDARD-BAUSTEINE EINFUEGEN
-- ============================================

INSERT INTO `unterweisungs_bausteine` (`kategorie`, `titel`, `inhalt`, `sortierung`) VALUES

-- Organisation
('Organisation', 'Verantwortlichkeiten',
'• Verantwortlich bei Produktionen ist der Technische Leiter der Firma.\n• Verantwortlich fuer Einzelgewerke ist der Gewerkeleiter / Bereichsleiter.\n• Den Anweisungen des Verantwortlichen ist Folge zu leisten.\n• Die Sicherheitskennzeichnungen sind zu beachten.\n• Die Kommunikationskette ist einzuhalten:\n-> Crewmitglied berichtet Gewerkeleiter / Bereichsleiter\n-> Gewerkeleiter berichtet an Technischer Leiter', 10),

-- Allgemeine Hinweise
('Allgemeine Hinweise', 'Arbeitsanweisungen',
'• Alle Arbeitsanweisungen muessen eingehalten werden.\n• Bei Unklarheiten zu einer Aufgabe unbedingt nachfragen.\n• Anweisungen zu unsicheren Arbeiten muessen nicht befolgt werden!\n• Alle Arbeiten sind sicher auszufuehren!\n• Achtet auf Euch und Andere!', 20),

('Allgemeine Hinweise', 'Rechtliche Bestimmungen',
'• Die rechtlichen Bestimmungen sind einzuhalten.\n• Die Arbeitsschutzvorschriften und Gefaehrdungsbeurteilungen sind im Produktionsbuero einsehbar.', 21),

('Allgemeine Hinweise', 'Kommunikation',
'• Alle Beschaeftigten haben das Recht und die Pflicht, Probleme, Schwachstellen und unnoetige Belastungen im Arbeitsablauf anzusprechen und gemeinsam nach Verbesserungsmoeglichkeiten zu suchen.', 22),

('Allgemeine Hinweise', 'Verbote',
'• Alkohol, Drogen oder andere berauschende Mittel sind vor und waehrend der Arbeit verboten.\n• Das Rauchen ist ausschliesslich an den dafuer vorgesehenen Orten gestattet.', 23),

-- Notfaelle
('Notfaelle, Raeumung', 'Verkehrswege',
'Alle Verkehrswege, z.B. Tueren und Tore muessen freigehalten werden.\nFlucht- und Rettungswege, bzw. Notausgaenge oder Feuerloescheinrichtungen duerfen nicht verstellt werden.', 30),

('Notfaelle, Raeumung', 'Unfaelle',
'Bei Unfaellen ist sofort Hilfe zu leisten, Ersthelfer/Sanitaeter herbei zu holen!\nNotfallalarmierung durchfuehren! (CH 114/EU 112)\nHoehenrettung erforderlich?\nUnfaelle und Beinahe-Unfaelle muessen sofort dem direkten Ansprechpartner gemeldet werden.', 31),

('Notfaelle, Raeumung', 'Braende',
'Braende sind sofort zu melden (CH 118/EU 112) und mit den Feuerloescheinrichtungen zu bekaempfen.', 32),

('Notfaelle, Raeumung', 'Raeumung',
'Bei einer notwendigen Raeumung ist hilflosen und Personen mit Beeintraechtigung zu helfen.\nAlle Mitarbeiter sammeln sich, im Falle einer Raeumung, ausschliesslich an der Sammelstelle, welche bei Arbeitsbeginn vom Verantwortlichen bekanntgegeben wurde.', 33),

-- Allgemeine Gefahren
('Allgemeine Gefahren', 'Quetschen und Splitter',
'• Gefahr durch Quetschen der Haende beim Be- und Entladen.\n• Gefahr durch spitze und scharfkantige Oberflaechen und Holzsplitter, sowie durch Ecken und Kanten.\n• Handschuhe tragen.\n• Sicherheitsschuhe Klasse S2 tragen.\n• Feste, robuste Arbeitskleidung tragen.', 40),

('Allgemeine Gefahren', 'Herabfallende Gegenstaende',
'• Cases und andere Gegenstaende koennen herabfallen. Beim Stapeln und Abstapeln auf korrekten, sicheren Stand der Gegenstaende achten.\n• Vorsicht beim Bewegen einer oberen Reihe Cases: Dahinter koennen angekippte Cases ihren Halt verlieren und herunterfallen.', 41),

('Allgemeine Gefahren', 'Ladegut sicher stapeln',
'• Ladegut auf Staplern, Hubwagen und anderen Transportmitteln sicher stapeln und aufnehmen.\n• Gewicht gleichmaessig verteilen, vorsichtig in Kurven und auf Rampen fahren.\n• Ladegut nicht werfen oder fallen lassen.\n• Cases nicht anstossen lassen.\n• Vorsicht vor Staplern und LKW, diese haben immer Vorfahrt.\n• Feuchte Rampen und Boeden vor Benutzung trocknen.\n• Das Mitfahren auf Gabelstaplern und das Surfen auf Hubwagen ist verboten.', 42),

('Allgemeine Gefahren', 'Heben und Tragen',
'• Bei Heben und Tragen die Last nah am Koerper halten und bewegen.\n• Beim Aufnehmen der Last vom Boden in die Hocke gehen, mit dem ganzen Koerper heben.\n• Mit Lasten nicht den Oberkoerper alleine drehen, sondern den ganzen Koerper.\n• Die Position haeufiger wechseln, um den Koerper gleichmaessig zu belasten.\n• Hilfs- und Transportmittel benutzen (Stapler, Paletten, Wagen, usw.)\n• Schwere Lasten (ueber 40Kg) oder grosse Lasten (Traversen) nicht alleine heben oder bewegen.\n• Cases nicht alleine die Rampe raufschieben, je einen weiteren Kollegen links und rechts der Rampe zur Unterstuetzung nehmen.\n• Cases nicht in den LKW rollen lassen, ohne das ein Kollege das Case sieht und annimmt.', 43),

('Allgemeine Gefahren', 'Ziehen und Schieben',
'• Moeglichst Stapler oder Hubwagen benutzen.\n• Grosse und schwere Gegenstaende nicht alleine ziehen und schieben.\n• Kleinere Gegenstaende auf Paletten und Rungen zusammenfassen.', 44),

('Allgemeine Gefahren', 'Elektrotechnik',
'• Stromkabel und Verteiler vor Benutzung auf Beschaedigungen pruefen und bei sichtbaren Schaeden nicht benutzen.\n• Nur gepruefte und uebergebene Stromeinspeisungen und Verteiler benutzen.\n• Arbeiten an elektrischen Anlagen werden nur durch Elektrofachkraefte ausgefuehrt.', 45),

-- Arbeiten in der Hoehe
('Arbeiten in der Hoehe', 'Sicherung von Gegenstaenden',
'• Sicherungs-Drahtseile zum Sichern von aufzuhaengenden Gegenstaenden benutzen.\n• Drahtseile so anschlagen, dass max. Fallhoehe nicht mehr als 20cm.\n• Drahtseile nur mit geeigneten Verbindungsmitteln an vorgesehenen Anschlagpunkten befestigen.\n• Bei Arbeiten ueber anderen Personen, Werkzeuge und anderes nur in fest verschliessbaren Taschen mitfuehren. Werkzeuge mit Fallsicherung tragen und benutzen.\n• Helm nach EN 397 tragen!', 50),

('Arbeiten in der Hoehe', 'Beleuchtung',
'• Auf notwendige, ausreichende Arbeitsbeleuchtung ist zu achten, ggf. Taschenlampen einsetzen.', 51),

('Arbeiten in der Hoehe', 'Lichtquellen',
'• Nicht direkt in Lichtquellen (Laser, LED, Tageslicht,...) sehen.\n• Ggf. Schutzbrille einsetzen.', 52),

('Arbeiten in der Hoehe', 'Leitern und Tritte',
'• Leitern und Tritte vor dem Benutzen auf ordnungsgemaessen Zustand pruefen.\n• Bei Schaeden ausser Verkehr ziehen und Vorgesetzte informieren.\n• Standsicher aufstellen, Bodenunebenheiten beachten.\n• Anlegeleitern so anstellen, dass diese mindestens 1m ueber der Austrittsstelle hinausragen.\n• Bei Anlegeleitern die obersten vier Sprossen nicht benutzen.\n• Von Anlegeleitern nur Arbeiten geringen Umfangs ausfuehren.\n• Stehleitern nicht als Anlegeleitern benutzen.\n• Stehleitern so aufstellen, dass die Spreizsicherung voll ausgeklappt ist.\n• Von Stehleitern nicht auf andere Arbeitsplaetze uebersteigen.', 53),

-- Wetter
('Wetter - Arbeiten im Freien', 'Kaelte',
'• Ab minus 5 Grad C waehrend der Arbeit zusaetzliche Aufwaermpausen einlegen, Hautcreme benutzen.\n• Warme, robuste Arbeitskleidung, bzw. Kaelteschutzkleidung (auch Handschuhe) tragen.', 60),

('Wetter - Arbeiten im Freien', 'Hitze',
'• Geeignete Arbeitskleidung (hell, luftig) tragen.\n• Erholungs- und Abkuehlungspausen einlegen.\n• Ausreichend trinken.', 61),

('Wetter - Arbeiten im Freien', 'Sonneneinstrahlung',
'• Huete oder Helme mit umlaufender Krempe tragen.\n• Lange Hosen tragen.\n• Sonnenschutzmittel benutzen.\n• Waehrend der Pausen Sonne meiden/unterstellen.', 62),

-- Arbeitsmittel
('Arbeitsmittel', 'Hubarbeitsbuehne',
'• Benutzung nur nach Qualifizierung (PAL-Card)\n• Geraet bei Uebernahme pruefen.\n• Nicht aus der Arbeitsplattform aus- oder aufsteigen\n• Gefahrenbereich am Boden absperren.\n• Rueckhaltesystem tragen und benutzen; diese an Anschlagpunkten der Hubarbeitsbuehne ordnungsgemaess anschlagen.', 70),

('Arbeitsmittel', 'Arbeiten in der Hoehe',
'• Bei Arbeiten in Konstruktionen und auf Plattformen ueber 2,5m Hoehe ist ein Ganzkoerpergurt immer vorgeschrieben.', 71),

-- PSA
('Persoenliche Schutzausruestung', 'Allgemein PSA',
'Die Persoenliche Schutzausruestung (PSA) ist immer mitzufuehren und bei Bedarf zu benutzen.\n• Die PSA und Arbeitskleidung ist dem Wetter angepasst auszuwaehlen.\n• Schadhafte und unbrauchbare PSA ist umgehend gegen Geeignete auszutauschen.', 80),

('Persoenliche Schutzausruestung', 'Kopfschutz',
'Ueberall dort, wo die Gefahr von Kopfverletzungen durch fallende Gegenstaende oder durch Anstossen an Hindernisse nicht auszuschliessen ist, z.B. beim Auf-, Ab-, Umbau, bei Lager- und Transportarbeiten, sowie bei gleichzeitigen Arbeiten auf mehreren Ebenen sind Schutzhelme nach Norm EN 397 zu tragen.', 81),

('Persoenliche Schutzausruestung', 'Fussschutz',
'Ueberall dort, wo Fussverletzungen moeglich sind, z.B. bei Auf-, Ab-, Umbauarbeiten, Lager- und Transportarbeiten sind Arbeitsschuhe mindestens nach Klasse S3 zu tragen.', 82),

('Persoenliche Schutzausruestung', 'Handschutz',
'Schutzhandschuhe sind bei allen Arbeiten, bei denen Handverletzungen moeglich sind, wie z.B. beim Umgang mit hautschaedigenden, splitternden oder scharfkantigen Materialien.', 83),

('Persoenliche Schutzausruestung', 'Gehoerschutz',
'Mitarbeiter muessen ein Gehoerschutz tragen, wenn der Laerm- oder Geraueschpegel am Arbeitsplatz 85 Dezibel (A-bewertet) oder dBA uebersteigt. Gehoerschuetzer reduzieren den Laermexpositionspegel und das Risiko von Gehoerverlust.\nGehoerschutz wird bei Bedarf vom Kunden zur Verfuegung gestellt. Der Crew Leiter muss Gehoerschutz anfordern, wenn dieser nicht zur Verfuegung gestellt wird, aber erforderlich ist.', 84),

('Persoenliche Schutzausruestung', 'Warnbekleidung',
'Reflektierende, hochsichtbare Warnbekleidung ist auf Anweisung oder bei entsprechender Gefaehrdungslage z.B. durch Fahrzeugverkehr, Staplerverkehr oder aehnlichem zu tragen.\nDas Anhaengen an den z.B: Guertel gilt nicht als tragen und ist somit nicht zulaessig!', 85);

SELECT 'Sicherheitsunterweisung-Tabellen erfolgreich erstellt!' as Status;
