<?php $pageTitle = 'Wallets'; require 'includes/header.php'; if (!$isLoggedIn) { header('Location: index'); exit; } ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><i class="bi bi-wallet2"></i> Mis Wallets</h4>
    <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#transferModal"><i class="bi bi-send"></i> Nueva Transferencia</button>
</div>

<div class="row g-3 mb-4" id="walletsGrid"><span class="text-muted">Cargando wallets...</span></div>

<!-- Historial -->
<div class="card-dark">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Historial</h5>
        <div class="d-flex gap-2">
            <select class="form-select form-control-dark" style="width:auto;font-size:.85rem" id="fWallet"><option value="">Todas</option></select>
            <select class="form-select form-control-dark" style="width:auto;font-size:.85rem" id="fStatus">
                <option value="">Todos</option><option value="completed">Completed</option><option value="pending">Pending</option><option value="failed">Failed</option>
            </select>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-dark-custom">
            <thead><tr><th>TX Hash</th><th>Tipo</th><th>De</th><th>Para</th><th>Monto</th><th>Estado</th><th>Fecha</th></tr></thead>
            <tbody id="txTable"><tr><td colspan="7" class="text-muted text-center">Cargando...</td></tr></tbody>
        </table>
    </div>
</div>

<!-- Transfer Modal -->
<div class="modal fade" id="transferModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-send"></i> Transferir</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label text-muted small">Wallet origen</label><select class="form-select form-control-dark" id="txFrom"></select></div>
            <div class="mb-3"><label class="form-label text-muted small">Direccion destino</label><input type="text" class="form-control form-control-dark" id="txTo" placeholder="0xCARL0-0002-BTC-VULNX"></div>
            <div class="mb-3"><label class="form-label text-muted small">Monto</label><input type="number" step="0.00000001" class="form-control form-control-dark" id="txAmt" placeholder="0.001"></div>
            <div class="mb-3"><label class="form-label text-muted small">Descripcion</label><input type="text" class="form-control form-control-dark" id="txDesc" placeholder="Pago"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline-custom" data-bs-dismiss="modal">Cancelar</button>
            <button class="btn btn-gradient" id="btnTransfer"><i class="bi bi-send"></i> Enviar</button>
        </div>
    </div></div>
</div>

<?php $extraJS = '<script>
$(function() {
    function loadWallets() {
        api("/v1/wallets/balance.php").done(function(r) {
            var w = r.wallets || [], html = "", opts = "<option value=\"\">Todas</option>", sel = "";
            $.each(w, function(i, wl) {
                html += "<div class=\"col-md-4\"><div class=\"card-dark\">"
                    + "<div class=\"d-flex align-items-center gap-2 mb-2\">" + cryptoIcon(wl.currency) + "<h5 class=\"mb-0\">" + wl.currency + "</h5></div>"
                    + "<div class=\"stat-value mb-1\">" + fmtCrypto(wl.balance) + "</div>"
                    + "<small class=\"text-muted d-block\" style=\"word-break:break-all\">" + wl.wallet_address + "</small>"
                    + "<small class=\"text-muted\">ID: " + wl.id + "</small></div></div>";
                opts += "<option value=\"" + wl.id + "\">" + wl.currency + " (ID:" + wl.id + ")</option>";
                sel += "<option value=\"" + wl.id + "\">" + wl.currency + " - " + fmtCrypto(wl.balance) + "</option>";
            });
            $("#walletsGrid").html(html || "<p class=\"text-muted\">Sin wallets</p>");
            $("#fWallet").html(opts);
            $("#txFrom").html(sel);
        });
    }

    function loadTx() {
        var q = "/v1/wallets/history.php?";
        if ($("#fWallet").val()) q += "wallet_id=" + $("#fWallet").val() + "&";
        if ($("#fStatus").val()) q += "status=" + $("#fStatus").val();
        api(q).done(function(r) {
            var txs = r.transactions || [], html = "";
            $.each(txs, function(i, tx) {
                html += "<tr><td><code style=\"color:#6366f1;font-size:.8rem\">" + (tx.tx_hash||"-").substring(0,20) + "...</code></td>"
                    + "<td>" + tx.tx_type + "</td><td><small>" + (tx.from_address||"External").substring(0,18) + "</small></td>"
                    + "<td><small>" + (tx.to_address||"External").substring(0,18) + "</small></td>"
                    + "<td class=\"fw-bold\">" + fmtCrypto(tx.amount) + " " + tx.currency + "</td>"
                    + "<td>" + statusBadge(tx.status) + "</td><td><small>" + fmtDate(tx.created_at) + "</small></td></tr>";
            });
            $("#txTable").html(html || "<tr><td colspan=7 class=\"text-muted text-center\">Sin transacciones</td></tr>");
        });
    }

    loadWallets(); loadTx();
    $("#fWallet, #fStatus").on("change", loadTx);

    $("#btnTransfer").on("click", function() {
        api("/v1/wallets/transfer.php", {
            method: "POST",
            data: { from_wallet_id: $("#txFrom").val(), to_wallet_address: $("#txTo").val(), amount: parseFloat($("#txAmt").val()), description: $("#txDesc").val() }
        }).done(function(r) {
            toast("Transferencia exitosa! " + r.transaction.tx_hash);
            bootstrap.Modal.getInstance(document.getElementById("transferModal")).hide();
            loadWallets(); loadTx();
        }).fail(function(xhr) { toast(xhr.responseJSON ? xhr.responseJSON.error : "Error", "danger"); });
    });
});
</script>'; require 'includes/footer.php'; ?>
