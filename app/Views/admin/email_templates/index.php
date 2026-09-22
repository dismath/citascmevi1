<?php
$title = 'Plantillas de Correo';
ob_start();
?>
<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-envelope-open-text"></i> Diseño de Correo: Orden / Prefactura</h3>
        <p class="text-muted text-sm mb-0">Configura el diseño HTML que será enviado al paciente junto a su orden o prefactura médica.</p>
    </div>
    <div class="card-body">
        <div class="alert alert-info mb-4" style="background-color: #e3f2fd; border: 1px solid #90caf9; color: #0d47a1;">
            <strong><i class="fa-solid fa-circle-info"></i> Variables Dinámicas Disponibles:</strong>
            <ul style="margin-top: 5px; margin-bottom: 0;">
                <li><code>{patient_name}</code> - Nombre del paciente</li>
                <li><code>{patient_id}</code> - Identificación (cédula/RUC) del paciente</li>
                <li><code>{patient_phone}</code> - Teléfono del paciente</li>
                <li><code>{patient_email}</code> - Correo del paciente</li>
                <li><code>{order_id}</code> - Número de orden o prefactura (ej: 000123)</li>
                <li><code>{date}</code> - Fecha y hora de la cita programada</li>
                <li><code>{order_type_text}</code> - Tipo de orden (ej: "orden de imagenología", o solo "orden")</li>
                <li><code>{intro_text}</code> - Texto introductorio (ej: "ha sido registrada..." o "ha sido actualizada")</li>
                <li><code>{clinic_name}</code> - Nombre de la clínica o sistema</li>
                <li><code>{company_name}</code> - Nombre de la empresa (configurada en Ajustes)</li>
                <li><code>{company_address}</code> - Dirección de la empresa (configurada en Ajustes)</li>
                <li><code>{company_phone}</code> - Teléfono de la empresa (configurada en Ajustes)</li>
                <li><code>{company_email}</code> - Correo de la empresa (configurada en Ajustes)</li>
                <li><code>{company_website}</code> - Sitio / Página web de la empresa (configurada en Ajustes)</li>
            </ul>
            Estas variables serán reemplazadas automáticamente al momento de enviar el correo.
        </div>
        <form action="<?= $baseUrl ?>/admin/email-templates/update" method="POST">
            <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
            <div class="form-group mb-4">
                <label for="order_email_template" class="form-label">Plantilla del Correo HTML:</label>
                <textarea name="order_email_template" id="order_email_template" class="form-control" rows="15"><?= htmlspecialchars($order_email_template) ?></textarea>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Guardar Plantilla</button>
                <a href="<?= $baseUrl ?>/admin/email-templates" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    tinymce.init({
        selector: '#order_email_template',
        height: 500,
        menubar: false,
        plugins: [
            'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
            'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
            'insertdatetime', 'media', 'table', 'help', 'wordcount'
        ],
        toolbar: 'undo redo | blocks | ' +
        'bold italic backcolor | alignleft aligncenter ' +
        'alignright alignjustify | bullist numlist outdent indent | ' +
        'removeformat | code | help',
        content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px }',
        setup: function (editor) {
            editor.on('change', function () {
                editor.save(); // Sincroniza con el textarea original al cambiar
            });
        }
    });
});
</script>
<?php
$content = ob_get_clean();
require dirname(__DIR__, 2) . '/layouts/admin.php';
?>
