-- =====================================================
-- Update v12: Zweisprachigkeit (DE/EN) für Exports
-- =====================================================

-- 1. Projektsprache
ALTER TABLE `projekte`
ADD COLUMN `sprache` ENUM('de','en') NOT NULL DEFAULT 'de' AFTER `status`;

-- 2. Unterweisungs-Bausteine: Englische Felder
ALTER TABLE `unterweisungs_bausteine`
ADD COLUMN `kategorie_en` VARCHAR(100) DEFAULT NULL AFTER `kategorie`,
ADD COLUMN `titel_en` VARCHAR(200) DEFAULT NULL AFTER `titel`,
ADD COLUMN `inhalt_en` TEXT DEFAULT NULL AFTER `inhalt`;

-- 3. Projekt-Gefährdungen: Englische Felder
ALTER TABLE `projekt_gefaehrdungen`
ADD COLUMN `titel_en` VARCHAR(255) DEFAULT NULL AFTER `titel`,
ADD COLUMN `beschreibung_en` TEXT DEFAULT NULL AFTER `beschreibung`,
ADD COLUMN `massnahme_s_en` TEXT DEFAULT NULL AFTER `massnahme_s`,
ADD COLUMN `massnahme_t_en` TEXT DEFAULT NULL AFTER `massnahme_t`,
ADD COLUMN `massnahme_o_en` TEXT DEFAULT NULL AFTER `massnahme_o`,
ADD COLUMN `massnahme_p_en` TEXT DEFAULT NULL AFTER `massnahme_p`,
ADD COLUMN `verantwortlich_en` VARCHAR(200) DEFAULT NULL AFTER `verantwortlich`;

-- 4. Gefährdungs-Bibliothek: Englische Felder
ALTER TABLE `gefaehrdung_bibliothek`
ADD COLUMN `titel_en` VARCHAR(200) DEFAULT NULL AFTER `titel`,
ADD COLUMN `beschreibung_en` TEXT DEFAULT NULL AFTER `beschreibung`,
ADD COLUMN `typische_massnahmen_en` TEXT DEFAULT NULL AFTER `typische_massnahmen`,
ADD COLUMN `gesetzliche_grundlage_en` TEXT DEFAULT NULL AFTER `gesetzliche_grundlage`,
ADD COLUMN `massnahme_s_en` TEXT DEFAULT NULL AFTER `massnahme_s`,
ADD COLUMN `massnahme_t_en` TEXT DEFAULT NULL AFTER `massnahme_t`,
ADD COLUMN `massnahme_o_en` TEXT DEFAULT NULL AFTER `massnahme_o`,
ADD COLUMN `massnahme_p_en` TEXT DEFAULT NULL AFTER `massnahme_p`,
ADD COLUMN `verantwortlich_en` VARCHAR(255) DEFAULT NULL AFTER `verantwortlich`;

-- 5. Gefährdungsarten: Englische Namen
ALTER TABLE `gefaehrdungsarten`
ADD COLUMN `name_en` VARCHAR(200) DEFAULT NULL AFTER `name`,
ADD COLUMN `beschreibung_en` TEXT DEFAULT NULL AFTER `beschreibung`;

UPDATE `gefaehrdungsarten` SET `name_en` = 'Mechanical Hazards' WHERE `nummer` = 1;
UPDATE `gefaehrdungsarten` SET `name_en` = 'Fall Hazards' WHERE `nummer` = 2;
UPDATE `gefaehrdungsarten` SET `name_en` = 'Electrical Hazards' WHERE `nummer` = 3;
UPDATE `gefaehrdungsarten` SET `name_en` = 'Hazardous Substances (Chemical/Biological)' WHERE `nummer` = 4;
UPDATE `gefaehrdungsarten` SET `name_en` = 'Fire and Explosion Hazards' WHERE `nummer` = 5;
UPDATE `gefaehrdungsarten` SET `name_en` = 'Thermal Hazards' WHERE `nummer` = 6;
UPDATE `gefaehrdungsarten` SET `name_en` = 'Special Physical Stresses' WHERE `nummer` = 7;
UPDATE `gefaehrdungsarten` SET `name_en` = 'Work Environment Hazards' WHERE `nummer` = 8;
UPDATE `gefaehrdungsarten` SET `name_en` = 'Musculoskeletal Strain' WHERE `nummer` = 9;
UPDATE `gefaehrdungsarten` SET `name_en` = 'Psychological Stress' WHERE `nummer` = 10;
UPDATE `gefaehrdungsarten` SET `name_en` = 'Unexpected Events' WHERE `nummer` = 11;
UPDATE `gefaehrdungsarten` SET `name_en` = 'Power Supply Failure' WHERE `nummer` = 12;
UPDATE `gefaehrdungsarten` SET `name_en` = 'Work Organisation' WHERE `nummer` = 13;

-- 6. Arbeits-Kategorien: Englische Felder
ALTER TABLE `arbeits_kategorien`
ADD COLUMN `name_en` VARCHAR(200) DEFAULT NULL AFTER `name`,
ADD COLUMN `beschreibung_en` TEXT DEFAULT NULL AFTER `beschreibung`;

-- 7. Arbeits-Unterkategorien: Englische Felder
ALTER TABLE `arbeits_unterkategorien`
ADD COLUMN `name_en` VARCHAR(200) DEFAULT NULL AFTER `name`,
ADD COLUMN `beschreibung_en` TEXT DEFAULT NULL AFTER `beschreibung`;
