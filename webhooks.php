<?php $pageTitle = 'Webhooks'; require 'includes/header.php'; if (!$isLoggedIn) { header('Location: index'); exit; } ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="bi bi-broadcast"></i> Webhooks</h4>
    <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#whModal"><i class="bi bi-plus-lg"></i> Nuevo</button>
</div>

<div class="card-dark">
    <div class="table-responsive">
        <table class="table table-dark-custom">
            <thead><tr><th>ID</th><th>URL</th><th>Evento</th><th>Secret</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody id="whTable"><tr><td colspan="6" class="text-muted text-center">Cargando...</td></tr></tbody>
        </table>
    </div>
</div>

<div class="card-dark mt-4" id="testBox" style="display:none">
    <h6><i class="bi bi-terminal"></i> Resultado Test</h6>
    <pre id="testContent"></pre>
</div>

<!-- Modal -->
<div class="modal fade" id="whModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Nuevo Webhook</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label text-muted small">URL</label><input type="url" class="form-control form-control-dark" id="whUrl" placeholder="https://tu-server.com/hook"></div>
        <div class="mb-3"><label class="form-label text-muted small">Evento</label><select class="form-select form-control-dark" id="whEvent"><option value="transaction">Transacciones</option><option value="swap">Swaps</option><option value="kyc_update">KYC</option><option value="login">Login</option></select></div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-custom" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-gradient" id="btnCreateWh">Crear</button></div>
</div></div></div>

<?php $extraJS = '<script>
$(function() {
    function load() {
        api("/v1/webhook/notify.php").done(function(r) {
            var h = r.webhooks || [], html = "";
            $.each(h, function(i, w) {
                html += "<tr><td>" + w.id + "</td><td><code style=\"color:#6366f1;font-size:.8rem\">" + w.url + "</code></td>"
                    + "<td>" + w.event_type + "</td><td><code style=\"color:#eab308;font-size:.75rem\">" + w.secret + "</code></td>"
                    + "<td>" + (w.is_active ? statusBadge("completed") : statusBadge("failed")) + "</td>"
                    + "<td><button class=\"btn btn-sm btn-outline-custom\" onclick=\"testWh(" + w.id + ")\"><i class=\"bi bi-play\"></i> Test</button></td></tr>";
            });
            $("#whTable").html(html || "<tr><td colspan=6 class=\"text-muted text-center\">Sin webhooks</td></tr>");
        });
    }
    window.testWh = function(id) {
        api("/v1/webhook/notify.php", { method: "POST", data: { action: "test", webhook_id: id } })
        .done(function(r) { $("#testBox").show(); $("#testContent").text(JSON.stringify(r, null, 2)); })
        .fail(function(xhr) { $("#testBox").show(); $("#testContent").text(JSON.stringify(xhr.responseJSON, null, 2)); });
    };
    $("#btnCreateWh").on("click", function() {
        api("/v1/webhook/notify.php", { method: "POST", data: { action: "create", url: $("#whUrl").val(), event_type: $("#whEvent").val() } })
        .done(function() { toast("Webhook creado"); bootstrap.Modal.getInstance(document.getElementById("whModal")).hide(); load(); })
        .fail(function(xhr) { toast(xhr.responseJSON ? xhr.responseJSON.error : "Error", "danger"); });
    });
    load();
});
</script>'; require 'includes/footer.php'; ?>
