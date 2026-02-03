<?php
/**
 * API: Vorgänge (Gefährdungen)
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
            // Einzelnen Vorgang abrufen
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('ID erforderlich');
            }

            $db = Database::getInstance();
            $vorgang = $db->fetchOne("
                SELECT v.*, gf.name as faktor_name, gf.nummer as faktor_nummer
                FROM vorgaenge v
                LEFT JOIN gefaehrdung_faktoren gf ON v.gefaehrdung_faktor_id = gf.id
                WHERE v.id = ?
            ", [$id]);

            if (!$vorgang) {
                throw new Exception('Vorgang nicht gefunden');
            }

            echo json_encode($vorgang);
            break;

        case 'POST':
            // Neuen Vorgang erstellen
            requireRole(ROLE_EDITOR);

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['taetigkeit_id']) || empty($data['position']) ||
                empty($data['vorgang_beschreibung']) || empty($data['gefaehrdung'])) {
                throw new Exception('Pflichtfelder fehlen');
            }

            $id = $gb->createVorgang($data);

            echo json_encode(['success' => true, 'id' => $id]);
            break;

        case 'PUT':
            // Vorgang aktualisieren
            requireRole(ROLE_EDITOR);

            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('ID erforderlich');
            }

            $data = json_decode(file_get_contents('php://input'), true);

            $gb->updateVorgang($id, $data);

            echo json_encode(['success' => true]);
            break;

        case 'DELETE':
            // Vorgang löschen
            requireRole(ROLE_EDITOR);

            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('ID erforderlich');
            }

            $gb->deleteVorgang($id);

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
