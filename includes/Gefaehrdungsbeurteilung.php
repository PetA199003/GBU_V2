<?php
/**
 * Gefährdungsbeurteilung Klasse
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

class Gefaehrdungsbeurteilung {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Alle Beurteilungen abrufen
     */
    public function getAll($filters = []) {
        $sql = "
            SELECT gb.*,
                   u.name as unternehmen_name,
                   ab.name as arbeitsbereich_name,
                   CONCAT(b.vorname, ' ', b.nachname) as erstellt_von_name,
                   (SELECT COUNT(*) FROM taetigkeiten t WHERE t.gefaehrdungsbeurteilung_id = gb.id) as taetigkeit_count,
                   (SELECT COUNT(*) FROM vorgaenge v
                    JOIN taetigkeiten t2 ON v.taetigkeit_id = t2.id
                    WHERE t2.gefaehrdungsbeurteilung_id = gb.id) as vorgang_count
            FROM gefaehrdungsbeurteilungen gb
            LEFT JOIN unternehmen u ON gb.unternehmen_id = u.id
            LEFT JOIN arbeitsbereiche ab ON gb.arbeitsbereich_id = ab.id
            LEFT JOIN benutzer b ON gb.erstellt_von = b.id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters['unternehmen_id'])) {
            $sql .= " AND gb.unternehmen_id = ?";
            $params[] = $filters['unternehmen_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND gb.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (gb.titel LIKE ? OR gb.ersteller_name LIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY gb.aktualisiert_am DESC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Einzelne Beurteilung abrufen
     */
    public function getById($id) {
        $gb = $this->db->fetchOne("
            SELECT gb.*,
                   u.name as unternehmen_name,
                   ab.name as arbeitsbereich_name,
                   CONCAT(b.vorname, ' ', b.nachname) as erstellt_von_name
            FROM gefaehrdungsbeurteilungen gb
            LEFT JOIN unternehmen u ON gb.unternehmen_id = u.id
            LEFT JOIN arbeitsbereiche ab ON gb.arbeitsbereich_id = ab.id
            LEFT JOIN benutzer b ON gb.erstellt_von = b.id
            WHERE gb.id = ?
        ", [$id]);

        if ($gb) {
            $gb['taetigkeiten'] = $this->getTaetigkeiten($id);
        }

        return $gb;
    }

    /**
     * Tätigkeiten einer Beurteilung abrufen
     */
    public function getTaetigkeiten($gbId) {
        $taetigkeiten = $this->db->fetchAll("
            SELECT * FROM taetigkeiten
            WHERE gefaehrdungsbeurteilung_id = ?
            ORDER BY sortierung, position
        ", [$gbId]);

        foreach ($taetigkeiten as &$t) {
            $t['vorgaenge'] = $this->getVorgaenge($t['id']);
        }

        return $taetigkeiten;
    }

    /**
     * Vorgänge einer Tätigkeit abrufen
     */
    public function getVorgaenge($taetigkeitId) {
        return $this->db->fetchAll("
            SELECT v.*, gf.name as faktor_name, gf.nummer as faktor_nummer,
                   gk.name as kategorie_name
            FROM vorgaenge v
            LEFT JOIN gefaehrdung_faktoren gf ON v.gefaehrdung_faktor_id = gf.id
            LEFT JOIN gefaehrdung_kategorien gk ON gf.kategorie_id = gk.id
            WHERE v.taetigkeit_id = ?
            ORDER BY v.sortierung, v.position
        ", [$taetigkeitId]);
    }

    /**
     * Neue Beurteilung erstellen
     */
    public function create($data) {
        return $this->db->insert('gefaehrdungsbeurteilungen', [
            'unternehmen_id' => $data['unternehmen_id'],
            'arbeitsbereich_id' => $data['arbeitsbereich_id'] ?: null,
            'titel' => $data['titel'],
            'ersteller_name' => $data['ersteller_name'],
            'erstelldatum' => $data['erstelldatum'] ?? date('Y-m-d'),
            'status' => $data['status'] ?? 'entwurf',
            'bemerkungen' => $data['bemerkungen'] ?? null,
            'erstellt_von' => $_SESSION['user_id']
        ]);
    }

    /**
     * Beurteilung aktualisieren
     */
    public function update($id, $data) {
        $updateData = [];

        $allowedFields = ['unternehmen_id', 'arbeitsbereich_id', 'titel', 'ersteller_name',
                         'erstelldatum', 'ueberarbeitungsdatum', 'status', 'bemerkungen'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field] ?: null;
            }
        }

        if (!empty($updateData)) {
            $this->db->update('gefaehrdungsbeurteilungen', $updateData, 'id = :id', ['id' => $id]);
        }

        return true;
    }

    /**
     * Beurteilung löschen
     */
    public function delete($id) {
        $this->db->delete('gefaehrdungsbeurteilungen', 'id = ?', [$id]);
        return true;
    }

    /**
     * Tätigkeit erstellen
     */
    public function createTaetigkeit($data) {
        // Nächste Position ermitteln
        $maxPos = $this->db->fetchOne("
            SELECT MAX(sortierung) as max_sort
            FROM taetigkeiten
            WHERE gefaehrdungsbeurteilung_id = ?
        ", [$data['gefaehrdungsbeurteilung_id']]);

        return $this->db->insert('taetigkeiten', [
            'gefaehrdungsbeurteilung_id' => $data['gefaehrdungsbeurteilung_id'],
            'position' => $data['position'],
            'name' => $data['name'],
            'beschreibung' => $data['beschreibung'] ?? null,
            'sortierung' => ($maxPos['max_sort'] ?? 0) + 1
        ]);
    }

    /**
     * Tätigkeit aktualisieren
     */
    public function updateTaetigkeit($id, $data) {
        $updateData = [];

        if (isset($data['position'])) $updateData['position'] = $data['position'];
        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['beschreibung'])) $updateData['beschreibung'] = $data['beschreibung'];
        if (isset($data['sortierung'])) $updateData['sortierung'] = $data['sortierung'];

        if (!empty($updateData)) {
            $this->db->update('taetigkeiten', $updateData, 'id = :id', ['id' => $id]);
        }

        return true;
    }

    /**
     * Tätigkeit löschen
     */
    public function deleteTaetigkeit($id) {
        $this->db->delete('taetigkeiten', 'id = ?', [$id]);
        return true;
    }

    /**
     * Vorgang erstellen
     */
    public function createVorgang($data) {
        // Nächste Position ermitteln
        $maxPos = $this->db->fetchOne("
            SELECT MAX(sortierung) as max_sort
            FROM vorgaenge
            WHERE taetigkeit_id = ?
        ", [$data['taetigkeit_id']]);

        return $this->db->insert('vorgaenge', [
            'taetigkeit_id' => $data['taetigkeit_id'],
            'position' => $data['position'],
            'vorgang_beschreibung' => $data['vorgang_beschreibung'],
            'gefaehrdung' => $data['gefaehrdung'],
            'gefaehrdung_faktor_id' => $data['gefaehrdung_faktor_id'] ?: null,
            'schadenschwere' => $data['schadenschwere'],
            'wahrscheinlichkeit' => $data['wahrscheinlichkeit'],
            'stop_s' => $data['stop_s'] ?? 0,
            'stop_t' => $data['stop_t'] ?? 0,
            'stop_o' => $data['stop_o'] ?? 0,
            'stop_p' => $data['stop_p'] ?? 0,
            'massnahmen' => $data['massnahmen'] ?? null,
            'massnahme_schadenschwere' => $data['massnahme_schadenschwere'] ?: null,
            'massnahme_wahrscheinlichkeit' => $data['massnahme_wahrscheinlichkeit'] ?: null,
            'sonstige_bemerkungen' => $data['sonstige_bemerkungen'] ?? null,
            'gesetzliche_regelungen' => $data['gesetzliche_regelungen'] ?? null,
            'sortierung' => ($maxPos['max_sort'] ?? 0) + 1
        ]);
    }

    /**
     * Vorgang aktualisieren
     */
    public function updateVorgang($id, $data) {
        $allowedFields = ['position', 'vorgang_beschreibung', 'gefaehrdung', 'gefaehrdung_faktor_id',
                         'schadenschwere', 'wahrscheinlichkeit', 'stop_s', 'stop_t', 'stop_o', 'stop_p',
                         'massnahmen', 'massnahme_schadenschwere', 'massnahme_wahrscheinlichkeit',
                         'sonstige_bemerkungen', 'gesetzliche_regelungen', 'maengel_behoben_von',
                         'maengel_behoben_am', 'sortierung'];

        $updateData = [];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field] !== '' ? $data[$field] : null;
            }
        }

        if (!empty($updateData)) {
            $this->db->update('vorgaenge', $updateData, 'id = :id', ['id' => $id]);
        }

        return true;
    }

    /**
     * Vorgang löschen
     */
    public function deleteVorgang($id) {
        $this->db->delete('vorgaenge', 'id = ?', [$id]);
        return true;
    }

    /**
     * Gefährdungskategorien abrufen
     */
    public function getKategorien() {
        return $this->db->fetchAll("SELECT * FROM gefaehrdung_kategorien ORDER BY sortierung, nummer");
    }

    /**
     * Gefährdungsfaktoren abrufen
     */
    public function getFaktoren($kategorieId = null) {
        $sql = "
            SELECT gf.*, gk.name as kategorie_name
            FROM gefaehrdung_faktoren gf
            JOIN gefaehrdung_kategorien gk ON gf.kategorie_id = gk.id
        ";

        $params = [];

        if ($kategorieId) {
            $sql .= " WHERE gf.kategorie_id = ?";
            $params[] = $kategorieId;
        }

        $sql .= " ORDER BY gk.sortierung, gf.sortierung, gf.nummer";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Beurteilung duplizieren
     */
    public function duplicate($id) {
        $original = $this->getById($id);
        if (!$original) {
            return false;
        }

        // Hauptbeurteilung kopieren
        $newGbId = $this->create([
            'unternehmen_id' => $original['unternehmen_id'],
            'arbeitsbereich_id' => $original['arbeitsbereich_id'],
            'titel' => $original['titel'] . ' (Kopie)',
            'ersteller_name' => $original['ersteller_name'],
            'erstelldatum' => date('Y-m-d'),
            'status' => 'entwurf',
            'bemerkungen' => $original['bemerkungen']
        ]);

        // Tätigkeiten kopieren
        foreach ($original['taetigkeiten'] as $taetigkeit) {
            $newTaetigkeitId = $this->createTaetigkeit([
                'gefaehrdungsbeurteilung_id' => $newGbId,
                'position' => $taetigkeit['position'],
                'name' => $taetigkeit['name'],
                'beschreibung' => $taetigkeit['beschreibung']
            ]);

            // Vorgänge kopieren
            foreach ($taetigkeit['vorgaenge'] as $vorgang) {
                $this->createVorgang([
                    'taetigkeit_id' => $newTaetigkeitId,
                    'position' => $vorgang['position'],
                    'vorgang_beschreibung' => $vorgang['vorgang_beschreibung'],
                    'gefaehrdung' => $vorgang['gefaehrdung'],
                    'gefaehrdung_faktor_id' => $vorgang['gefaehrdung_faktor_id'],
                    'schadenschwere' => $vorgang['schadenschwere'],
                    'wahrscheinlichkeit' => $vorgang['wahrscheinlichkeit'],
                    'stop_s' => $vorgang['stop_s'],
                    'stop_t' => $vorgang['stop_t'],
                    'stop_o' => $vorgang['stop_o'],
                    'stop_p' => $vorgang['stop_p'],
                    'massnahmen' => $vorgang['massnahmen'],
                    'massnahme_schadenschwere' => $vorgang['massnahme_schadenschwere'],
                    'massnahme_wahrscheinlichkeit' => $vorgang['massnahme_wahrscheinlichkeit'],
                    'sonstige_bemerkungen' => $vorgang['sonstige_bemerkungen'],
                    'gesetzliche_regelungen' => $vorgang['gesetzliche_regelungen']
                ]);
            }
        }

        return $newGbId;
    }
}
