-- Fix: Bestehende Projekt-Gefährdungen reparieren
-- Wenn massnahme_s/t/o/p leer sind aber das alte massnahmen-Feld gefüllt ist,
-- wird der Inhalt nach massnahme_o (Organisatorisch) migriert

UPDATE projekt_gefaehrdungen
SET massnahme_o = massnahmen,
    stop_o = 1
WHERE massnahmen IS NOT NULL
  AND massnahmen != ''
  AND (massnahme_s IS NULL OR massnahme_s = '')
  AND (massnahme_t IS NULL OR massnahme_t = '')
  AND (massnahme_o IS NULL OR massnahme_o = '')
  AND (massnahme_p IS NULL OR massnahme_p = '');

-- Altes Feld leeren nach Migration
UPDATE projekt_gefaehrdungen
SET massnahmen = NULL
WHERE massnahme_o IS NOT NULL AND massnahme_o != ''
  AND (massnahmen = massnahme_o OR massnahmen LIKE CONCAT('%', LEFT(massnahme_o, 50), '%'));

-- Auch Bibliotheks-Einträge prüfen (falls v9b nicht gelaufen ist)
UPDATE gefaehrdung_bibliothek
SET massnahme_o = typische_massnahmen,
    stop_o = 1
WHERE typische_massnahmen IS NOT NULL
  AND typische_massnahmen != ''
  AND (massnahme_s IS NULL OR massnahme_s = '')
  AND (massnahme_t IS NULL OR massnahme_t = '')
  AND (massnahme_o IS NULL OR massnahme_o = '')
  AND (massnahme_p IS NULL OR massnahme_p = '');

UPDATE gefaehrdung_bibliothek
SET typische_massnahmen = NULL
WHERE massnahme_o IS NOT NULL AND massnahme_o != ''
  AND (typische_massnahmen = massnahme_o OR typische_massnahmen LIKE CONCAT('%', LEFT(massnahme_o, 50), '%'));

SELECT 'Fix abgeschlossen' AS Status,
       (SELECT COUNT(*) FROM projekt_gefaehrdungen WHERE massnahme_o IS NOT NULL AND massnahme_o != '') AS 'Projekte mit O-Massnahmen',
       (SELECT COUNT(*) FROM gefaehrdung_bibliothek WHERE massnahme_o IS NOT NULL AND massnahme_o != '') AS 'Bibliothek mit O-Massnahmen';
