<?php
/**
 * API: Arbeitsbereiche
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

requireLogin();

$db = Database::getInstance();

$unternehmensId = $_GET['unternehmen_id'] ?? null;

if (!$unternehmensId) {
    echo json_encode([]);
    exit;
}

$arbeitsbereiche = $db->fetchAll(
    "SELECT * FROM arbeitsbereiche WHERE unternehmen_id = ? ORDER BY name",
    [$unternehmensId]
);

echo json_encode($arbeitsbereiche);
