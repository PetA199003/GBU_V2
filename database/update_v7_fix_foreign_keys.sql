-- Update-Skript V7: Fremdschlüssel in gefaehrdung_bibliothek korrigieren
-- Entfernt alte Fremdschlüssel die auf nicht mehr verwendete Tabellen zeigen

USE gefaehrdungsbeurteilung;

SET FOREIGN_KEY_CHECKS = 0;

-- Alte Fremdschlüssel entfernen (falls vorhanden)
-- Diese zeigen auf die alte gefaehrdung_kategorien Tabelle

-- Prüfen und entfernen: fk_gefbib_kategorie
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = 'gefaehrdungsbeurteilung'
    AND TABLE_NAME = 'gefaehrdung_bibliothek'
    AND CONSTRAINT_NAME = 'fk_gefbib_kategorie');
SET @sql = IF(@fk_exists > 0,
    'ALTER TABLE gefaehrdung_bibliothek DROP FOREIGN KEY fk_gefbib_kategorie',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Prüfen und entfernen: fk_gefbib_faktor
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = 'gefaehrdungsbeurteilung'
    AND TABLE_NAME = 'gefaehrdung_bibliothek'
    AND CONSTRAINT_NAME = 'fk_gefbib_faktor');
SET @sql = IF(@fk_exists > 0,
    'ALTER TABLE gefaehrdung_bibliothek DROP FOREIGN KEY fk_gefbib_faktor',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Prüfen und entfernen: fk_gefbib_benutzer
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = 'gefaehrdungsbeurteilung'
    AND TABLE_NAME = 'gefaehrdung_bibliothek'
    AND CONSTRAINT_NAME = 'fk_gefbib_benutzer');
SET @sql = IF(@fk_exists > 0,
    'ALTER TABLE gefaehrdung_bibliothek DROP FOREIGN KEY fk_gefbib_benutzer',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Alte Spalten umbenennen/entfernen falls nötig (faktor_id wird nicht mehr verwendet)
-- Die kategorie_id bleibt, zeigt aber jetzt auf arbeits_kategorien

SET FOREIGN_KEY_CHECKS = 1;

-- Fertig!
SELECT 'Datenbank-Update V7 (Foreign Key Fix) erfolgreich!' as Status;
