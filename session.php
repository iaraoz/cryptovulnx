<?php
// Manejo de sesion PHP - simple y directo
session_start();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'login') {
    $_SESSION['token'] = $_POST['token'];
    $_SESSION['user'] = json_decode($_POST['user'], true);
    echo json_encode(['ok' => true]);
} elseif ($action === 'logout') {
    session_destroy();
    echo json_encode(['ok' => true]);
}
