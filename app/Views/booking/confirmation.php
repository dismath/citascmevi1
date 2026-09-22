<div class="container main-content mt-3">
    <div class="confirmation-card">
        <div class="confirmation-header">
            <div style="font-size: 3rem; margin-bottom: 0rem;"><i class="fa-regular fa-circle-check"></i></div>
            <h2>¡Pedido recibido exitosamente!</h2>
            <p>Su solicitud se ha generado correctamente. Una vez verificado, recibirá la confirmación de su cita o servicio en su correo electrónico.</p>
        </div>
        <div class="confirmation-body">
            <h3 class="mb-3 text-primary border-bottom pb-1">Detalles de la Solicitud #<?= str_pad($appointment['id'], 5, '0', STR_PAD_LEFT) ?></h3>
            <div class="confirmation-row">
                <span class="label">Paciente:</span>
                <span class="value">
                    <strong><?= htmlspecialchars($appointment['patient_name'] ?? '') ?></strong> <small class="text-muted">(CI: <?= htmlspecialchars($appointment['patient_id_number'] ?? '') ?>)</small><br>
                    <?php if(!empty($appointment['patient_email'])): ?>
                    <small class="text-muted"><i class="fa-solid fa-envelope"></i> <?= htmlspecialchars($appointment['patient_email']) ?></small><br>
                    <?php endif; ?>
                    <?php if(!empty($appointment['phone'])): ?>
                    <small class="text-muted"><i class="fa-solid fa-phone"></i> <?= htmlspecialchars($appointment['phone']) ?></small>
                    <?php endif; ?>
                </span>
            </div>
            <div class="confirmation-row">
                <span class="label">Especialidad/Servicio:</span>
                <span class="value"><?= htmlspecialchars($appointment['specialty_name'] ?? '') ?></span>
            </div>
            <div class="confirmation-row">
                <span class="label">Atiende:</span>
                <span class="value"><?= htmlspecialchars($appointment['doctor_name'] ?? '') ?></span>
            </div>
            <div class="confirmation-row">
                <span class="label">Fecha de Cita:</span>
                <span class="value text-primary font-weight-bold" style="font-size: 1.1rem; font-weight: 700;">
                    <i class="fa-regular fa-calendar"></i> <?php
                    $dias = ['Sunday' => 'Domingo', 'Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles', 'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado'];
                    $diaIngles = date('l', strtotime($appointment['appointment_date']));
                    echo $dias[$diaIngles] . ', ' . date('d/m/Y', strtotime($appointment['appointment_date']));
                    ?>
                </span>
            </div>
            <div class="confirmation-row">
                <span class="label">Hora de Cita:</span>
                <span class="value text-primary font-weight-bold" style="font-size: 1.1rem; font-weight: 700;">
                    <i class="fa-regular fa-clock"></i> <?= date('H:i', strtotime($appointment['appointment_date'])) ?>
                </span>
            </div>
            <div class="confirmation-row">
                <span class="label">Estado:</span>
                <span class="value"><span class="badge badge-warning">Pendiente de Revisión en Caja</span></span>
            </div>
            <?php if (!empty($appointment['attachment_url'])): ?>
            <div class="confirmation-row">
                <span class="label">Documento Adjunto:</span>
                <span class="value">
                    <span class="badge badge-success"><i class="fa-solid fa-file-circle-check"></i> Documento recibido correctamente</span>
                </span>
            </div>
            <?php endif; ?>
            <div class="mt-3 text-center no-print">
                <div class="alert alert-info d-inline-block text-left mb-3">
                    <i class="fa-solid fa-circle-info"></i> Recuerde acercarse a recepción 15 minutos antes de su cita.
                </div>
                <div class="company-info-block mb-3 p-3 text-left" style="background-color: var(--surface-2); border-radius: var(--radius-md); border: 1px solid var(--border); font-size: 0.9rem;">
                    <h5 class="mb-2 text-primary" style="font-size: 1rem;"><i class="fa-solid fa-building"></i> Información de la Clínica</h5>
                    <div class="d-flex flex-column gap-1 text-muted">
                        <div><strong class="text-dark">Dirección:</strong> <?= htmlspecialchars($companyAddress ?? '') ?></div>
                        <div><strong class="text-dark">Teléfono:</strong> <?= htmlspecialchars($companyPhone ?? '') ?></div>
                    </div>
                </div>
                <div class="d-flex justify-center gap-2">
                    <button onclick="window.print()" class="btn btn-primary"><i class="fa-solid fa-print"></i> Imprimir Comprobante</button>
                    <a href="<?= $baseUrl ?>/" class="btn btn-secondary">Nueva Cita</a>
                </div>
            </div>
        </div>
    </div>
</div>
