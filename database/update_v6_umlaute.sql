-- Update-Skript V6: Umlaute in Gefährdungsarten korrigieren
-- Führe dieses Skript in phpMyAdmin oder MySQL aus

USE gefaehrdungsbeurteilung;

-- Charset der Datenbank und Tabellen sicherstellen
ALTER DATABASE gefaehrdungsbeurteilung CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE gefaehrdungsarten CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Gefährdungsarten mit korrekten Umlauten aktualisieren
UPDATE gefaehrdungsarten SET name = 'Mechanische Gefährdung', beschreibung = 'Gefährdungen durch bewegte Teile, Quetschen, Stoßen, etc.' WHERE nummer = 1;
UPDATE gefaehrdungsarten SET name = 'Sturzgefahr', beschreibung = 'Stolpern, Rutschen, Stürzen' WHERE nummer = 2;
UPDATE gefaehrdungsarten SET name = 'Elektrische Gefahren', beschreibung = 'Gefährdungen durch elektrischen Strom' WHERE nummer = 3;
UPDATE gefaehrdungsarten SET name = 'Gesundheitsgefährdende Stoffe (chemische/biologische)', beschreibung = 'Gefahrstoffe, biologische Arbeitsstoffe' WHERE nummer = 4;
UPDATE gefaehrdungsarten SET name = 'Brand- und Explosionsgefahren', beschreibung = 'Brandgefahr, Explosionsgefahr' WHERE nummer = 5;
UPDATE gefaehrdungsarten SET name = 'Thermische Gefährdung', beschreibung = 'Hitze, Kälte, heiße/kalte Oberflächen' WHERE nummer = 6;
UPDATE gefaehrdungsarten SET name = 'Spezielle physikalische Belastungen', beschreibung = 'Lärm, Vibration, Strahlung' WHERE nummer = 7;
UPDATE gefaehrdungsarten SET name = 'Belastungen durch Arbeitsumgebungsbedingungen', beschreibung = 'Klima, Beleuchtung, Platzverhältnisse' WHERE nummer = 8;
UPDATE gefaehrdungsarten SET name = 'Belastungen am Bewegungsapparat', beschreibung = 'Heben, Tragen, Zwangshaltungen' WHERE nummer = 9;
UPDATE gefaehrdungsarten SET name = 'Psychische Belastungen', beschreibung = 'Stress, Zeitdruck, Überforderung' WHERE nummer = 10;
UPDATE gefaehrdungsarten SET name = 'Unerwartete Aktionen', beschreibung = 'Unvorhergesehene Ereignisse, Fehlverhalten' WHERE nummer = 11;
UPDATE gefaehrdungsarten SET name = 'Ausfall Energieversorgung', beschreibung = 'Stromausfall, Versorgungsunterbrechung' WHERE nummer = 12;
UPDATE gefaehrdungsarten SET name = 'Arbeitsorganisation', beschreibung = 'Organisatorische Mängel, Kommunikation' WHERE nummer = 13;

-- Auch arbeits_kategorien korrigieren
ALTER TABLE arbeits_kategorien CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE arbeits_unterkategorien CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE gefaehrdung_bibliothek CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE projekt_gefaehrdungen CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE projekte CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE benutzer CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

SELECT 'Umlaute-Update erfolgreich!' as Status;
