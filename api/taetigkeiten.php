<?php
/**
 * API: Tätigkeiten
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Gefaehrdungsbeurteilung.php';

header('Content-Type: application/json');

requireLogin();

$gb = new Gefaehrdungsbeurteilung();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Einzelne Tätigkeit abrufen
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('ID erforderlich');
            }

            $db = Database::getInstance();
            $taetigkeit = $db->fetchOne("SELECT * FROM taetigkeiten WHERE id = ?", [$id]);

            if (!$taetigkeit) {
                throw new Exception('Tätigkeit nicht gefunden');
            }

            echo json_encode($taetigkeit);
            break;

        case 'POST':
            // Neue Tätigkeit erstellen
            requireRole(ROLE_EDITOR);

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['gefaehrdungsbeurteilung_id']) || empty($data['position']) || empty($data['name'])) {
                throw new Exception('Pflichtfelder fehlen');
            }

            $id = $gb->createTaetigkeit($data);

            echo json_encode(['success' => true, 'id' => $id]);
            break;

        case 'PUT':
            // Tätigkeit aktualisieren
            requireRole(ROLE_EDITOR);

            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('ID erforderlich');
            }

            $data = json_decode(file_get_contents('php://input'), true);

            $gb->updateTaetigkeit($id, $data);

            echo json_encode(['success' => true]);
            break;

        case 'DELETE':
            // Tätigkeit löschen
            requireRole(ROLE_EDITOR);

            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('ID erforderlich');
            }

            $gb->deleteTaetigkeit($id);

            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Methode nicht erlaubt']);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
