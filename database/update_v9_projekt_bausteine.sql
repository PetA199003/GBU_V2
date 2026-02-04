-- Update-Skript V9: Projekt-spezifische Unterweisungs-Bausteine
-- Erlaubt Bearbeitern, pro Projekt individuelle Inhalte hinzuzufuegen

USE gefaehrdungsbeurteilung;

-- Spalte projekt_id hinzufuegen (NULL = globaler Baustein, ID = nur fuer dieses Projekt)
ALTER TABLE `unterweisungs_bausteine`
ADD COLUMN `projekt_id` INT(11) DEFAULT NULL AFTER `aktiv`,
ADD INDEX `idx_projekt_id` (`projekt_id`);

-- Foreign Key (optional, fuer Referenzintegritaet)
-- ALTER TABLE `unterweisungs_bausteine`
-- ADD CONSTRAINT `fk_baustein_projekt` FOREIGN KEY (`projekt_id`) REFERENCES `projekte`(`id`) ON DELETE CASCADE;
