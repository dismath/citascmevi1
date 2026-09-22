<?php if (!$patientId): ?>
    <div class="alert alert-warning">
        <i class="fa-solid fa-triangle-exclamation"></i> No tienes un perfil de paciente asociado a esta cuenta. Por favor, contacta a recepción.
    </div>
<?php else: ?>
    <div class="grid-3 mb-3">
        <div class="card">
            <div class="card-header">
                <h3><i class="fa-solid fa-user-injured text-primary"></i> Información Básica</h3>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <span class="info-label">Tipo de Sangre</span>
                    <span class="info-value"><span class="badge badge-danger"><?= htmlspecialchars($history['blood_type'] ?? 'N/D') ?></span></span>
                </div>
                <div style="border-top: 1px solid var(--border-light); margin: 1rem 0;"></div>
                <div class="info-row">
                    <span class="info-label">Alergias</span>
                    <span class="info-value"><?= nl2br(htmlspecialchars($history['allergies'] ?? 'Ninguna registrada')) ?></span>
                </div>
                <div style="border-top: 1px solid var(--border-light); margin: 1rem 0;"></div>
                <div class="info-row">
                    <span class="info-label">Enfermedades Crónicas</span>
                    <span class="info-value"><?= nl2br(htmlspecialchars($history['chronic_diseases'] ?? 'Ninguna registrada')) ?></span>
                </div>
            </div>
        </div>
        <div class="card" style="grid-column: span 2;">
            <div class="card-header">
                <h3><i class="fa-solid fa-file-medical-alt text-primary"></i> Mis Atenciones Médicas</h3>
            </div>
            <div class="card-body">
                <?php if(empty($notes)): ?>
                    <div class="alert alert-info">
                        <i class="fa-solid fa-info-circle"></i> No tienes registros de atenciones previas.
                    </div>
                <?php else: ?>
                    <div class="accordion-custom">
                        <?php foreach($notes as $index => $note): ?>
                        <div class="accordion-item-custom <?= $index === 0 ? 'open' : '' ?>">
                            <button class="accordion-trigger" onclick="toggleAccordion(this)">
                                <div class="d-flex align-center gap-2">
                                    <i class="fa-solid fa-chevron-right accordion-arrow"></i>
                                    <strong><?= date('d/m/Y', strtotime($note['created_at'])) ?></strong>
                                    <span class="text-muted">— Dr. <?= htmlspecialchars($note['doctor_name']) ?></span>
                                </div>
                            </button>
                            <div class="accordion-panel">
                                <div class="accordion-panel-inner">
                                    <p><strong>Diagnóstico:</strong><br><?= nl2br(htmlspecialchars($note['diagnosis'] ?? '')) ?></p>
                                    <p class="mt-2"><strong>Tratamiento / Receta:</strong><br><?= nl2br(htmlspecialchars($note['treatment'] ?? '')) ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-file-invoice-dollar text-primary"></i> Mis Órdenes de Servicio</h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if(empty($orders)): ?>
                <div class="p-4 text-center text-muted">
                    <i class="fa-solid fa-file-invoice" style="font-size: 2rem; display: block; margin-bottom: 1rem; color: var(--border);"></i>
                    No tienes órdenes registradas.
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha de Cita</th>
                                <th>Nº Orden</th>
                                <th>Total</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($orders as $order): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($order['appointment_date'])) ?></td>
                                <td><strong>#<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></strong></td>
                                <td>$<?= number_format($order['total_amount'], 2) ?></td>
                                <td>
                                    <?php if($order['status'] == 'paid'): ?>
                                        <span class="badge badge-success">Pagada</span>
                                    <?php elseif($order['status'] == 'cancelled'): ?>
                                        <span class="badge badge-danger">Cancelada</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Pendiente</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
<script>
function toggleAccordion(btn) {
    const item = btn.closest('.accordion-item-custom');
    const wasOpen = item.classList.contains('open');
    // Close all
    document.querySelectorAll('.accordion-item-custom').forEach(function(el) {
        el.classList.remove('open');
    });
    // Toggle this one
    if (!wasOpen) {
        item.classList.add('open');
    }
}
</script>
