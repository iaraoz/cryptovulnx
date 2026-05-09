<?php
$pageTitle = 'Login';
$isLoggedIn = false;
session_start();
if (isset($_SESSION['token'])) { header('Location: dashboard'); exit; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CryptoVulnX - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="login-container">
    <div class="login-card">
        <div class="login-logo">CryptoVulnX</div>
        <p class="text-center text-muted mb-4">Plataforma de Trading Crypto</p>

        <div class="alert-vuln mb-4" style="font-size:.8rem">
            <i class="bi bi-exclamation-triangle"></i>
            <strong>Entorno de laboratorio</strong> - App intencionalmente vulnerable para pentesting de APIs.
        </div>

        <!-- Tabs -->
        <ul class="nav nav-pills mb-4" role="tablist" style="gap:.5rem">
            <li class="nav-item flex-fill"><button class="nav-link active w-100" data-bs-toggle="pill" data-bs-target="#loginTab" style="border-radius:10px">Login</button></li>
            <li class="nav-item flex-fill"><button class="nav-link w-100" data-bs-toggle="pill" data-bs-target="#registerTab" style="border-radius:10px">Registro</button></li>
        </ul>

        <div class="tab-content">
            <!-- Login -->
            <div class="tab-pane fade show active" id="loginTab">
                <form id="loginForm">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Usuario</label>
                        <input type="text" class="form-control form-control-dark" id="loginUser" placeholder="Usuario" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Contrasena</label>
                        <input type="password" class="form-control form-control-dark" id="loginPass" placeholder="Contrasena" required>
                    </div>
                    <button type="submit" class="btn btn-gradient w-100 mb-3" id="loginBtn">
                        <i class="bi bi-box-arrow-in-right"></i> Iniciar Sesion
                    </button>
                    <div class="text-center">
                        <a href="#" class="text-muted small" data-bs-toggle="modal" data-bs-target="#resetModal">Olvidaste tu contrasena?</a>
                    </div>
                </form>
            </div>

            <!-- Register -->
            <div class="tab-pane fade" id="registerTab">
                <form id="registerForm">
                    <div class="mb-3"><input type="text" class="form-control form-control-dark" id="regUser" placeholder="Usuario" required></div>
                    <div class="mb-3"><input type="email" class="form-control form-control-dark" id="regEmail" placeholder="Email" required></div>
                    <div class="mb-3"><input type="password" class="form-control form-control-dark" id="regPass" placeholder="Contrasena" required></div>
                    <div class="mb-3"><input type="text" class="form-control form-control-dark" id="regName" placeholder="Nombre completo"></div>
                    <div class="mb-3"><input type="text" class="form-control form-control-dark" id="regRef" placeholder="Codigo de referido (opcional)"></div>
                    <button type="submit" class="btn btn-gradient w-100"><i class="bi bi-person-plus"></i> Crear Cuenta</button>
                </form>
            </div>
        </div>

        <!-- Cuentas de prueba -->
        <div class="mt-4 pt-3" style="border-top:1px solid rgba(255,255,255,0.1)">
            <p class="text-muted small mb-2"><i class="bi bi-info-circle"></i> Cuentas de prueba:</p>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-sm btn-outline-custom" onclick="$('#loginUser').val('carlos');$('#loginPass').val('carlos2024')">carlos</button>
                <button class="btn btn-sm btn-outline-custom" onclick="$('#loginUser').val('maria');$('#loginPass').val('maria2024')">maria</button>
                <button class="btn btn-sm btn-outline-custom" onclick="$('#loginUser').val('admin');$('#loginPass').val('admin123')">admin</button>
            </div>
        </div>
    </div>
</div>

<!-- Reset Modal -->
<div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Recuperar Contrasena</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div id="resetStep1">
                    <div class="mb-3"><input type="email" class="form-control form-control-dark" id="resetEmail" placeholder="tu@email.com"></div>
                    <button class="btn btn-gradient w-100" id="btnRequestPin">Enviar PIN</button>
                </div>
                <div id="resetStep2" style="display:none">
                    <div class="mb-3"><input type="text" class="form-control form-control-dark" id="resetPin" placeholder="PIN de 4 digitos" maxlength="4"></div>
                    <div class="mb-3"><input type="password" class="form-control form-control-dark" id="resetNewPass" placeholder="Nueva contrasena"></div>
                    <button class="btn btn-gradient w-100" id="btnVerifyPin">Cambiar Contrasena</button>
                </div>
                <div id="resetDebug" class="mt-3" style="display:none"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$('#loginForm').on('submit', function(e) {
    e.preventDefault();
    $('#loginBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
    $.ajax({
        url: 'api/v1/auth/login.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ username: $('#loginUser').val(), password: $('#loginPass').val() }),
        success: function(r) {
            // Guardar en session via PHP
            $.post('session', { action: 'login', token: r.token, user: JSON.stringify(r.user) }, function() {
                window.location.href = 'dashboard';
            });
        },
        error: function(xhr) {
            var msg = xhr.responseJSON ? xhr.responseJSON.error : 'Error de conexion';
            alert(msg);
            $('#loginBtn').prop('disabled', false).html('<i class="bi bi-box-arrow-in-right"></i> Iniciar Sesion');
        }
    });
});

$('#registerForm').on('submit', function(e) {
    e.preventDefault();
    $.ajax({
        url: 'api/v1/auth/register.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            username: $('#regUser').val(), email: $('#regEmail').val(), password: $('#regPass').val(),
            full_name: $('#regName').val(), referred_by: $('#regRef').val()
        }),
        success: function(r) {
            $.post('session', { action: 'login', token: r.token, user: JSON.stringify(r.user) }, function() {
                window.location.href = 'dashboard';
            });
        },
        error: function(xhr) { alert(xhr.responseJSON ? xhr.responseJSON.error : 'Error'); }
    });
});

$('#btnRequestPin').on('click', function() {
    $.ajax({
        url: 'api/v1/auth/reset.php', method: 'POST', contentType: 'application/json',
        data: JSON.stringify({ action: 'request', email: $('#resetEmail').val() }),
        success: function(r) {
            $('#resetStep1').hide();
            $('#resetStep2').show();
            if (r.debug_pin) {
                $('#resetDebug').show().html('<div class="alert-vuln" style="font-size:.8rem"><b>Debug:</b> PIN = ' + r.debug_pin + '</div>');
            }
        },
        error: function(xhr) { alert(xhr.responseJSON ? xhr.responseJSON.error : 'Error'); }
    });
});

$('#btnVerifyPin').on('click', function() {
    $.ajax({
        url: 'api/v1/auth/reset.php', method: 'POST', contentType: 'application/json',
        data: JSON.stringify({ action: 'verify', email: $('#resetEmail').val(), pin: $('#resetPin').val(), new_password: $('#resetNewPass').val() }),
        success: function() { alert('Contrasena actualizada!'); location.reload(); },
        error: function(xhr) { alert(xhr.responseJSON ? xhr.responseJSON.error : 'PIN invalido'); }
    });
});
</script>
</body>
</html>
