# Update-Anleitung: Projekte statt Unternehmen

## Auf der VM (192.168.1.115)

### 1. Änderungen von GitHub pullen

```bash
cd /root/GBU_V2
git pull origin main
```

### 2. Datenbank aktualisieren

```bash
sudo mysql gefaehrdungsbeurteilung < /root/GBU_V2/database/update_projekte.sql
```

### 3. Dateien ins Web-Verzeichnis kopieren

```bash
sudo cp -r /root/GBU_V2/* /var/www/html/gefaehrdungsbeurteilung/
sudo chown -R www-data:www-data /var/www/html/gefaehrdungsbeurteilung/
```

### 4. Fertig!

Im Browser öffnen: **http://192.168.1.115/gefaehrdungsbeurteilung/**

---

## Was wurde geändert?

### Neue Funktionen:
- **Projekte** statt Unternehmen mit folgenden Feldern:
  - Name
  - Location
  - Zeitraum (von - bis)
  - Aufbau-Datum
  - Abbau-Datum
  - Indoor/Outdoor/Beides
  - Status (geplant/aktiv/abgeschlossen)
  - Beschreibung

- **Benutzer-Zuweisung**: Admin kann Benutzer zu Projekten zuweisen
- **Sichtbarkeit**: Benutzer sehen nur die Projekte, denen sie zugewiesen sind
- **Neuer Benutzer Button**: In der Benutzerverwaltung kann man jetzt neue Benutzer anlegen

### Geänderte Dateien:
- `templates/header.php` - Navigation: "Unternehmen" → "Projekte"
- `admin/projekte.php` - NEU: Projektverwaltung
- `admin/benutzer.php` - NEU: "Neuer Benutzer" Button und Modal

### Neue Datenbank-Tabellen:
- `projekte` - Projekt-Informationen
- `benutzer_projekte` - Benutzer-Projekt-Zuweisung

---

## Einzeiler (alles auf einmal)

```bash
cd /root/GBU_V2 && git pull origin main && sudo mysql gefaehrdungsbeurteilung < /root/GBU_V2/database/update_projekte.sql && sudo cp -r /root/GBU_V2/* /var/www/html/gefaehrdungsbeurteilung/ && sudo chown -R www-data:www-data /var/www/html/gefaehrdungsbeurteilung/
```
