<?php
/**
 * Redirect: Beurteilungen -> Projekte
 * Die Gefährdungsbeurteilungen werden jetzt pro Projekt verwaltet
 */

require_once __DIR__ . '/config/config.php';

setFlashMessage('info', 'Gefährdungsbeurteilungen werden jetzt direkt in Projekten verwaltet.');
redirect('projekte.php');
