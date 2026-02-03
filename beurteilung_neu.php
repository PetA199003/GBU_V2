<?php
/**
 * Redirect: Neue Beurteilung -> Projekte
 * Gefährdungen werden jetzt direkt in Projekten erstellt
 */

require_once __DIR__ . '/config/config.php';

setFlashMessage('info', 'Bitte wählen Sie ein Projekt aus, um Gefährdungen hinzuzufügen.');
redirect('projekte.php');
