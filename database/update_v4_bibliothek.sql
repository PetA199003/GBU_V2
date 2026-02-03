-- Update-Skript V4: Bibliothek-Tabelle erweitern
-- Fügt STOP-Felder, Gefährdungsart und Kategorien zur Bibliothek hinzu

USE gefaehrdungsbeurteilung;

-- ============================================
-- GEFÄHRDUNG_BIBLIOTHEK ERWEITERN
-- ============================================

-- STOP-Prinzip Felder hinzufügen
ALTER TABLE `gefaehrdung_bibliothek`
    ADD COLUMN IF NOT EXISTS `stop_s` TINYINT(1) NOT NULL DEFAULT 0 AFTER `standard_wahrscheinlichkeit`,
    ADD COLUMN IF NOT EXISTS `stop_t` TINYINT(1) NOT NULL DEFAULT 0 AFTER `stop_s`,
    ADD COLUMN IF NOT EXISTS `stop_o` TINYINT(1) NOT NULL DEFAULT 0 AFTER `stop_t`,
    ADD COLUMN IF NOT EXISTS `stop_p` TINYINT(1) NOT NULL DEFAULT 0 AFTER `stop_o`;

-- Gefährdungsart und Kategorien hinzufügen
ALTER TABLE `gefaehrdung_bibliothek`
    ADD COLUMN IF NOT EXISTS `gefaehrdungsart_id` INT UNSIGNED DEFAULT NULL AFTER `id`,
    ADD COLUMN IF NOT EXISTS `kategorie_id` INT UNSIGNED DEFAULT NULL AFTER `gefaehrdungsart_id`,
    ADD COLUMN IF NOT EXISTS `unterkategorie_id` INT UNSIGNED DEFAULT NULL AFTER `kategorie_id`;

-- Indizes hinzufügen (nur wenn sie nicht existieren)
-- MySQL 8+ unterstützt IF NOT EXISTS für Indizes nicht direkt, daher mit Fehlerbehandlung
-- Diese werden ggf. einen Fehler werfen wenn sie schon existieren - kann ignoriert werden

-- Fertig!
SELECT 'Datenbank-Update V4 (Bibliothek-Erweiterung) erfolgreich!' as Status;
