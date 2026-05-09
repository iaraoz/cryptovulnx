<?php
session_start();
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../api/config/jwt.php';
require_once __DIR__ . '/../api/config/helpers.php';

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$isLoggedIn = isset($_SESSION['token']);
$user = $_SESSION['user'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CryptoVulnX<?= isset($pageTitle) ? " - $pageTitle" : '' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php if ($isLoggedIn): ?>
<nav class="navbar navbar-expand-lg navbar-custom fixed-top">
    <div class="container-fluid px-4" style="max-width:1280px;margin:0 auto">
        <a class="navbar-brand" href="dashboard"><i class="bi bi-currency-bitcoin"></i> CryptoVulnX</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto gap-1">
                <li class="nav-item"><a class="nav-link <?= $currentPage == 'dashboard' ? 'active' : '' ?>" href="dashboard">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link <?= $currentPage == 'wallet' ? 'active' : '' ?>" href="wallet">Wallets</a></li>
                <li class="nav-item"><a class="nav-link <?= $currentPage == 'swap' ? 'active' : '' ?>" href="swap">Swap</a></li>
                <li class="nav-item"><a class="nav-link <?= $currentPage == 'kyc' ? 'active' : '' ?>" href="kyc">KYC</a></li>
                <li class="nav-item"><a class="nav-link <?= $currentPage == 'webhooks' ? 'active' : '' ?>" href="webhooks">Webhooks</a></li>
                <?php if ($user && $user['role'] === 'admin'): ?>
                <li class="nav-item"><a class="nav-link <?= $currentPage == 'admin' ? 'active' : '' ?>" href="admin">Admin</a></li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav align-items-center gap-2">
                <li class="nav-item">
                    <span class="nav-link" style="font-size:.8rem;color:var(--muted-foreground)!important;cursor:default">
                        <?= htmlspecialchars($user['username'] ?? '') ?>
                        <?= ($user['role'] ?? '') === 'admin' ? '<span class="badge bg-danger" style="font-size:.65rem">admin</span>' : '' ?>
                    </span>
                </li>
                <li class="nav-item"><a class="nav-link" href="logout" style="font-size:.8rem"><i class="bi bi-box-arrow-right"></i></a></li>
            </ul>
        </div>
    </div>
</nav>
<div class="main-content">
<?php endif; ?>
