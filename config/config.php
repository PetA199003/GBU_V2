<?php
/**
 * Allgemeine Konfiguration
 * Gefährdungsbeurteilungs-System
 */

// Session starten
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fehleranzeige (in Produktion auf 0 setzen)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Zeitzone
date_default_timezone_set('Europe/Berlin');

// Basis-Pfade
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', '');

// Anwendungs-Einstellungen
define('APP_NAME', 'Gefährdungsbeurteilung');
define('APP_VERSION', '1.0.0');

// Benutzerrollen
define('ROLE_VIEWER', 1);
define('ROLE_EDITOR', 2);
define('ROLE_ADMIN', 3);

$ROLE_NAMES = [
    ROLE_VIEWER => 'Betrachter',
    ROLE_EDITOR => 'Bearbeiter',
    ROLE_ADMIN => 'Administrator'
];

// Schadenschwere (S 1-3)
$SCHADENSCHWERE = [
    1 => ['name' => 'Leicht', 'beschreibung' => 'Leichte Verletzungen / Erkrankungen', 'color' => '#92D050'],
    2 => ['name' => 'Mittel', 'beschreibung' => 'Mittlere Verletzungen / Erkrankungen', 'color' => '#FFFF00'],
    3 => ['name' => 'Schwer', 'beschreibung' => 'Schwere Verletzungen/bleibende Schäden/Möglicher Tod', 'color' => '#FF0000']
];

// Wahrscheinlichkeit (W 1-3)
$WAHRSCHEINLICHKEIT = [
    1 => ['name' => 'Unwahrscheinlich', 'beschreibung' => 'unwahrscheinlich', 'color' => '#92D050'],
    2 => ['name' => 'Wahrscheinlich', 'beschreibung' => 'wahrscheinlich', 'color' => '#FFFF00'],
    3 => ['name' => 'Sehr wahrscheinlich', 'beschreibung' => 'sehr wahrscheinlich', 'color' => '#FF0000']
];

// STOP-Prinzip Maßnahmenarten
$STOP_PRINZIP = [
    'S' => ['name' => 'Substitution', 'beschreibung' => 'Substitution durch sichere Verfahren', 'color' => '#FF0000'],
    'T' => ['name' => 'Technisch', 'beschreibung' => 'Technische Lösungen', 'color' => '#FFC000'],
    'O' => ['name' => 'Organisatorisch', 'beschreibung' => 'Organisatorische und verhältnisbezogene Lösungen', 'color' => '#FFFF00'],
    'P' => ['name' => 'Persönlich', 'beschreibung' => 'Persönliche Schutzausrüstung', 'color' => '#92D050']
];

// Gefährdungskategorien
$GEFAEHRDUNG_KATEGORIEN = [
    1 => 'Mechanische Gefährdungen',
    2 => 'Elektrische Gefährdungen',
    3 => 'Gefahrstoffe',
    4 => 'Biologische Arbeitsstoffe',
    5 => 'Brand- und Explosionsgefährdungen',
    6 => 'Thermische Gefährdungen',
    7 => 'Gefährdungen durch spezielle physikalische Einwirkungen',
    8 => 'Gefährdungen durch Arbeitsumgebungsbedingungen',
    9 => 'Physische Belastung/Arbeitsschwere',
    10 => 'Psychische Faktoren',
    11 => 'Sonstige Gefährdungen'
];

// Hilfsfunktionen
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    return $_SESSION['user'] ?? null;
}

function hasRole($requiredRole) {
    $user = getCurrentUser();
    if (!$user) {
        return false;
    }
    return (int)$user['rolle'] >= (int)$requiredRole;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header('Location: ' . BASE_URL . '/unauthorized.php');
        exit;
    }
}

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header('Location: ' . BASE_URL . '/' . $url);
    exit;
}

function setFlashMessage($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function calculateRiskScore($schadenschwere, $wahrscheinlichkeit) {
    return ($schadenschwere * $schadenschwere) * $wahrscheinlichkeit;
}

function getRiskColor($score) {
    if ($score <= 2) return '#92D050'; // Grün
    if ($score <= 4) return '#FFFF00'; // Gelb
    if ($score <= 8) return '#FFC000'; // Orange
    return '#FF0000'; // Rot
}

function getRiskLevel($score) {
    if ($score <= 2) return 'Gering';
    if ($score <= 4) return 'Mittel';
    if ($score <= 8) return 'Hoch';
    return 'Sehr hoch';
}
