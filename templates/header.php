<?php
require_once __DIR__ . '/../config/config.php';
$flash = getFlashMessage();
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? sanitize($pageTitle) . ' - ' : '' ?><?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php if (isLoggedIn()): ?>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= BASE_URL ?>/index.php">
                <i class="bi bi-shield-check me-2"></i><?= APP_NAME ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/index.php">
                            <i class="bi bi-house-door me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/beurteilungen.php">
                            <i class="bi bi-file-earmark-text me-1"></i>Beurteilungen
                        </a>
                    </li>
                    <?php if (hasRole(ROLE_EDITOR)): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-book me-1"></i>Bibliothek
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/bibliothek/gefaehrdungen.php">
                                <i class="bi bi-exclamation-triangle me-2"></i>Gefährdungen
                            </a></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/bibliothek/massnahmen.php">
                                <i class="bi bi-check2-circle me-2"></i>Maßnahmen
                            </a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    <?php if (hasRole(ROLE_ADMIN)): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-gear me-1"></i>Verwaltung
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/benutzer.php">
                                <i class="bi bi-people me-2"></i>Benutzer
                            </a></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/projekte.php">
                                <i class="bi bi-folder me-2"></i>Projekte
                            </a></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/kategorien.php">
                                <i class="bi bi-tags me-2"></i>Kategorien
                            </a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i>
                            <?= sanitize($currentUser['voller_name']) ?>
                            <span class="badge bg-light text-primary ms-1">
                                <?= $GLOBALS['ROLE_NAMES'][$currentUser['rolle']] ?? '' ?>
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/profil.php">
                                <i class="bi bi-person me-2"></i>Mein Profil
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>Abmelden
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <?php endif; ?>

    <main class="<?= isLoggedIn() ? 'container-fluid py-4' : '' ?>">
        <?php if ($flash): ?>
        <div class="container">
            <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show">
                <?= sanitize($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
        <?php endif; ?>
