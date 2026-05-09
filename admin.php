<?php $pageTitle = 'Admin'; require 'includes/header.php'; if (!$isLoggedIn) { header('Location: index'); exit; } ?>

<div class="alert-vuln mb-4"><i class="bi bi-shield-exclamation"></i> <strong>Panel de Administracion</strong> - Acceso restringido.</div>

<ul class="nav nav-pills mb-4" style="gap:.5rem">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#usersTab" style="border-radius:10px"><i class="bi bi-people"></i> Usuarios</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#txTab" style="border-radius:10px"><i class="bi bi-receipt"></i> Transacciones</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#debugTab" style="border-radius:10px"><i class="bi bi-bug"></i> Debug</button></li>
</ul>

<div class="tab-content">
    <!-- Users -->
    <div class="tab-pane fade show active" id="usersTab">
        <div class="card-dark">
            <div class="d-flex justify-content-between mb-3">
                <h5 class="mb-0">Usuarios</h5>
                <div class="d-flex gap-2">
                    <input type="text" class="form-control form-control-dark" id="userSearch" placeholder="Buscar..." style="width:250px">
                    <button class="btn btn-gradient" id="btnSearch"><i class="bi bi-search"></i></button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-dark-custom">
                    <thead><tr><th>ID</th><th>Usuario</th><th>Email</th><th>Password</th><th>Rol</th><th>KYC</th><th>Creado</th></tr></thead>
                    <tbody id="usersTable"><tr><td colspan="7" class="text-muted text-center">Cargando...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Transactions -->
    <div class="tab-pane fade" id="txTab">
        <div class="card-dark">
            <h5 class="mb-3">Todas las Transacciones</h5>
            <div class="table-responsive">
                <table class="table table-dark-custom">
                    <thead><tr><th>ID</th><th>Hash</th><th>De</th><th>Para</th><th>Monto</th><th>Tipo</th><th>Estado</th><th>Fecha</th></tr></thead>
                    <tbody id="adminTx"><tr><td colspan="8" class="text-muted text-center">Cargando...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Debug -->
    <div class="tab-pane fade" id="debugTab">
        <div class="row g-3 mb-4">
            <div class="col-md-4"><button class="btn btn-outline-custom w-100 py-3" onclick="loadDebug('info')"><i class="bi bi-info-circle d-block" style="font-size:1.5rem"></i>Server Info</button></div>
            <div class="col-md-4"><button class="btn btn-outline-custom w-100 py-3" onclick="loadDebug('users')"><i class="bi bi-database d-block" style="font-size:1.5rem"></i>DB Dump</button></div>
            <div class="col-md-4"><button class="btn btn-outline-custom w-100 py-3" onclick="loadDebug('env')"><i class="bi bi-gear d-block" style="font-size:1.5rem"></i>Env Vars</button></div>
        </div>
        <div class="card-dark" id="debugBox" style="display:none">
            <h6><i class="bi bi-terminal"></i> Debug Output</h6>
            <pre id="debugContent"></pre>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
$(function() {
    function loadUsers(search) {
        var q = search ? "?search=" + encodeURIComponent(search) : "";
        api("/v1/admin/users.php" + q).done(function(r) {
            var html = "";
            $.each(r.users || [], function(i, u) {
                html += "<tr><td>" + u.id + "</td><td><b>" + u.username + "</b></td><td>" + u.email + "</td>"
                    + "<td><code style=\"color:#dc2626\">" + u.password_plain + "</code></td>"
                    + "<td>" + (u.role=="admin" ? "<span class=\"badge bg-danger\">admin</span>" : "user") + "</td>"
                    + "<td>" + (u.kyc_verified==1 ? "Si" : "No") + "</td><td><small>" + u.created_at + "</small></td></tr>";
            });
            $("#usersTable").html(html || "<tr><td colspan=7 class=\"text-muted text-center\">Sin resultados</td></tr>");
        }).fail(function(xhr) { $("#usersTable").html("<tr><td colspan=7 class=\"text-danger text-center\">" + (xhr.responseJSON ? xhr.responseJSON.error : "Error - necesitas rol admin") + "</td></tr>"); });
    }

    api("/v1/admin/transactions.php").done(function(r) {
        var html = "";
        $.each(r.transactions || [], function(i, tx) {
            html += "<tr><td>" + tx.id + "</td><td><code style=\"font-size:.75rem\">" + (tx.tx_hash||"-").substring(0,18) + "</code></td>"
                + "<td>" + (tx.from_user_id||"-") + "</td><td>" + (tx.to_user_id||"-") + "</td>"
                + "<td class=\"fw-bold\">" + tx.amount + " " + tx.currency + "</td><td>" + tx.tx_type + "</td>"
                + "<td>" + statusBadge(tx.status) + "</td><td><small>" + tx.created_at + "</small></td></tr>";
        });
        $("#adminTx").html(html || "<tr><td colspan=8>Error</td></tr>");
    }).fail(function(xhr) { $("#adminTx").html("<tr><td colspan=8 class=\"text-danger text-center\">" + (xhr.responseJSON ? xhr.responseJSON.error : "Error") + "</td></tr>"); });

    loadUsers();
    $("#btnSearch").on("click", function() { loadUsers($("#userSearch").val()); });
    $("#userSearch").on("keypress", function(e) { if (e.which==13) loadUsers($(this).val()); });
});

function loadDebug(action) {
    api("/v1/admin/debug.php?action=" + action).done(function(r) {
        $("#debugBox").show(); $("#debugContent").text(JSON.stringify(r, null, 2));
    }).fail(function(xhr) { $("#debugBox").show(); $("#debugContent").text(JSON.stringify(xhr.responseJSON, null, 2)); });
}
</script>'; require 'includes/footer.php'; ?>
