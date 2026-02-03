<?php
/**
 * Gefährdungsbeurteilung duplizieren
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Gefaehrdungsbeurteilung.php';

requireRole(ROLE_EDITOR);

$gbClass = new Gefaehrdungsbeurteilung();

$id = $_GET['id'] ?? null;
if (!$id) {
    redirect('beurteilungen.php');
}

$newId = $gbClass->duplicate($id);

if ($newId) {
    setFlashMessage('success', 'Gefährdungsbeurteilung wurde dupliziert.');
    redirect('beurteilung_edit.php?id=' . $newId);
} else {
    setFlashMessage('error', 'Fehler beim Duplizieren der Gefährdungsbeurteilung.');
    redirect('beurteilungen.php');
}
