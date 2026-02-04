-- Update-Skript V8: Piktogramme fuer Unterweisungs-Bausteine
-- Verwendet Bootstrap Icons als Platzhalter (werden als CSS-Klassen verwendet)

USE gefaehrdungsbeurteilung;

-- Piktogramm-URLs aktualisieren (verwendet bekannte Sicherheitssymbole)
-- Sie koennen diese spaeter durch eigene Bilder ersetzen

-- Hinweis: Die Bilder muessen manuell hochgeladen werden
-- Alternativ koennen Sie kostenlose Sicherheitspiktogramme verwenden von:
-- https://commons.wikimedia.org/wiki/Category:GHS_hazard_pictograms
-- https://commons.wikimedia.org/wiki/Category:ISO_7010_safety_signs

-- Beispiel-Update fuer vorhandene Bausteine (URLs anpassen nach Upload):
-- UPDATE unterweisungs_bausteine SET bild_url = '/uploads/piktogramme/helm.png' WHERE titel = 'Kopfschutz';
-- UPDATE unterweisungs_bausteine SET bild_url = '/uploads/piktogramme/schuhe.png' WHERE titel = 'Fussschutz';
-- UPDATE unterweisungs_bausteine SET bild_url = '/uploads/piktogramme/handschuhe.png' WHERE titel = 'Handschutz';
-- UPDATE unterweisungs_bausteine SET bild_url = '/uploads/piktogramme/gehoerschutz.png' WHERE titel = 'Gehoerschutz';

SELECT 'Bitte laden Sie Piktogramme in /uploads/piktogramme/ hoch und aktualisieren Sie die bild_url in der Datenbank.' as Hinweis;
