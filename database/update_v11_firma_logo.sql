-- Update V11: Logo-Spalte für Firmen hinzufügen
-- Datum: 2025

ALTER TABLE `firmen` ADD COLUMN `logo_url` VARCHAR(500) DEFAULT NULL AFTER `webseite`;
