<?php $pageTitle = 'Dashboard'; require 'includes/header.php'; if (!$isLoggedIn) { header('Location: index'); exit; } ?>

<!-- Price Ticker -->
<div class="card-dark mb-4 p-3">
    <div class="d-flex gap-4 flex-wrap" id="priceTicker"><span class="text-muted">Cargando precios...</span></div>
</div>

<!-- Chart -->
<div class="card-dark mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h6 class="mb-0" style="font-weight:600">Mercado en vivo</h6>
            <small class="text-muted" id="chartPair">BTC/USDT</small>
        </div>
        <div class="d-flex gap-1">
            <button class="btn btn-sm btn-ghost chart-sym active" data-sym="BTCUSDT">BTC</button>
            <button class="btn btn-sm btn-ghost chart-sym" data-sym="ETHUSDT">ETH</button>
            <button class="btn btn-sm btn-ghost chart-sym" data-sym="BNBUSDT">BNB</button>
            <button class="btn btn-sm btn-ghost chart-sym" data-sym="SOLUSDT">SOL</button>
            <span style="width:1px;background:var(--border);margin:0 4px"></span>
            <button class="btn btn-sm btn-ghost chart-tf active" data-tf="1h">1H</button>
            <button class="btn btn-sm btn-ghost chart-tf" data-tf="4h">4H</button>
            <button class="btn btn-sm btn-ghost chart-tf" data-tf="1d">1D</button>
            <button class="btn btn-sm btn-ghost chart-tf" data-tf="1w">1W</button>
        </div>
    </div>
    <div id="chart" style="height:400px;border-radius:var(--radius);overflow:hidden"></div>
    <div class="d-flex gap-4 mt-3" id="chartInfo">
        <small class="text-muted">O: <span id="cOpen">-</span></small>
        <small class="text-muted">H: <span id="cHigh" style="color:var(--success)">-</span></small>
        <small class="text-muted">L: <span id="cLow" style="color:var(--destructive)">-</span></small>
        <small class="text-muted">C: <span id="cClose">-</span></small>
        <small class="text-muted">Vol: <span id="cVol">-</span></small>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="stat-card"><i class="bi bi-currency-dollar stat-icon"></i><div class="stat-label">Balance Total (USD)</div><div class="stat-value" id="totalBal">$0.00</div></div></div>
    <div class="col-md-3"><div class="stat-card"><i class="bi bi-wallet2 stat-icon"></i><div class="stat-label">Wallets</div><div class="stat-value" id="walletCnt">0</div></div></div>
    <div class="col-md-3"><div class="stat-card"><i class="bi bi-arrow-left-right stat-icon"></i><div class="stat-label">Transacciones</div><div class="stat-value" id="txCnt">0</div></div></div>
    <div class="col-md-3"><div class="stat-card"><i class="bi bi-shield-check stat-icon"></i><div class="stat-label">KYC</div><div class="stat-value" id="kycStat" style="font-size:1rem"><?= $user['kyc_verified'] ? '<span style="color:var(--success)">Verificado</span>' : '<span style="color:var(--warning)">Pendiente</span>' ?></div></div></div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card-dark">
            <div class="d-flex justify-content-between mb-3"><h6 class="mb-0" style="font-weight:600">Mis Wallets</h6><a href="wallet" class="btn btn-sm btn-ghost" style="font-size:.8rem">Ver todas <i class="bi bi-arrow-right"></i></a></div>
            <div id="walletsList"><span class="text-muted">Cargando...</span></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card-dark">
            <div class="d-flex justify-content-between mb-3"><h6 class="mb-0" style="font-weight:600">Transacciones recientes</h6><a href="wallet" class="btn btn-sm btn-ghost" style="font-size:.8rem">Ver todas <i class="bi bi-arrow-right"></i></a></div>
            <div id="txList"><span class="text-muted">Cargando...</span></div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row g-3 mt-4">
    <div class="col-md-4"><a href="wallet" class="card-dark d-block text-decoration-none text-center py-4"><i class="bi bi-send" style="font-size:1.5rem;color:var(--muted-foreground)"></i><div class="mt-2" style="font-size:.875rem;color:var(--foreground)">Transferir</div><small class="text-muted">Enviar crypto</small></a></div>
    <div class="col-md-4"><a href="swap" class="card-dark d-block text-decoration-none text-center py-4"><i class="bi bi-arrow-left-right" style="font-size:1.5rem;color:var(--muted-foreground)"></i><div class="mt-2" style="font-size:.875rem;color:var(--foreground)">Swap</div><small class="text-muted">Intercambiar tokens</small></a></div>
    <div class="col-md-4"><a href="kyc" class="card-dark d-block text-decoration-none text-center py-4"><i class="bi bi-shield-check" style="font-size:1.5rem;color:var(--muted-foreground)"></i><div class="mt-2" style="font-size:.875rem;color:var(--foreground)">Verificar KYC</div><small class="text-muted">Subir documentos</small></a></div>
</div>

<?php $extraJS = '
<script src="https://unpkg.com/lightweight-charts@4.1.0/dist/lightweight-charts.standalone.production.js"></script>
<script>
$(function() {
    var prices = {};

    // ── Precios ──
    api("/v1/crypto/prices.php").done(function(r) {
        var html = "";
        $.each(r.prices, function(i, p) {
            prices[p.symbol] = p.price_usd;
            var cls = p.change_24h >= 0 ? "price-change-up" : "price-change-down";
            html += "<div class=\"d-flex align-items-center gap-2 text-nowrap\">" + cryptoIcon(p.symbol) + " <b>" + p.symbol + "</b> " + fmtUSD(p.price_usd) + " <small class=\"" + cls + "\">" + (p.change_24h >= 0 ? "+" : "") + p.change_24h + "%</small></div>";
        });
        $("#priceTicker").html(html);

        api("/v1/wallets/balance.php").done(function(w) {
            var wallets = w.wallets || [], total = 0, html = "";
            $("#walletCnt").text(wallets.length);
            $.each(wallets, function(i, wl) {
                var usd = wl.balance * (prices[wl.currency] || 0);
                total += usd;
                html += "<div class=\"d-flex justify-content-between py-2\" style=\"border-bottom:1px solid rgba(255,255,255,0.1)\">"
                    + "<div class=\"d-flex align-items-center gap-2\">" + cryptoIcon(wl.currency) + "<div><b>" + wl.currency + "</b><br><small class=\"text-muted\">" + wl.wallet_address.substring(0,20) + "...</small></div></div>"
                    + "<div class=\"text-end\"><b>" + fmtCrypto(wl.balance) + "</b><br><small class=\"text-muted\">" + fmtUSD(usd) + "</small></div></div>";
            });
            $("#walletsList").html(html || "<p class=\"text-muted\">Sin wallets</p>");
            $("#totalBal").text(fmtUSD(total));
        });
    });

    // ── Transacciones ──
    api("/v1/wallets/history.php").done(function(r) {
        var txs = (r.transactions || []).slice(0, 5), html = "";
        $("#txCnt").text(r.total_count || 0);
        $.each(txs, function(i, tx) {
            html += "<div class=\"d-flex justify-content-between py-2\" style=\"border-bottom:1px solid rgba(255,255,255,0.1)\">"
                + "<div><b>" + tx.tx_type.toUpperCase() + "</b><br><small class=\"text-muted\">" + fmtDate(tx.created_at) + "</small></div>"
                + "<div class=\"text-end\"><b>" + fmtCrypto(tx.amount) + " " + tx.currency + "</b><br>" + statusBadge(tx.status) + "</div></div>";
        });
        $("#txList").html(html || "<p class=\"text-muted\">Sin transacciones</p>");
    });

    // ── Candlestick Chart (Binance API + Lightweight Charts) ──
    var chart = LightweightCharts.createChart(document.getElementById("chart"), {
        layout: { background: { color: "#171717" }, textColor: "#a1a1aa", fontSize: 12, fontFamily: "Inter, sans-serif" },
        grid: { vertLines: { color: "rgba(255,255,255,0.04)" }, horzLines: { color: "rgba(255,255,255,0.04)" } },
        crosshair: { mode: LightweightCharts.CrosshairMode.Normal, vertLine: { color: "rgba(255,255,255,0.1)", labelBackgroundColor: "#262626" }, horzLine: { color: "rgba(255,255,255,0.1)", labelBackgroundColor: "#262626" } },
        rightPriceScale: { borderColor: "rgba(255,255,255,0.1)", scaleMargins: { top: 0.1, bottom: 0.2 } },
        timeScale: { borderColor: "rgba(255,255,255,0.1)", timeVisible: true, secondsVisible: false },
        handleScroll: true, handleScale: true
    });

    var candleSeries = chart.addCandlestickSeries({
        upColor: "#22c55e", downColor: "#ef4444", borderDownColor: "#ef4444", borderUpColor: "#22c55e",
        wickDownColor: "#ef4444", wickUpColor: "#22c55e"
    });

    var volumeSeries = chart.addHistogramSeries({
        priceFormat: { type: "volume" }, priceScaleId: "",
        scaleMargins: { top: 0.8, bottom: 0 }
    });

    var currentSym = "BTCUSDT", currentTf = "1h";

    function loadChart(symbol, interval) {
        currentSym = symbol; currentTf = interval;
        $("#chartPair").text(symbol.replace("USDT", "/USDT"));

        $.getJSON("https://api.binance.com/api/v3/klines", { symbol: symbol, interval: interval, limit: 200 }, function(data) {
            var candles = [], volumes = [];
            $.each(data, function(i, k) {
                var t = k[0] / 1000;
                var o = parseFloat(k[1]), h = parseFloat(k[2]), l = parseFloat(k[3]), c = parseFloat(k[4]), v = parseFloat(k[5]);
                candles.push({ time: t, open: o, high: h, low: l, close: c });
                volumes.push({ time: t, value: v, color: c >= o ? "rgba(34,197,94,0.3)" : "rgba(239,68,68,0.3)" });
            });
            candleSeries.setData(candles);
            volumeSeries.setData(volumes);
            chart.timeScale().fitContent();

            // Mostrar ultimo dato
            if (candles.length) {
                var last = candles[candles.length - 1];
                var lastVol = volumes[volumes.length - 1];
                $("#cOpen").text(fmtUSD(last.open));
                $("#cHigh").text(fmtUSD(last.high));
                $("#cLow").text(fmtUSD(last.low));
                $("#cClose").text(fmtUSD(last.close)).css("color", last.close >= last.open ? "var(--success)" : "var(--destructive)");
                $("#cVol").text(lastVol.value.toLocaleString("en-US", {maximumFractionDigits:0}));
            }
        }).fail(function() {
            $("#chart").html("<div class=\"d-flex align-items-center justify-content-center h-100 text-muted\">No se pudo cargar datos de Binance</div>");
        });
    }

    // Crosshair move - OHLCV en tiempo real
    chart.subscribeCrosshairMove(function(param) {
        if (!param.time || !param.seriesData) return;
        var c = param.seriesData.get(candleSeries);
        var v = param.seriesData.get(volumeSeries);
        if (c) {
            $("#cOpen").text(fmtUSD(c.open));
            $("#cHigh").text(fmtUSD(c.high));
            $("#cLow").text(fmtUSD(c.low));
            $("#cClose").text(fmtUSD(c.close)).css("color", c.close >= c.open ? "var(--success)" : "var(--destructive)");
        }
        if (v) $("#cVol").text(v.value.toLocaleString("en-US", {maximumFractionDigits:0}));
    });

    // Botones de simbolo
    $(".chart-sym").on("click", function() {
        $(".chart-sym").removeClass("active");
        $(this).addClass("active");
        loadChart($(this).data("sym"), currentTf);
    });

    // Botones de timeframe
    $(".chart-tf").on("click", function() {
        $(".chart-tf").removeClass("active");
        $(this).addClass("active");
        loadChart(currentSym, $(this).data("tf"));
    });

    // Resize
    $(window).on("resize", function() { chart.applyOptions({ width: $("#chart").width() }); });

    // Cargar
    loadChart("BTCUSDT", "1h");
});
</script>'; require 'includes/footer.php'; ?>
