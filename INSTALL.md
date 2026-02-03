# Gefährdungsbeurteilungs-System - Installationsanleitung

## Systemvoraussetzungen

- PHP 7.4 oder höher
- MySQL 5.7 oder höher (oder MariaDB 10.3+)
- Webserver (Apache mit mod_rewrite oder Nginx)
- Composer (optional, für zukünftige Erweiterungen)

## Installation

### 1. Dateien hochladen

Kopieren Sie alle Dateien in Ihr Webserver-Verzeichnis, z.B.:
```
/var/www/html/gefaehrdungsbeurteilung/
```

### 2. Datenbank erstellen

1. Melden Sie sich bei MySQL an:
   ```bash
   mysql -u root -p
   ```

2. Führen Sie das Schema-Skript aus:
   ```bash
   mysql -u root -p < database/schema.sql
   ```

   Oder importieren Sie `database/schema.sql` über phpMyAdmin.

### 3. Datenbankverbindung konfigurieren

Bearbeiten Sie `config/database.php` und passen Sie die Zugangsdaten an:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'gefaehrdungsbeurteilung');
define('DB_USER', 'ihr_benutzername');
define('DB_PASS', 'ihr_passwort');
```

### 4. Basis-URL anpassen

Bearbeiten Sie `config/config.php` und passen Sie die BASE_URL an:

```php
define('BASE_URL', '/gefaehrdungsbeurteilung');
```

Falls die Anwendung im Root-Verzeichnis liegt:
```php
define('BASE_URL', '');
```

### 5. Berechtigungen setzen

```bash
chmod 755 -R /var/www/html/gefaehrdungsbeurteilung/
chmod 777 -R /var/www/html/gefaehrdungsbeurteilung/uploads/
```

### 6. Apache-Konfiguration (optional)

Erstellen Sie eine `.htaccess`-Datei im Hauptverzeichnis:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

## Standard-Zugangsdaten

Nach der Installation können Sie sich mit folgenden Daten anmelden:

- **Benutzername:** admin
- **Passwort:** password

**WICHTIG:** Ändern Sie das Passwort sofort nach dem ersten Login!

## Funktionen

### Benutzerrollen

| Rolle | Rechte |
|-------|--------|
| Betrachter | Kann Gefährdungsbeurteilungen ansehen |
| Bearbeiter | Kann Gefährdungsbeurteilungen erstellen und bearbeiten |
| Administrator | Voller Zugriff inkl. Benutzerverwaltung |

### Hauptfunktionen

- **Dashboard:** Übersicht mit Statistiken und letzten Beurteilungen
- **Gefährdungsbeurteilungen:** Erstellen, bearbeiten, ansehen, duplizieren
- **Gefährdungsbibliothek:** Wiederverwendbare Gefährdungen speichern
- **Maßnahmenbibliothek:** Wiederverwendbare Maßnahmen speichern
- **Export:** PDF und Excel/CSV Export
- **Benutzerverwaltung:** Benutzer und Rollen verwalten
- **Unternehmensverwaltung:** Multi-Mandanten-Fähigkeit

### Risikobewertung

Die Risikobewertung erfolgt nach der Formel: **R = S² × W**

- **S** = Schadenschwere (1-3)
- **W** = Wahrscheinlichkeit (1-3)

| Risiko (R) | Bewertung |
|------------|-----------|
| 1-2 | Gering (Grün) |
| 3-4 | Mittel (Gelb) |
| 6-8 | Hoch (Orange) |
| 9+ | Sehr hoch (Rot) |

### STOP-Prinzip

Maßnahmen werden nach dem STOP-Prinzip kategorisiert:

- **S** - Substitution (Ersatz durch sichere Verfahren)
- **T** - Technische Lösungen
- **O** - Organisatorische Lösungen
- **P** - Persönliche Schutzausrüstung (PSA)

## Fehlerbehebung

### Datenbankverbindungsfehler

1. Prüfen Sie die Zugangsdaten in `config/database.php`
2. Stellen Sie sicher, dass der MySQL-Server läuft
3. Prüfen Sie, ob die Datenbank existiert

### Seite nicht gefunden

1. Prüfen Sie die BASE_URL in `config/config.php`
2. Bei Apache: Aktivieren Sie mod_rewrite
3. Bei Nginx: Konfigurieren Sie die Rewrite-Regeln

### Keine Schreibrechte

```bash
chmod 777 uploads/
```

## Sicherheitshinweise

Für den Produktiveinsatz:

1. Ändern Sie das Admin-Passwort
2. Deaktivieren Sie die Fehleranzeige in `config/config.php`:
   ```php
   error_reporting(0);
   ini_set('display_errors', 0);
   ```
3. Verwenden Sie HTTPS
4. Schützen Sie die `config/`-Dateien
5. Regelmäßige Datenbank-Backups

## Support

Bei Fragen oder Problemen wenden Sie sich an Ihren Administrator.

---

**Version:** 1.0.0
**Erstellt:** <?= date('Y-m-d') ?>
