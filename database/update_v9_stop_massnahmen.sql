-- Update V9: Separate Maßnahmen-Felder für STOP-Prinzip
-- Jedes STOP-Prinzip (S, T, O, P) bekommt ein eigenes Textfeld für Maßnahmen

-- Gefährdung-Bibliothek erweitern
ALTER TABLE gefaehrdung_bibliothek
    ADD COLUMN massnahme_s TEXT DEFAULT NULL COMMENT 'Substitution - Maßnahmen' AFTER stop_p,
    ADD COLUMN massnahme_t TEXT DEFAULT NULL COMMENT 'Technisch - Maßnahmen' AFTER massnahme_s,
    ADD COLUMN massnahme_o TEXT DEFAULT NULL COMMENT 'Organisatorisch - Maßnahmen' AFTER massnahme_t,
    ADD COLUMN massnahme_p TEXT DEFAULT NULL COMMENT 'Persönlich (PSA) - Maßnahmen' AFTER massnahme_o;

-- Projekt-Gefährdungen erweitern
ALTER TABLE projekt_gefaehrdungen
    ADD COLUMN massnahme_s TEXT DEFAULT NULL COMMENT 'Substitution - Maßnahmen' AFTER stop_p,
    ADD COLUMN massnahme_t TEXT DEFAULT NULL COMMENT 'Technisch - Maßnahmen' AFTER massnahme_s,
    ADD COLUMN massnahme_o TEXT DEFAULT NULL COMMENT 'Organisatorisch - Maßnahmen' AFTER massnahme_t,
    ADD COLUMN massnahme_p TEXT DEFAULT NULL COMMENT 'Persönlich (PSA) - Maßnahmen' AFTER massnahme_o;

-- Migration: Bestehende Maßnahmen als "allgemeine" Maßnahmen behalten
-- Die alten Felder 'massnahmen' und 'typische_massnahmen' bleiben erhalten als Fallback
