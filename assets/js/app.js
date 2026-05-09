// CryptoVulnX - Utilidades globales

var API = 'api';
// TOKEN se inyecta desde PHP en footer.php

// AJAX helper con JWT
function api(endpoint, options) {
    options = options || {};
    var settings = {
        url: API + endpoint,
        contentType: 'application/json',
        dataType: 'json',
        headers: {}
    };
    $.extend(settings, options);
    if (TOKEN) settings.headers['Authorization'] = 'Bearer ' + TOKEN;
    if (options.data && typeof options.data === 'object' && options.method !== 'GET') {
        settings.data = JSON.stringify(options.data);
    }
    return $.ajax(settings);
}

// Toast (shadcn style)
function toast(msg, type) {
    type = type || 'success';
    var colors = { success: '#22c55e', danger: '#dc2626', warning: '#eab308', info: '#6366f1' };
    var el = $('<div>').css({
        position: 'fixed', top: '72px', right: '16px', zIndex: 9999,
        background: '#171717', border: '1px solid rgba(255,255,255,0.1)',
        color: '#fafafa', padding: '10px 16px', borderRadius: '0.625rem',
        minWidth: '260px', boxShadow: '0 25px 50px -12px rgba(0,0,0,.5)',
        fontSize: '0.875rem', display: 'flex', alignItems: 'center', gap: '8px'
    }).html('<span style="width:6px;height:6px;border-radius:50%;background:' + (colors[type] || colors.info) + ';flex-shrink:0"></span>' + msg).appendTo('body');
    setTimeout(function() { el.fadeOut(300, function() { el.remove(); }); }, 4000);
}

// Formateo
function fmtCrypto(n) { return parseFloat(n || 0).toFixed(8); }
function fmtUSD(n) { return '$' + parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function fmtDate(d) { return d ? new Date(d).toLocaleString('es-ES') : '-'; }

function statusBadge(s) {
    var cls = { completed: 'badge-completed', pending: 'badge-pending', failed: 'badge-failed', approved: 'badge-completed', rejected: 'badge-failed' };
    return '<span class="badge-status ' + (cls[s] || 'badge-pending') + '">' + s + '</span>';
}

function cryptoIcon(sym) {
    var c = { BTC: 'crypto-btc', ETH: 'crypto-eth', USDT: 'crypto-usdt' };
    return '<span class="crypto-icon ' + (c[sym] || 'crypto-btc') + '">' + (sym || '?').charAt(0) + '</span>';
}

// Divisor reutilizable
var DIVIDER = 'border-bottom:1px solid rgba(255,255,255,0.1)';
