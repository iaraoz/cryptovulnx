<?php $pageTitle = 'Swap'; require 'includes/header.php'; if (!$isLoggedIn) { header('Location: index'); exit; } ?>

<h4 class="mb-4"><i class="bi bi-arrow-left-right"></i> Swap de Criptomonedas</h4>

<div class="row g-4">
    <div class="col-md-5">
        <div class="card-dark">
            <h5 class="mb-4">Intercambiar</h5>
            <div class="mb-3">
                <label class="form-label text-muted small">De</label>
                <div class="d-flex gap-2">
                    <select class="form-select form-control-dark" id="fromCur" style="width:120px"><option>BTC</option><option>ETH</option><option>USDT</option><option>BNB</option><option>SOL</option></select>
                    <input type="number" step="0.00000001" class="form-control form-control-dark" id="swapAmt" placeholder="0.00">
                </div>
                <small class="text-muted">Balance: <span id="fromBal">0.00</span></small>
            </div>
            <div class="text-center my-3">
                <button class="btn btn-sm btn-outline-custom" id="btnSwapDir" style="border-radius:50%;width:40px;height:40px"><i class="bi bi-arrow-down-up"></i></button>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted small">Para</label>
                <div class="d-flex gap-2">
                    <select class="form-select form-control-dark" id="toCur" style="width:120px"><option>ETH</option><option>BTC</option><option>USDT</option><option>BNB</option><option>SOL</option></select>
                    <input type="number" class="form-control form-control-dark" id="swapRes" placeholder="0.00" readonly>
                </div>
            </div>
            <div class="card-dark mb-3" style="background:var(--background);padding:1rem">
                <div class="d-flex justify-content-between small"><span class="text-muted">Tasa:</span><span id="swapRate">-</span></div>
                <div class="d-flex justify-content-between small mt-1"><span class="text-muted">Fee:</span><span style="color:#22c55e">0%</span></div>
            </div>
            <button class="btn btn-gradient w-100" id="btnSwap"><i class="bi bi-arrow-left-right"></i> Ejecutar Swap</button>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card-dark">
            <h5 class="mb-3"><i class="bi bi-graph-up"></i> Precios</h5>
            <div class="table-responsive">
                <table class="table table-dark-custom">
                    <thead><tr><th>Moneda</th><th>Precio USD</th><th>24h</th><th>Volumen</th></tr></thead>
                    <tbody id="pricesTable"><tr><td colspan="4" class="text-muted text-center">Cargando...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
$(function() {
    var prices = {}, balances = {};

    function load() {
        $.when(api("/v1/crypto/prices.php"), api("/v1/wallets/balance.php")).done(function(pr, wl) {
            // Precios
            var html = "";
            $.each(pr[0].prices, function(i, p) {
                prices[p.symbol] = p.price_usd;
                var cls = p.change_24h >= 0 ? "price-change-up" : "price-change-down";
                html += "<tr><td>" + cryptoIcon(p.symbol) + " <b>" + p.symbol + "</b></td><td class=\"fw-bold\">" + fmtUSD(p.price_usd) + "</td>"
                    + "<td class=\"" + cls + "\">" + (p.change_24h>=0?"+":"") + p.change_24h + "%</td><td>" + fmtUSD(p.volume_24h) + "</td></tr>";
            });
            $("#pricesTable").html(html);
            // Balances
            $.each(wl[0].wallets || [], function(i, w) { balances[w.currency] = w.balance; });
            updateBal();
        });
    }

    function updateBal() { $("#fromBal").text(fmtCrypto(balances[$("#fromCur").val()] || 0)); }

    function calc() {
        var f = $("#fromCur").val(), t = $("#toCur").val(), a = parseFloat($("#swapAmt").val()) || 0;
        if (prices[f] && prices[t] && a > 0) {
            var rate = prices[f] / prices[t];
            $("#swapRes").val((a * rate).toFixed(8));
            $("#swapRate").text("1 " + f + " = " + rate.toFixed(8) + " " + t);
        }
    }

    $("#fromCur, #toCur").on("change", function() { updateBal(); calc(); });
    $("#swapAmt").on("input", calc);
    $("#btnSwapDir").on("click", function() {
        var f = $("#fromCur").val(), t = $("#toCur").val();
        $("#fromCur").val(t); $("#toCur").val(f); updateBal(); calc();
    });

    $("#btnSwap").on("click", function() {
        api("/v1/crypto/swap", { method: "POST", data: { from_currency: $("#fromCur").val(), to_currency: $("#toCur").val(), amount: parseFloat($("#swapAmt").val()) }
        }).done(function(r) {
            toast("Swap completado! " + r.swap.amount + " " + r.swap.from_currency + " -> " + r.swap.result_amount.toFixed(8) + " " + r.swap.to_currency);
            load();
        }).fail(function(xhr) { toast(xhr.responseJSON ? xhr.responseJSON.error : "Error", "danger"); });
    });

    load();
});
</script>'; require 'includes/footer.php'; ?>
