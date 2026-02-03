<?php
/**
 * Logout
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Auth.php';

$auth = new Auth();
$auth->logout();

setFlashMessage('success', 'Sie wurden erfolgreich abgemeldet.');
redirect('login.php');
