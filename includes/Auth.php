<?php
/**
 * Authentifizierungs-Klasse
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

class Auth {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Benutzer registrieren
     */
    public function register($data) {
        // Validierung
        $errors = [];

        if (empty($data['benutzername']) || strlen($data['benutzername']) < 3) {
            $errors[] = 'Benutzername muss mindestens 3 Zeichen lang sein.';
        }

        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
        }

        if (empty($data['passwort']) || strlen($data['passwort']) < 6) {
            $errors[] = 'Passwort muss mindestens 6 Zeichen lang sein.';
        }

        if ($data['passwort'] !== $data['passwort_confirm']) {
            $errors[] = 'Die Passwörter stimmen nicht überein.';
        }

        if (empty($data['vorname'])) {
            $errors[] = 'Bitte geben Sie Ihren Vornamen ein.';
        }

        if (empty($data['nachname'])) {
            $errors[] = 'Bitte geben Sie Ihren Nachnamen ein.';
        }

        // Prüfen ob Benutzername bereits existiert
        $existing = $this->db->fetchOne(
            "SELECT id FROM benutzer WHERE benutzername = ?",
            [$data['benutzername']]
        );
        if ($existing) {
            $errors[] = 'Dieser Benutzername ist bereits vergeben.';
        }

        // Prüfen ob E-Mail bereits existiert
        $existing = $this->db->fetchOne(
            "SELECT id FROM benutzer WHERE email = ?",
            [$data['email']]
        );
        if ($existing) {
            $errors[] = 'Diese E-Mail-Adresse ist bereits registriert.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Benutzer erstellen
        $userId = $this->db->insert('benutzer', [
            'benutzername' => $data['benutzername'],
            'email' => $data['email'],
            'passwort' => password_hash($data['passwort'], PASSWORD_DEFAULT),
            'vorname' => $data['vorname'],
            'nachname' => $data['nachname'],
            'rolle' => ROLE_VIEWER, // Neue Benutzer sind zunächst Betrachter
            'aktiv' => 1
        ]);

        return ['success' => true, 'user_id' => $userId];
    }

    /**
     * Benutzer anmelden
     */
    public function login($benutzername, $passwort) {
        $user = $this->db->fetchOne(
            "SELECT * FROM benutzer WHERE (benutzername = ? OR email = ?) AND aktiv = 1",
            [$benutzername, $benutzername]
        );

        if (!$user) {
            return ['success' => false, 'error' => 'Ungültiger Benutzername oder Passwort.'];
        }

        if (!password_verify($passwort, $user['passwort'])) {
            return ['success' => false, 'error' => 'Ungültiger Benutzername oder Passwort.'];
        }

        // Session-Daten setzen
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user'] = [
            'id' => $user['id'],
            'benutzername' => $user['benutzername'],
            'email' => $user['email'],
            'vorname' => $user['vorname'],
            'nachname' => $user['nachname'],
            'rolle' => $user['rolle'],
            'voller_name' => $user['vorname'] . ' ' . $user['nachname']
        ];

        // Letzten Login aktualisieren
        $this->db->update('benutzer',
            ['letzter_login' => date('Y-m-d H:i:s')],
            'id = :id',
            ['id' => $user['id']]
        );

        return ['success' => true, 'user' => $_SESSION['user']];
    }

    /**
     * Benutzer abmelden
     */
    public function logout() {
        session_unset();
        session_destroy();
    }

    /**
     * Passwort ändern
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        $user = $this->db->fetchOne("SELECT passwort FROM benutzer WHERE id = ?", [$userId]);

        if (!$user || !password_verify($currentPassword, $user['passwort'])) {
            return ['success' => false, 'error' => 'Das aktuelle Passwort ist falsch.'];
        }

        if (strlen($newPassword) < 6) {
            return ['success' => false, 'error' => 'Das neue Passwort muss mindestens 6 Zeichen lang sein.'];
        }

        $this->db->update('benutzer',
            ['passwort' => password_hash($newPassword, PASSWORD_DEFAULT)],
            'id = :id',
            ['id' => $userId]
        );

        return ['success' => true];
    }

    /**
     * Benutzer nach ID abrufen
     */
    public function getUserById($id) {
        return $this->db->fetchOne(
            "SELECT id, benutzername, email, vorname, nachname, rolle, aktiv, letzter_login, erstellt_am
             FROM benutzer WHERE id = ?",
            [$id]
        );
    }

    /**
     * Alle Benutzer abrufen
     */
    public function getAllUsers() {
        return $this->db->fetchAll(
            "SELECT id, benutzername, email, vorname, nachname, rolle, aktiv, letzter_login, erstellt_am
             FROM benutzer ORDER BY nachname, vorname"
        );
    }

    /**
     * Benutzer aktualisieren (Admin)
     */
    public function updateUser($id, $data) {
        $updateData = [];

        if (isset($data['vorname'])) {
            $updateData['vorname'] = $data['vorname'];
        }
        if (isset($data['nachname'])) {
            $updateData['nachname'] = $data['nachname'];
        }
        if (isset($data['email'])) {
            $updateData['email'] = $data['email'];
        }
        if (isset($data['rolle'])) {
            $updateData['rolle'] = $data['rolle'];
        }
        if (isset($data['aktiv'])) {
            $updateData['aktiv'] = $data['aktiv'];
        }

        if (!empty($updateData)) {
            $this->db->update('benutzer', $updateData, 'id = :id', ['id' => $id]);
        }

        return ['success' => true];
    }

    /**
     * Benutzer löschen
     */
    public function deleteUser($id) {
        // Nicht den aktuell angemeldeten Benutzer löschen
        if ($id == $_SESSION['user_id']) {
            return ['success' => false, 'error' => 'Sie können sich nicht selbst löschen.'];
        }

        $this->db->delete('benutzer', 'id = ?', [$id]);
        return ['success' => true];
    }
}
