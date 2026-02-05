-- Update-Skript V6: Login-Versuche Tabelle für IP-Sperre
-- Speichert fehlgeschlagene Login-Versuche pro IP

USE gefaehrdungsbeurteilung;

-- ============================================
-- LOGIN_ATTEMPTS TABELLE
-- ============================================

CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip_address` VARCHAR(45) NOT NULL,
    `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `benutzername` VARCHAR(100) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_ip_address` (`ip_address`),
    KEY `idx_attempted_at` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alte Einträge automatisch löschen (älter als 1 Stunde)
-- Dies kann als geplanter Job ausgeführt werden oder beim Login geprüft werden

-- Fertig!
SELECT 'Datenbank-Update V6 (Login-Attempts) erfolgreich!' as Status;
