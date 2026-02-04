-- Update-Skript V6: Umlaute in Gefaehrdungsarten korrigieren
-- WICHTIG: In phpMyAdmin unter "SQL" einfuegen und ausfuehren

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

USE gefaehrdungsbeurteilung;

-- Gefaehrdungsarten loeschen und neu einfuegen (sicherste Methode)
TRUNCATE TABLE gefaehrdungsarten;

INSERT INTO gefaehrdungsarten (nummer, name, beschreibung, sortierung) VALUES
(1, 'Mechanische Gefaehrdung', 'Gefaehrdungen durch bewegte Teile, Quetschen, Stossen, etc.', 1),
(2, 'Sturzgefahr', 'Stolpern, Rutschen, Stuerzen', 2),
(3, 'Elektrische Gefahren', 'Gefaehrdungen durch elektrischen Strom', 3),
(4, 'Gesundheitsgefaehrdende Stoffe (chemische/biologische)', 'Gefahrstoffe, biologische Arbeitsstoffe', 4),
(5, 'Brand- und Explosionsgefahren', 'Brandgefahr, Explosionsgefahr', 5),
(6, 'Thermische Gefaehrdung', 'Hitze, Kaelte, heisse/kalte Oberflaechen', 6),
(7, 'Spezielle physikalische Belastungen', 'Laerm, Vibration, Strahlung', 7),
(8, 'Belastungen durch Arbeitsumgebungsbedingungen', 'Klima, Beleuchtung, Platzverhaeltnisse', 8),
(9, 'Belastungen am Bewegungsapparat', 'Heben, Tragen, Zwangshaltungen', 9),
(10, 'Psychische Belastungen', 'Stress, Zeitdruck, Ueberforderung', 10),
(11, 'Unerwartete Aktionen', 'Unvorhergesehene Ereignisse, Fehlverhalten', 11),
(12, 'Ausfall Energieversorgung', 'Stromausfall, Versorgungsunterbrechung', 12),
(13, 'Arbeitsorganisation', 'Organisatorische Maengel, Kommunikation', 13);

SELECT 'Gefaehrdungsarten ohne Umlaute eingefuegt!' as Status;
