<?php $pageTitle = 'KYC'; require 'includes/header.php'; if (!$isLoggedIn) { header('Location: index'); exit; } ?>

<h4 class="mb-4"><i class="bi bi-shield-check"></i> Verificacion KYC</h4>

<div class="row g-4">
    <div class="col-md-5">
        <div class="card-dark">
            <h5 class="mb-3">Subir Documento</h5>
            <div class="mb-3">
                <label class="form-label text-muted small">Tipo de documento</label>
                <select class="form-select form-control-dark" id="docType">
                    <option value="national_id">Documento Nacional</option><option value="passport">Pasaporte</option>
                    <option value="drivers_license">Licencia</option><option value="selfie">Selfie</option>
                </select>
            </div>

            <ul class="nav nav-pills mb-3" style="gap:.5rem">
                <li class="nav-item flex-fill"><button class="nav-link active w-100" data-bs-toggle="pill" data-bs-target="#tabFile" style="border-radius:8px;font-size:.85rem">Archivo</button></li>
                <li class="nav-item flex-fill"><button class="nav-link w-100" data-bs-toggle="pill" data-bs-target="#tabUrl" style="border-radius:8px;font-size:.85rem">URL</button></li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tabFile">
                    <div class="mb-3"><input type="file" class="form-control form-control-dark" id="docFile"></div>
                    <button class="btn btn-gradient w-100" id="btnUploadFile"><i class="bi bi-cloud-upload"></i> Subir</button>
                </div>
                <div class="tab-pane fade" id="tabUrl">
                    <div class="mb-3"><input type="url" class="form-control form-control-dark" id="docUrl" placeholder="https://example.com/doc.jpg"></div>
                    <button class="btn btn-gradient w-100" id="btnUploadUrl"><i class="bi bi-link-45deg"></i> Subir desde URL</button>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card-dark">
            <h5 class="mb-3">Estado de Documentos</h5>
            <div id="kycDocs"><span class="text-muted">Cargando...</span></div>
        </div>
        <div class="card-dark mt-4" id="respBox" style="display:none">
            <h6><i class="bi bi-code-slash"></i> Respuesta del servidor</h6>
            <pre id="respContent"></pre>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
$(function() {
    function loadDocs() {
        api("/v1/kyc/status.php").done(function(r) {
            var docs = r.kyc_documents || [], html = "";
            $.each(docs, function(i, d) {
                html += "<div class=\"d-flex justify-content-between py-3\" style=\"border-bottom:1px solid rgba(255,255,255,0.1)\">"
                    + "<div><b>" + d.document_type.replace("_"," ").toUpperCase() + "</b><br><small class=\"text-muted\">" + fmtDate(d.created_at) + "</small>"
                    + (d.file_path ? "<br><small class=\"text-muted\" style=\"word-break:break-all\">Path: " + d.file_path + "</small>" : "") + "</div>"
                    + statusBadge(d.status) + "</div>";
            });
            $("#kycDocs").html(html || "<p class=\"text-muted\">Sin documentos. Sube tu primer documento.</p>");
        });
    }
    function showResp(data) { $("#respBox").show(); $("#respContent").text(JSON.stringify(data, null, 2)); }

    $("#btnUploadFile").on("click", function() {
        var fd = new FormData();
        fd.append("document", $("#docFile")[0].files[0]);
        fd.append("document_type", $("#docType").val());
        $.ajax({ url: "api/v1/kyc/upload.php", method: "POST", data: fd, processData: false, contentType: false,
            headers: { Authorization: "Bearer " + TOKEN },
            success: function(r) { showResp(r); toast("Documento subido"); loadDocs(); },
            error: function(xhr) { showResp(xhr.responseJSON); toast("Error", "danger"); }
        });
    });

    $("#btnUploadUrl").on("click", function() {
        api("/v1/kyc/upload.php", { method: "POST", data: { document_type: $("#docType").val(), file_url: $("#docUrl").val() }
        }).done(function(r) { showResp(r); toast("Subido desde URL"); loadDocs(); })
          .fail(function(xhr) { showResp(xhr.responseJSON); toast("Error", "danger"); });
    });

    loadDocs();
});
</script>'; require 'includes/footer.php'; ?>
