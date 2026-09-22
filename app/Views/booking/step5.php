<div class="container main-content mt-3">
    <div class="wizard-progress">
        <div class="wizard-step completed"><div class="step-number"><i class="fa-solid fa-check"></i></div><span class="step-label">Especialidad</span></div>
        <div class="wizard-connector completed"></div>
        <div class="wizard-step completed"><div class="step-number"><i class="fa-solid fa-check"></i></div><span class="step-label">Médico</span></div>
        <div class="wizard-connector completed"></div>
        <div class="wizard-step completed"><div class="step-number"><i class="fa-solid fa-check"></i></div><span class="step-label">Fecha y Hora</span></div>
        <div class="wizard-connector completed"></div>
        <div class="wizard-step completed"><div class="step-number"><i class="fa-solid fa-check"></i></div><span class="step-label">Datos</span></div>
        <div class="wizard-connector completed"></div>
        <div class="wizard-step active"><div class="step-number">5</div><span class="step-label">Confirmación</span></div>
    </div>
    <form action="<?= $baseUrl ?>/booking/process" method="POST" enctype="multipart/form-data" id="bookingFinalizeForm">
        <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
        <input type="hidden" name="action" value="finalize">
        <div class="grid-3">
            <div class="card" style="grid-column: span 2;">
                <div class="card-header">
                    <h3>Información de Pago y Documentación</h3>
                </div>
                <div class="card-body">
                    <?php if (!$isEspecialidad): ?>
                        <div class="alert alert-warning mb-3">
                            <i class="fa-solid fa-circle-info"></i> El valor del servicio será determinado y confirmado a su correo electrónico.
                        </div>
                        <div class="form-group mb-3">
                            <label class="form-label" style="font-weight: 600;">
                                <i class="fa-solid fa-file-medical text-primary"></i> Subir Orden Médica o Documento <span class="text-danger">*</span>
                            </label>
                            <input type="file" name="payment_receipt" id="payment_receipt_input" class="form-control" accept="image/*,.pdf,application/pdf" required>
                            <span class="form-text text-muted">Formatos permitidos: Imagen (JPG, PNG, WEBP) o documento PDF. Máximo: 5MB.</span>
                        </div>
                    <?php else: ?>
                        <?php if ($fee > 0 && $showFee): ?>
                            <div class="alert alert-info mb-3">
                                <i class="fa-solid fa-circle-info"></i> El valor de la consulta es de <strong>$<?= number_format($fee, 2) ?></strong>. Por favor, realice la transferencia a los datos a continuación y suba el comprobante para confirmar su cita.
                            </div>
                            <div class="bank-info-card mb-3">
                                <h3><i class="fa-solid fa-building-columns"></i> Datos Bancarios</h3>
                                <div class="bank-info-row"><span class="label">Banco:</span> <span class="value"><?= htmlspecialchars($bankInfo['bank_name'] ?? 'Banco Pichincha') ?></span></div>
                                <div class="bank-info-row"><span class="label">Tipo de Cuenta:</span> <span class="value"><?= htmlspecialchars($bankInfo['bank_account_type'] ?? 'Corriente') ?></span></div>
                                <div class="bank-info-row"><span class="label">Número de Cuenta:</span> <span class="value"><?= htmlspecialchars($bankInfo['bank_account_number'] ?? '21000XXXX') ?></span></div>
                                <div class="bank-info-row"><span class="label">Titular:</span> <span class="value"><?= htmlspecialchars($bankInfo['bank_account_owner'] ?? 'Portal Salud S.A.') ?></span></div>
                                <div class="bank-info-row"><span class="label">RUC/CI:</span> <span class="value"><?= htmlspecialchars($bankInfo['bank_id_number'] ?? '179XXXXXXX001') ?></span></div>
                                <div class="bank-info-row"><span class="label">Correo:</span> <span class="value"><?= htmlspecialchars($bankInfo['bank_email'] ?? 'pagos@portalsalud.com') ?></span></div>
                            </div>
                            <div class="form-group mb-3">
                                <label class="form-label" style="font-weight: 600;">
                                    <i class="fa-solid fa-receipt text-primary"></i> Subir Comprobante de Pago o Documento <span class="text-danger">*</span>
                                </label>
                                <input type="file" name="payment_receipt" id="payment_receipt_input" class="form-control" accept="image/*,.pdf,application/pdf" required>
                                <span class="form-text text-muted">Formatos permitidos: Imagen (JPG, PNG, WEBP) o documento PDF. Máximo: 5MB.</span>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success mb-3">
                                <i class="fa-solid fa-circle-check"></i> Puede proceder a confirmar su cita sin pago previo.
                            </div>
                            <div class="form-group mb-3">
                                <label class="form-label" style="font-weight: 600;">
                                    <i class="fa-solid fa-paperclip text-primary"></i> Adjuntar Orden Médica o Documento (Opcional)
                                </label>
                                <input type="file" name="payment_receipt" id="payment_receipt_input" class="form-control" accept="image/*,.pdf,application/pdf">
                                <span class="form-text text-muted">Si dispone de una orden médica, examen previo o documento relevante, puede adjuntarlo (JPG, PNG, WEBP o PDF. Máx 5MB).</span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Contenedor de Vista Previa del Archivo -->
                    <div id="filePreviewContainer" class="mb-3" style="display: none;">
                        <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; background: var(--surface-2, #f8fafc); border: 1px solid var(--border, #cbd5e1); border-radius: 8px;">
                            <div id="filePreviewIcon" style="font-size: 1.75rem;"></div>
                            <div style="flex: 1; min-width: 0;">
                                <div id="filePreviewName" style="font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"></div>
                                <div id="filePreviewSize" style="font-size: 0.8rem; color: var(--text-muted, #64748b);"></div>
                            </div>
                            <button type="button" id="btnRemoveFile" class="btn btn-sm btn-outline-danger" style="padding: 0.25rem 0.5rem;" title="Quitar archivo">
                                <i class="fa-solid fa-xmark"></i> Quitar
                            </button>
                        </div>
                        <div id="imagePreviewWrapper" class="mt-2 text-center" style="display: none;">
                            <img id="imagePreviewImg" src="" style="max-height: 180px; max-width: 100%; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 1px solid #e2e8f0;" alt="Vista previa">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Notas o Motivo de Consulta (Opcional)</label>
                        <textarea name="notes" class="form-control" placeholder="Describa brevemente el motivo de su visita o requerimiento especial..."></textarea>
                    </div>
                    <div class="mt-3 d-flex justify-between">
                        <a href="<?= $baseUrl ?>/booking/step/4" class="btn btn-secondary">Volver</a>
                        <button type="submit" id="btnFinalizar" class="btn btn-success btn-lg"><i class="fa-regular fa-calendar-check"></i> Finalizar y Agendar</button>
                    </div>
                </div>
            </div>
            <div class="card bg-surface-2">
                <div class="card-header">
                    <h3>Resumen Final</h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <span class="text-muted text-sm d-block">Paciente</span>
                        <strong><?= htmlspecialchars($patient['name']) ?></strong><br>
                        <small class="text-muted">CI: <?= htmlspecialchars($patient['id_number']) ?></small><br>
                        <?php if(!empty($patient['email'])): ?>
                        <small class="text-muted"><i class="fa-solid fa-envelope"></i> <?= htmlspecialchars($patient['email']) ?></small><br>
                        <?php endif; ?>
                        <?php if(!empty($patient['phone'])): ?>
                        <small class="text-muted"><i class="fa-solid fa-phone"></i> <?= htmlspecialchars($patient['phone']) ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <span class="text-muted text-sm d-block">Médico</span>
                        <strong><?= htmlspecialchars($doctor['name']) ?></strong><br>
                        <small class="text-muted"><?= htmlspecialchars($doctor['specialty_name']) ?></small>
                    </div>
                    <div class="mb-3">
                        <span class="text-muted text-sm d-block">Fecha y Hora</span>
                        <strong><?php
                            $dias = ['Sunday' => 'Domingo', 'Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles', 'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado'];
                            $diaIngles = date('l', strtotime($slot['available_date']));
                            echo $dias[$diaIngles] . ', ' . date('d/m/Y', strtotime($slot['available_date']));
                        ?> a las <?= date('H:i', strtotime($slot['start_time'])) ?></strong>
                    </div>
                    <?php if ($fee > 0 && $showFee): ?>
                        <div class="mt-2 pt-2" style="border-top: 2px dashed var(--border);">
                            <span class="text-muted text-sm d-block">Total a pagar</span>
                            <strong class="text-primary" style="font-size: 1.5rem;">$<?= number_format($fee, 2) ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('payment_receipt_input');
    const previewContainer = document.getElementById('filePreviewContainer');
    const previewIcon = document.getElementById('filePreviewIcon');
    const previewName = document.getElementById('filePreviewName');
    const previewSize = document.getElementById('filePreviewSize');
    const imageWrapper = document.getElementById('imagePreviewWrapper');
    const imageImg = document.getElementById('imagePreviewImg');
    const btnRemove = document.getElementById('btnRemoveFile');

    if (!fileInput) return;

    fileInput.addEventListener('change', function() {
        const file = this.files[0];
        if (!file) {
            hidePreview();
            return;
        }

        // Validar tamaño máximo (5 MB = 5 * 1024 * 1024 bytes)
        const maxBytes = 5 * 1024 * 1024;
        if (file.size > maxBytes) {
            alert('El archivo seleccionado excede el límite de 5 MB. Por favor seleccione un archivo más pequeño.');
            fileInput.value = '';
            hidePreview();
            return;
        }

        // Validar extensión
        const ext = file.name.split('.').pop().toLowerCase();
        const allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'];
        if (!allowed.includes(ext)) {
            alert('Formato de archivo no permitido. Solo se aceptan imágenes (JPG, PNG, WEBP) o documentos PDF.');
            fileInput.value = '';
            hidePreview();
            return;
        }

        // Formatear tamaño
        const sizeFormatted = (file.size / 1024 / 1024).toFixed(2) + ' MB (' + (file.size / 1024).toFixed(0) + ' KB)';
        previewName.textContent = file.name;
        previewSize.textContent = sizeFormatted;

        if (ext === 'pdf') {
            previewIcon.innerHTML = '<i class="fa-solid fa-file-pdf text-danger"></i>';
            imageWrapper.style.display = 'none';
            imageImg.src = '';
        } else {
            previewIcon.innerHTML = '<i class="fa-solid fa-file-image text-primary"></i>';
            const reader = new FileReader();
            reader.onload = function(e) {
                imageImg.src = e.target.result;
                imageWrapper.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }

        previewContainer.style.display = 'block';
    });

    if (btnRemove) {
        btnRemove.addEventListener('click', function() {
            fileInput.value = '';
            hidePreview();
        });
    }

    function hidePreview() {
        if (previewContainer) previewContainer.style.display = 'none';
        if (imageWrapper) imageWrapper.style.display = 'none';
        if (imageImg) imageImg.src = '';
    }

    // ── Prevención de doble envío ──────────────────────────────────────────
    const bookingForm = document.getElementById('bookingFinalizeForm');
    const btnFinalizar = document.getElementById('btnFinalizar');

    if (bookingForm && btnFinalizar) {
        bookingForm.addEventListener('submit', function(e) {
            // Si ya fue enviado, bloquear segundo envío
            if (bookingForm.dataset.submitted === 'true') {
                e.preventDefault();
                return false;
            }
            // Marcar como enviado y actualizar UI
            bookingForm.dataset.submitted = 'true';
            btnFinalizar.disabled = true;
            btnFinalizar.innerHTML = '<span style="display:inline-block;width:16px;height:16px;border:2px solid rgba(255,255,255,0.5);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;vertical-align:middle;margin-right:6px;"></span> Procesando...';
            btnFinalizar.style.opacity = '0.85';
        });

        // Reactivar si el usuario regresa con el botón Atrás (bfcache)
        window.addEventListener('pageshow', function(evt) {
            if (evt.persisted) {
                bookingForm.dataset.submitted = 'false';
                btnFinalizar.disabled = false;
                btnFinalizar.innerHTML = '<i class="fa-regular fa-calendar-check"></i> Finalizar y Agendar';
                btnFinalizar.style.opacity = '';
            }
        });
    }

    // Keyframe de la animación spinner (inyectado una vez)
    if (!document.getElementById('spin-style')) {
        const st = document.createElement('style');
        st.id = 'spin-style';
        st.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
        document.head.appendChild(st);
    }
});
</script>
