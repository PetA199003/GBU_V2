-- Update-Skript V5: Status-Spalte für Archivierung erweitern
-- Die status-Spalte muss 'archiviert' aufnehmen können

USE gefaehrdungsbeurteilung;

-- Status-Spalte erweitern (von ENUM oder VARCHAR zu größerem VARCHAR)
ALTER TABLE `projekte` MODIFY COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'geplant';

-- Fertig!
SELECT 'Datenbank-Update V5 (Archivierung) erfolgreich!' as Status;
