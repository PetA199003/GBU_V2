-- Update-Skript V10: Unterschrift des Durchfuehrenden
-- Speichert die Unterschrift der Person, die die Unterweisung durchfuehrt

USE gefaehrdungsbeurteilung;

-- Spalten fuer Durchfuehrer-Unterschrift hinzufuegen
ALTER TABLE `projekt_unterweisungen`
ADD COLUMN `durchfuehrer_unterschrift` LONGTEXT DEFAULT NULL AFTER `durchgefuehrt_am`,
ADD COLUMN `durchfuehrer_unterschrieben_am` DATETIME DEFAULT NULL AFTER `durchfuehrer_unterschrift`;
