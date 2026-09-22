<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <form method="GET" action="<?= $baseUrl ?>/admin/appointments" class="d-flex align-items-center gap-2 flex-wrap">
            <div class="d-flex align-items-center gap-2">
                <label>Inicio:</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($currentStartDate ?? '') ?>">
            </div>
            <div class="d-flex align-items-center gap-2">
                <label>Fin:</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($currentEndDate ?? '') ?>">
            </div>
            <div class="d-flex align-items-center gap-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="" <?= empty($currentStatus) ? 'selected' : '' ?>>Todas</option>
                    <option value="pending" <?= ($currentStatus ?? '') === 'pending' ? 'selected' : '' ?>>Pendientes</option>
                    <option value="agendada" <?= in_array($currentStatus ?? '', ['scheduled', 'agendada']) ? 'selected' : '' ?>>Agendada</option>
                    <option value="confirmed" <?= ($currentStatus ?? '') === 'confirmed' ? 'selected' : '' ?>>Confirmadas</option>
                    <option value="completed" <?= ($currentStatus ?? '') === 'completed' ? 'selected' : '' ?>>Completadas</option>
                    <option value="cancelled" <?= ($currentStatus ?? '') === 'cancelled' ? 'selected' : '' ?>>Anuladas</option>
                    <option value="rescheduled" <?= ($currentStatus ?? '') === 'rescheduled' ? 'selected' : '' ?>>Reagendadas</option>
                </select>
            </div>
            <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
            <button type="submit" formaction="<?= $baseUrl ?>/admin/appointments/export/excel" formtarget="_blank" class="btn btn-sm btn-success"><i class="fa-solid fa-file-excel"></i> Excel</button>
            <button type="submit" formaction="<?= $baseUrl ?>/admin/appointments/export/pdf" formtarget="_blank" class="btn btn-sm btn-danger"><i class="fa-solid fa-file-pdf"></i> PDF</button>
        </form>
        <div>
            <?php if (\App\Helpers\Auth::hasPermission('appointments_create')): ?>
            <button type="button" class="btn btn-sm btn-success" id="btnOpenNewApptModal" onclick="if(window.openNewApptModal) window.openNewApptModal(event);">
                <i class="fa-solid fa-plus"></i> Nueva Cita
            </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-wrapper">
            <table class="datatable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha/Hora</th>
                        <th>Paciente</th>
                        <th>Médico/Especialidad</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $settingsModel = new \App\Models\SystemSetting();
                    $maxReschedules = (int)$settingsModel->get('max_reschedules', '1');
                    foreach($appointments as $app): 
                    ?>
                    <tr class="row-status-<?= ($app['reschedule_count'] ?? 0) > 0 ? 'rescheduled' : htmlspecialchars($app['status']) ?>">
                        <td><strong>#<?= $app['id'] ?></strong></td>
                        <td>
                            <div><?= date('d/m/Y', strtotime($app['appointment_date'])) ?></div>
                            <div class="text-muted text-sm"><?= date('H:i', strtotime($app['appointment_date'])) ?></div>
                        </td>
                        <td>
                            <div>
                                <a href="javascript:void(0)" class="view-patient-details text-decoration-none fw-bold" 
                                   data-bs-toggle="modal" data-bs-target="#patientDetailsModal"
                                   data-name="<?= htmlspecialchars($app['patient_name']) ?>"
                                   data-id="<?= htmlspecialchars($app['patient_id_number']) ?>"
                                   data-phone="<?= htmlspecialchars($app['patient_phone']) ?>"
                                   data-email="<?= htmlspecialchars($app['patient_email']) ?>"
                                   data-address="<?= htmlspecialchars($app['patient_address']) ?>">
                                    <?= htmlspecialchars($app['patient_name']) ?>
                                </a>
                            </div>
                            <div class="text-muted text-sm">CI: <?= htmlspecialchars($app['patient_id_number']) ?></div>
                        </td>
                        <td>
                            <div><?= htmlspecialchars($app['doctor_name']) ?></div>
                            <div class="text-muted text-sm"><?= htmlspecialchars($app['specialty_name']) ?></div>
                        </td>
                        <td>
                            <?php
                            $status = strtolower(trim($app['status']));
                            $statusColors = [
                                'pending' => 'warning', 'scheduled' => 'info', 'agendada' => 'info', 'confirmed' => 'primary',
                                'completed' => 'success', 'cancelled' => 'danger'
                            ];
                            ?>
                            <?php 
                            $canUpdateAppt = \App\Helpers\Auth::hasPermission('appointments_update');
                            $canDeleteAppt = \App\Helpers\Auth::hasPermission('appointments_delete');
                            $canCreateOrder = \App\Helpers\Auth::hasPermission('orders_create');
                            ?>
                            <?php if ((!isset($app['specialty_type_code']) || $app['specialty_type_code'] === 'ESPEC') && $canUpdateAppt): ?>
                                <form action="<?= $baseUrl ?>/admin/appointments/status/<?= \App\Helpers\HashId::encode($app['id']) ?>" method="POST" class="d-inline" style="margin-bottom: 0;">
                                    <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                                    <?php
                                        $statusOrderArr = ['pending' => 0, 'agendada' => 1, 'scheduled' => 1, 'confirmed' => 2, 'completed' => 3, 'cancelled' => 99];
                                        $currentOrder = $statusOrderArr[$status] ?? 0;
                                        $isDisabled = in_array($status, ['completed', 'cancelled']) ? 'disabled' : '';
                                        $badgeClass = 'badge badge-' . ($statusColors[$status] ?? 'secondary');
                                    ?>
                                    <select name="status" class="form-control form-control-sm <?= $badgeClass ?>" style="width: auto; display: inline-block; padding: 0.1rem 0.5rem; height: auto; border: none; font-weight: bold; cursor: <?= $isDisabled ? 'not-allowed' : 'pointer' ?>;" onchange="this.form.submit()" <?= $isDisabled ?>>
                                        <option value="pending" <?= $status == 'pending' ? 'selected' : '' ?> <?= $currentOrder > 0 ? 'disabled' : '' ?>>Pendiente</option>
                                        <option value="agendada" <?= in_array($status, ['scheduled', 'agendada']) ? 'selected' : '' ?> <?= $currentOrder > 1 ? 'disabled' : '' ?>>Agendada</option>
                                        <option value="confirmed" <?= $status == 'confirmed' ? 'selected' : '' ?> <?= $currentOrder > 2 ? 'disabled' : '' ?>>Confirmada</option>
                                        <option value="completed" <?= $status == 'completed' ? 'selected' : '' ?> <?= $currentOrder > 3 ? 'disabled' : '' ?>>Completada</option>
                                        <option value="cancelled" <?= $status == 'cancelled' ? 'selected' : '' ?> <?= $currentOrder > 99 ? 'disabled' : '' ?>>Anulada</option>
                                    </select>
                                </form>
                            <?php else: ?>
                                <?php
                                $statusLabels = [
                                    'pending' => 'Pendiente', 'scheduled' => 'Agendada', 'agendada' => 'Agendada', 'confirmed' => 'Confirmada',
                                    'completed' => 'Completada', 'cancelled' => 'Anulada'
                                ];
                                ?>
                                <span class="badge badge-<?= $statusColors[$status] ?? 'secondary' ?>"><?= $statusLabels[$status] ?? 'Desconocido' ?></span>
                            <?php endif; ?>
                            <?php if ($app['email_sent'] == 1): ?>
                                <span class="ms-1 text-success" title="Correo enviado"><i class="fa-solid fa-envelope-circle-check"></i></span>
                            <?php endif; ?>
                            <?php if (($app['reschedule_count'] ?? 0) > 0): ?>
                                <span class="badge badge-warning" style="margin-top: 4px; display: inline-block;" title="Reagendada <?= $app['reschedule_count'] ?> vez/veces">
                                    <i class="fa-solid fa-rotate"></i> <?= $app['reschedule_count'] ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <?php if ($canUpdateAppt && in_array($app['status'], ['pending', 'confirmed'])): ?>
                                    <?php if ($maxReschedules === 0 || ($app['reschedule_count'] ?? 0) < $maxReschedules): ?>
                                        <a href="<?= $baseUrl ?>/admin/appointments/reschedule/<?= \App\Helpers\HashId::encode($app['id']) ?>" class="btn btn-sm btn-warning" title="Reagendar"><i class="fa-regular fa-calendar-days"></i></a>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-secondary" disabled title="Límite de reagendamientos alcanzado"><i class="fa-regular fa-calendar-days"></i></button>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($canCreateOrder && isset($app['specialty_type_code']) && $app['specialty_type_code'] !== 'ESPEC'): ?>
                                <?php 
                                    $onclick = ($app['orders_count'] > 0) 
                                        ? "onclick=\"return confirm('Este registro ya tiene una orden generada. ¿Desea crear otra o cancelar?');\"" 
                                        : "";
                                ?>
                                <a href="<?= $baseUrl ?>/admin/orders/create/<?= \App\Helpers\HashId::encode($app['id']) ?>" class="btn btn-sm btn-primary" title="Generar Orden/Prefactura" <?= $onclick ?>>
                                    <i class="fa-solid fa-file-invoice-dollar"></i>
                                </a>
                                <?php endif; ?>
                                <?php if($app['attachment_url']): ?>
                                    <a href="<?= $baseUrl ?>/admin/appointments/attachment/<?= \App\Helpers\HashId::encode($app['id']) ?>" target="_blank" class="btn btn-sm btn-info" title="Ver Comprobante">
                                        <i class="fas fa-file-invoice"></i>
                                    </a>
                                <?php elseif ($canUpdateAppt): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger position-relative btn-upload-receipt" title="Cargar comprobante" data-bs-toggle="modal" data-bs-target="#uploadReceiptModal" data-id="<?= \App\Helpers\HashId::encode($app['id']) ?>">
                                        <i class="fas fa-file-upload"></i>
                                        <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle" style="width: 10px; height: 10px;"></span>
                                    </button>
                                <?php endif; ?>
                                <a href="<?= $baseUrl ?>/admin/appointments/print-ticket/<?= \App\Helpers\HashId::encode($app['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Imprimir Ticket Térmico (7x15)">
                                    <i class="fa-solid fa-receipt"></i>
                                </a>
                                <?php if ($canDeleteAppt): ?>
                                <form action="<?= $baseUrl ?>/admin/appointments/delete/<?= \App\Helpers\HashId::encode($app['id']) ?>" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar esta cita? Esta acción liberará el horario del médico.')">
                                    <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (isset($pagination) && $pagination['total_pages'] > 1): ?>
        <div class="mt-4 d-flex justify-content-between align-items-center">
            <span class="text-muted">Mostrando página <?= $pagination['current_page'] ?> de <?= $pagination['total_pages'] ?> (Total: <?= $pagination['total_records'] ?> registros)</span>
            <div class="pagination gap-2">
                <?php if ($pagination['current_page'] > 1): ?>
                    <a href="?page=<?= $pagination['current_page'] - 1 ?>&status=<?= $currentStatus ?>" class="btn btn-sm btn-outline-primary">Anterior</a>
                <?php endif; ?>
                <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                    <a href="?page=<?= $pagination['current_page'] + 1 ?>&status=<?= $currentStatus ?>" class="btn btn-sm btn-outline-primary">Siguiente</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<style>
/* Modal auto width (fits content) */
#patientDetailsModal .modal-dialog {
    max-width: none; /* no max, let content dictate */
    width: auto;
    margin: 1.75rem auto;
}
@media (max-width: 576px) {
    #patientDetailsModal .modal-dialog {
        width: 95%;
    }
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var patientLinks = document.querySelectorAll('.view-patient-details');
    patientLinks.forEach(function(link) {
        link.addEventListener('click', function() {
            document.getElementById('modal-patient-name').textContent = this.getAttribute('data-name');
            document.getElementById('modal-patient-id').textContent = this.getAttribute('data-id');
            document.getElementById('modal-patient-phone').textContent = this.getAttribute('data-phone');
            document.getElementById('modal-patient-email').textContent = this.getAttribute('data-email');
            document.getElementById('modal-patient-address').textContent = this.getAttribute('data-address');
        });
    });
});
</script>
<div id="modalNewAppointment" class="quick-modal" style="display: none;">
    <div class="quick-modal-backdrop"></div>
    <div class="quick-modal-dialog">
        <div class="quick-modal-content">
            <div class="quick-modal-header">
                <div>
                    <h5 class="m-0 text-white fw-bold d-flex align-items-center gap-2">
                        <i class="fa-solid fa-calendar-plus"></i> Nueva Cita Médica (Ventanilla / Recepción)
                    </h5>
                    <small class="text-white-50">Registre una cita presencial y genere el ticket de agendamiento para el paciente</small>
                </div>
                <button type="button" class="quick-modal-close" id="btnCloseNewApptModal">&times;</button>
            </div>
            <div class="quick-modal-body p-4">
                <div id="newApptAlert" class="alert alert-danger" style="display: none;"></div>
                <div id="newApptFormPanel">
                    <form id="formNewAppt" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                        <input type="hidden" name="is_ajax" value="1">
                        <input type="hidden" name="availability_id" id="newApptSlotId" value="">
                        <input type="hidden" name="patient_id" id="newApptPatientId" value="">
                        <input type="hidden" name="status" value="agendada">
                        <div class="form-section mb-4">
                            <div class="section-title">
                                <i class="fa-solid fa-stethoscope text-primary"></i> 1. Especialidad, Médico y Horario
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Especialidad: <span class="text-danger">*</span></label>
                                    <select name="specialty_id" id="selSpecialty" class="form-select form-select-sm" required>
                                        <option value="">-- Seleccionar --</option>
                                        <?php if (!empty($specialties)): ?>
                                            <?php foreach ($specialties as $sp): ?>
                                                <option value="<?= $sp['id'] ?>"><?= htmlspecialchars($sp['name']) ?></option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Médico: <span class="text-danger">*</span></label>
                                    <select name="doctor_id" id="selDoctor" class="form-select form-select-sm" required disabled>
                                        <option value="">-- Primero elija especialidad --</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Fecha de Atención: <span class="text-danger">*</span></label>
                                    <input type="date" name="appointment_date" id="inputApptDate" class="form-control form-control-sm" 
                                           min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>
                            <div class="mt-3">
                                <label class="form-label fw-bold d-flex justify-content-between align-items-center">
                                    <span><i class="fa-regular fa-clock text-primary"></i> Turnos Disponibles: <span class="text-danger">*</span></span>
                                    <small id="slotStatusHint" class="text-muted">Seleccione un médico y fecha</small>
                                </label>
                                <div id="slotsContainer" class="slots-box">
                                    <div class="text-muted text-center py-3" style="font-size: 0.9rem;">
                                        <i class="fa-solid fa-arrow-up"></i> Seleccione especialidad, médico y fecha para ver los horarios disponibles.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="form-section mb-4">
                            <div class="section-title d-flex justify-content-between align-items-center">
                                <span><i class="fa-solid fa-user-injured text-primary"></i> 2. Paciente Solicitante</span>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0" id="btnClearPatient">
                                    <i class="fa-solid fa-eraser"></i> Limpiar / Nuevo Paciente
                                </button>
                            </div>
                            <div class="patient-search-wrapper mb-3 position-relative">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                                    <input type="text" id="inputSearchPatient" class="form-control" 
                                           placeholder="Buscar paciente existente por Cédula, Nombre o Correo...">
                                    <button type="button" class="btn btn-primary" id="btnTriggerSearchPatient">Buscar</button>
                                </div>
                                <div id="patientSearchResults" class="patient-search-dropdown shadow" style="display: none;"></div>
                                <div id="patientSearchFeedback" class="mt-2" style="display: none;"></div>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Cédula / Documento: <span class="text-danger">*</span></label>
                                    <input type="text" name="patient_id_number" id="inputPatientIdNumber" class="form-control form-control-sm" 
                                           placeholder="Ej: 1720281144" required>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label fw-bold">Nombre Completo: <span class="text-danger">*</span></label>
                                    <input type="text" name="patient_name" id="inputPatientName" class="form-control form-control-sm" 
                                           placeholder="Nombres y Apellidos" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Teléfono / Celular:</label>
                                    <input type="text" name="patient_phone" id="inputPatientPhone" class="form-control form-control-sm" 
                                           placeholder="Ej: 0991234567">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Correo Electrónico (Notificación):</label>
                                    <input type="email" name="patient_email" id="inputPatientEmail" class="form-control form-control-sm" 
                                           placeholder="correo@ejemplo.com">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Dirección Domiciliaria:</label>
                                    <input type="text" name="patient_address" id="inputPatientAddress" class="form-control form-control-sm" 
                                           placeholder="Ciudad / Sector / Calle">
                                </div>
                            </div>
                        </div>
                        <div class="form-section mb-3">
                            <div class="section-title">
                                <i class="fa-solid fa-receipt text-primary"></i> 3. Comprobante de Pago y Observaciones
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Comprobante de Pago:</label>
                                    <input type="file" name="payment_receipt" id="inputReceipt" class="form-control form-control-sm" accept="image/*,.pdf">
                                    <small class="text-muted d-block mt-1">Imágenes JPG, PNG o PDF (Máx. 5MB)</small>
                                    <div id="receiptPreview" class="receipt-preview-box mt-2" style="display: none;"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Estado del Registro:</label>
                                    <div class="alert alert-info py-2 px-3 m-0 d-flex align-items-center gap-2" style="font-size: 0.88rem;">
                                        <i class="fa-solid fa-check-circle text-success fs-5"></i>
                                        <span>La cita se guardará con estado <strong>"Agendada"</strong> de forma automática.</span>
                                    </div>
                                    <div class="mt-2">
                                        <label class="form-label">Notas / Motivo de Consulta:</label>
                                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="Opcional: Motivo de consulta u observaciones de recepción...">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                            <button type="button" class="btn btn-secondary btn-sm" id="btnCancelNewAppt">Cancelar</button>
                            <button type="submit" class="btn btn-success btn-sm px-4 fw-bold" id="btnSubmitNewAppt">
                                <i class="fa-solid fa-check"></i> Agendar Cita
                            </button>
                        </div>
                    </form>
                </div>
                <div id="newApptSuccessPanel" style="display: none;" class="text-center py-3">
                    <div class="success-badge mb-2">
                        <i class="fa-solid fa-circle-check text-success" style="font-size: 4rem;"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-1">¡Cita Agendada Exitosamente!</h4>
                    <p class="text-muted mb-3" style="font-size: 0.95rem;">
                        La cita médica ha sido registrada con estado <span class="badge badge-info">Agendada</span> y el horario ha quedado reservado.
                    </p>
                    <div class="card bg-light border p-3 mb-4 mx-auto text-start" style="max-width: 520px; font-size: 0.92rem;">
                        <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                            <span class="text-muted">N° de Cita:</span>
                            <span class="fw-bold text-primary" id="succApptId">#00000</span>
                        </div>
                        <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                            <span class="text-muted">Paciente:</span>
                            <span class="fw-bold" id="succPatientName">---</span>
                        </div>
                        <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                            <span class="text-muted">Fecha y Hora:</span>
                            <span class="fw-bold text-success" id="succApptDateTime">---</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Estado:</span>
                            <span class="badge badge-info">Agendada</span>
                        </div>
                    </div>
                    <div class="d-flex justify-content-center gap-3 flex-wrap">
                        <a href="#" id="btnSuccessPrintTicket" target="_blank" class="btn btn-success btn-lg px-4 fw-bold shadow-sm d-inline-flex align-items-center gap-2">
                            <i class="fa-solid fa-receipt fs-4"></i> 🖨️ Imprimir Ticket (7x15)
                        </a>
                        <button type="button" class="btn btn-secondary btn-lg px-4" id="btnSuccessCloseReload">
                            <i class="fa-solid fa-check"></i> Finalizar y Actualizar Lista
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
/* Estilos del Modal de Agendamiento Rápido */
.quick-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1050;
    overflow-y: auto;
}
.quick-modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(3px);
}
.quick-modal-dialog {
    position: relative;
    width: 95%;
    max-width: 860px;
    margin: 30px auto;
    z-index: 1051;
}
.quick-modal-content {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 20px 45px rgba(0,0,0,0.25);
    overflow: hidden;
    border: 1px solid rgba(226, 232, 240, 0.8);
}
.quick-modal-header {
    background: linear-gradient(135deg, #1e3a8a, #2563eb);
    padding: 16px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.quick-modal-close {
    background: transparent;
    border: none;
    font-size: 28px;
    color: #ffffff;
    opacity: 0.8;
    cursor: pointer;
    line-height: 1;
    transition: opacity 0.2s;
}
.quick-modal-close:hover {
    opacity: 1;
}
.form-section {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 14px 18px;
}
.section-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #1e293b;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 6px;
    margin-bottom: 12px;
}
.slots-box {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 10px;
    min-height: 60px;
    max-height: 170px;
    overflow-y: auto;
}
.slot-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 5px 12px;
    margin: 4px;
    font-size: 0.85rem;
    font-weight: 600;
    border: 1.5px solid #2563eb;
    border-radius: 20px;
    background: #eff6ff;
    color: #1e40af;
    cursor: pointer;
    transition: all 0.15s ease;
}
.slot-pill:hover {
    background: #dbeafe;
    transform: translateY(-1px);
}
.slot-pill.active {
    background: #16a34a !important;
    border-color: #15803d !important;
    color: #ffffff !important;
    box-shadow: 0 2px 6px rgba(22, 163, 74, 0.35);
}
.patient-search-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    max-height: 200px;
    overflow-y: auto;
    z-index: 1060;
}
.patient-search-item {
    padding: 8px 12px;
    cursor: pointer;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.88rem;
}
.patient-search-item:hover {
    background: #f0fdf4;
}
.receipt-preview-box img {
    max-height: 90px;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal          = document.getElementById('modalNewAppointment');
    const btnOpen        = document.getElementById('btnOpenNewApptModal');
    const btnClose       = document.getElementById('btnCloseNewApptModal');
    const btnCancel      = document.getElementById('btnCancelNewAppt');
    const backdrop       = modal ? modal.querySelector('.quick-modal-backdrop') : null;
    const formPanel      = document.getElementById('newApptFormPanel');
    const successPanel   = document.getElementById('newApptSuccessPanel');
    const form           = document.getElementById('formNewAppt');
    const alertBox       = document.getElementById('newApptAlert');
    const selSpecialty   = document.getElementById('selSpecialty');
    const selDoctor      = document.getElementById('selDoctor');
    const inputDate      = document.getElementById('inputApptDate');
    const slotsContainer = document.getElementById('slotsContainer');
    const slotStatusHint = document.getElementById('slotStatusHint');
    const inputSlotId    = document.getElementById('newApptSlotId');
    const searchInput    = document.getElementById('inputSearchPatient');
    const btnSearch      = document.getElementById('btnTriggerSearchPatient');
    const searchDropdown = document.getElementById('patientSearchResults');
    const btnClearPat    = document.getElementById('btnClearPatient');
    const patIdInput     = document.getElementById('newApptPatientId');
    const patIdNumInput  = document.getElementById('inputPatientIdNumber');
    const patNameInput   = document.getElementById('inputPatientName');
    const patPhoneInput  = document.getElementById('inputPatientPhone');
    const patEmailInput  = document.getElementById('inputPatientEmail');
    const patAddressInput= document.getElementById('inputPatientAddress');
    const receiptInput   = document.getElementById('inputReceipt');
    const receiptPreview = document.getElementById('receiptPreview');
    const btnSubmit      = document.getElementById('btnSubmitNewAppt');
    const btnPrintTicket = document.getElementById('btnSuccessPrintTicket');
    const btnCloseReload = document.getElementById('btnSuccessCloseReload');
    let appointmentCreated = false;
    // 1. ABRIR Y CERRAR MODAL
    window.openNewApptModal = function(e) {
        if(e) { e.preventDefault(); e.stopPropagation(); }
        try {
            appointmentCreated = false;
            if(alertBox) alertBox.style.display = 'none';
            if(formPanel) formPanel.style.display = 'block';
            if(successPanel) successPanel.style.display = 'none';
            if(form) form.reset();
            if(inputSlotId) inputSlotId.value = '';
            if(patIdInput) patIdInput.value = '';
            const fbInit = document.getElementById('patientSearchFeedback');
            if (fbInit) { fbInit.style.display = 'none'; fbInit.innerHTML = ''; }
            if (receiptPreview) { receiptPreview.style.display = 'none'; receiptPreview.innerHTML = ''; }
            if (slotsContainer) slotsContainer.innerHTML = '<div class="text-muted text-center py-3" style="font-size: 0.9rem;"><i class="fa-solid fa-arrow-up"></i> Seleccione especialidad, médico y fecha para ver los horarios disponibles.</div>';
            if (slotStatusHint) slotStatusHint.textContent = 'Seleccione un médico y fecha';
            if (selDoctor) {
                selDoctor.innerHTML = '<option value="">-- Primero elija especialidad --</option>';
                selDoctor.disabled = true;
            }
            if (inputDate) inputDate.value = new Date().toISOString().split('T')[0];
            if (modal) modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        } catch (err) {
            console.error('Error in openModal:', err);
            alert('Hubo un error al abrir la ventana: ' + err.message);
        }
    }

    function closeModal() {
        if (modal) modal.style.display = 'none';
        document.body.style.overflow = '';
        if (appointmentCreated) {
            window.location.reload();
        }
    }
    if (btnOpen) btnOpen.addEventListener('click', window.openNewApptModal);
    if (btnClose) btnClose.addEventListener('click', closeModal);
    if (btnCancel) btnCancel.addEventListener('click', closeModal);
    if (backdrop) backdrop.addEventListener('click', closeModal);
    if (btnCloseReload) btnCloseReload.addEventListener('click', function() {
        window.location.reload();
    });
    // 2. CARGAR MÉDICOS POR ESPECIALIDAD
    if (selSpecialty) {
        selSpecialty.addEventListener('change', function() {
        const specialtyId = this.value;
        selDoctor.innerHTML = '<option value="">Cargando médicos...</option>';
        selDoctor.disabled = true;
        inputSlotId.value = '';
        slotsContainer.innerHTML = '<div class="text-muted text-center py-3">Seleccione un médico.</div>';
        if (!specialtyId) {
            selDoctor.innerHTML = '<option value="">-- Primero elija especialidad --</option>';
            return;
        }
        fetch(BASE_URL + '/api/doctors/' + specialtyId)
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data && res.data.length > 0) {
                    let opts = '<option value="">-- Seleccionar Médico --</option>';
                    res.data.forEach(d => {
                        opts += `<option value="${d.id}">Dr(a). ${d.name}</option>`;
                    });
                    selDoctor.innerHTML = opts;
                    selDoctor.disabled = false;
                } else {
                    selDoctor.innerHTML = '<option value="">No hay médicos disponibles</option>';
                }
            })
            .catch(() => {
                selDoctor.innerHTML = '<option value="">Error al cargar médicos</option>';
            });
        });
    }
    // 3. CARGAR TURNOS / HORARIOS DISPONIBLES
    function loadSlots() {
        const doctorId = selDoctor.value;
        const date     = inputDate.value;
        inputSlotId.value = '';
        if (!doctorId || !date) {
            return;
        }
        slotsContainer.innerHTML = '<div class="text-center py-3 text-primary"><i class="fa-solid fa-spinner fa-spin"></i> Buscando turnos disponibles...</div>';
        slotStatusHint.textContent = 'Consultando...';
        fetch(BASE_URL + '/api/availability/' + doctorId + '/' + date)
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data && res.data.length > 0) {
                    let html = '<div class="d-flex flex-wrap gap-1">';
                    res.data.forEach(slot => {
                        const start = slot.start_time.substring(0, 5);
                        const end   = slot.end_time.substring(0, 5);
                        html += `<button type="button" class="slot-pill" data-slot-id="${slot.id}">
                            <i class="fa-regular fa-clock"></i> ${start} - ${end}
                        </button>`;
                    });
                    html += '</div>';
                    slotsContainer.innerHTML = html;
                    slotStatusHint.textContent = `${res.data.length} turno(s) disponible(s)`;
                    // Asignar evento clic a cada turno
                    slotsContainer.querySelectorAll('.slot-pill').forEach(pill => {
                        pill.addEventListener('click', function() {
                            slotsContainer.querySelectorAll('.slot-pill').forEach(p => p.classList.remove('active'));
                            this.classList.add('active');
                            inputSlotId.value = this.getAttribute('data-slot-id');
                        });
                    });
                } else {
                    slotsContainer.innerHTML = '<div class="alert alert-warning py-2 px-3 m-0 text-center" style="font-size: 0.88rem;"><i class="fa-solid fa-triangle-exclamation"></i> No hay horarios disponibles para esta fecha. Intente con otra fecha.</div>';
                    slotStatusHint.textContent = 'Sin turnos disponibles';
                }
            })
            .catch(() => {
                slotsContainer.innerHTML = '<div class="text-danger text-center py-2">Error al consultar turnos.</div>';
            });
    }
    if (selDoctor) selDoctor.addEventListener('change', loadSlots);
    if (inputDate) inputDate.addEventListener('change', loadSlots);
    // 4. BÚSQUEDA Y AUTOCOMPLETADO DE PACIENTE EXISTENTE
    const patientFeedback = document.getElementById('patientSearchFeedback');
    function populatePatientFields(p) {
        if (!p) return;
        patIdInput.value      = p.id || '';
        patIdNumInput.value   = p.id_number || '';
        patNameInput.value    = p.name || '';
        patPhoneInput.value   = p.phone || '';
        patEmailInput.value   = p.email || '';
        patAddressInput.value = p.address || '';
        searchDropdown.style.display = 'none';
        if (patientFeedback) {
            patientFeedback.innerHTML = `
                <div class="alert alert-success py-2 px-3 m-0 d-flex align-items-center justify-content-between shadow-sm" style="font-size: 0.88rem; border-left: 4px solid #16a34a; background-color: #f0fdf4;">
                    <div>
                        <i class="fa-solid fa-circle-check text-success me-1"></i>
                        Paciente existente cargado: <strong>${p.name}</strong>
                        <span class="text-muted ms-1">(CI: ${p.id_number || 'N/A'})</span>
                    </div>
                    <span class="badge bg-success text-white">ID #${p.id}</span>
                </div>
            `;
            patientFeedback.style.display = 'block';
        }
    }
    function performPatientSearch(autoFillIfMatch = false) {
        const q = searchInput.value.trim();
        if (q.length < 1) {
            searchDropdown.style.display = 'none';
            return;
        }
        const originalBtnHtml = btnSearch.innerHTML;
        if (autoFillIfMatch) {
            btnSearch.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
            btnSearch.disabled = true;
        }
        fetch(BASE_URL + '/api/patients/search?q=' + encodeURIComponent(q))
            .then(res => res.json())
            .then(res => {
                if (autoFillIfMatch) {
                    btnSearch.innerHTML = originalBtnHtml;
                    btnSearch.disabled = false;
                }
                if (res.success && res.data && res.data.length > 0) {
                    // Si el usuario presionó Buscar o Enter y hay 1 resultado, o coincidencia exacta de CI / correo
                    const exactMatch = res.data.find(p => 
                        (p.id_number && p.id_number.trim() === q) || 
                        (p.email && p.email.trim().toLowerCase() === q.toLowerCase())
                    );
                    if (autoFillIfMatch && (res.data.length === 1 || exactMatch)) {
                        populatePatientFields(exactMatch || res.data[0]);
                        return;
                    }
                    // Mostrar opciones encontradas
                    let html = '';
                    res.data.forEach(p => {
                        html += `<div class="patient-search-item" 
                            data-id="${p.id}" 
                            data-idnum="${p.id_number || ''}" 
                            data-name="${p.name || ''}" 
                            data-phone="${p.phone || ''}" 
                            data-email="${p.email || ''}" 
                            data-address="${p.address || ''}">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-primary">${p.name}</span>
                                <span class="badge bg-light text-dark border">CI: ${p.id_number || 'N/A'}</span>
                            </div>
                            <small class="text-muted">Tel: ${p.phone || 'N/A'} | Correo: ${p.email || 'N/A'}</small>
                        </div>`;
                    });
                    searchDropdown.innerHTML = html;
                    searchDropdown.style.display = 'block';
                    searchDropdown.querySelectorAll('.patient-search-item').forEach(item => {
                        item.addEventListener('click', function() {
                            populatePatientFields({
                                id: this.getAttribute('data-id'),
                                id_number: this.getAttribute('data-idnum'),
                                name: this.getAttribute('data-name'),
                                phone: this.getAttribute('data-phone'),
                                email: this.getAttribute('data-email'),
                                address: this.getAttribute('data-address')
                            });
                        });
                    });
                } else {
                    if (autoFillIfMatch) {
                        searchDropdown.innerHTML = '<div class="p-2 text-muted text-center" style="font-size: 0.85rem;"><i class="fa-solid fa-circle-info me-1"></i> No se encontró paciente con esa información. Puede registrar los datos abajo.</div>';
                        searchDropdown.style.display = 'block';
                    } else {
                        searchDropdown.style.display = 'none';
                    }
                }
            })
            .catch(() => {
                if (autoFillIfMatch) {
                    btnSearch.innerHTML = originalBtnHtml;
                    btnSearch.disabled = false;
                }
                searchDropdown.style.display = 'none';
            });
    }
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            if (this.value.trim().length >= 2) {
                performPatientSearch(false);
            } else {
                searchDropdown.style.display = 'none';
            }
        });
        // Enter en input de búsqueda evita submit prematuro del modal y ejecuta auto-llenado
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                performPatientSearch(true);
            }
        });
    }
    if (btnSearch) {
        btnSearch.addEventListener('click', function() {
            performPatientSearch(true);
        });
    }
    // Cerrar dropdown al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchDropdown.contains(e.target)) {
            searchDropdown.style.display = 'none';
        }
    });
    // Búsqueda directa al escribir la cédula en el campo
    let idNumDebounce = null;
    function checkPatientByIdNumber(idNum) {
        if (!idNum || idNum.length < 8) return;
        fetch(BASE_URL + '/api/patient/' + encodeURIComponent(idNum))
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data) {
                    populatePatientFields(res.data);
                }
            })
            .catch(() => {});
    }
    if (patIdNumInput) {
        patIdNumInput.addEventListener('input', function() {
            clearTimeout(idNumDebounce);
            const idNum = this.value.trim();
            if (idNum.length >= 8 && !patIdInput.value) {
                idNumDebounce = setTimeout(() => {
                    checkPatientByIdNumber(idNum);
                }, 500);
            }
        });
        patIdNumInput.addEventListener('blur', function() {
            const idNum = this.value.trim();
            if (idNum.length >= 8 && !patIdInput.value) {
                checkPatientByIdNumber(idNum);
            }
        });
    }
    // Botón Limpiar paciente
    if (btnClearPat) {
        btnClearPat.addEventListener('click', function() {
            if (patIdInput) patIdInput.value      = '';
            if (patIdNumInput) patIdNumInput.value   = '';
            if (patNameInput) patNameInput.value    = '';
            if (patPhoneInput) patPhoneInput.value   = '';
            if (patEmailInput) patEmailInput.value   = '';
            if (patAddressInput) patAddressInput.value = '';
            if (searchInput) searchInput.value     = '';
            if (searchDropdown) searchDropdown.style.display = 'none';
            if (patientFeedback) {
                patientFeedback.style.display = 'none';
                patientFeedback.innerHTML = '';
            }
        });
    }
    // 5. PREVISUALIZACIÓN DE COMPROBANTE
    if (receiptInput) {
        receiptInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (receiptPreview) {
                        receiptPreview.innerHTML = `<img src="${e.target.result}" alt="Comprobante" class="shadow-sm">`;
                        receiptPreview.style.display = 'block';
                    }
                };
                reader.readAsDataURL(file);
            } else if (file) {
                if (receiptPreview) {
                    receiptPreview.innerHTML = `<span class="badge badge-info"><i class="fa-solid fa-file-pdf"></i> Documento seleccionado: ${file.name}</span>`;
                    receiptPreview.style.display = 'block';
                }
            } else {
                if (receiptPreview) {
                    receiptPreview.style.display = 'none';
                    receiptPreview.innerHTML = '';
                }
            }
        });
    }
    // 6. ENVIAR FORMULARIO POR AJAX
    if (form) {
        form.addEventListener('submit', function(e) {
        e.preventDefault();
        alertBox.style.display = 'none';
        if (!inputSlotId.value) {
            alertBox.textContent = 'Por favor seleccione uno de los turnos disponibles de la lista.';
            alertBox.style.display = 'block';
            slotsContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Agendando...';
        const formData = new FormData(form);
        fetch(BASE_URL + '/admin/appointments/store', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<i class="fa-solid fa-check"></i> Agendar Cita';
            if (data.success) {
                appointmentCreated = true;
                // Llenar datos de éxito
                document.getElementById('succApptId').textContent = '#' + String(data.appointment_id).padStart(5, '0');
                document.getElementById('succPatientName').textContent = data.patient_name || patNameInput.value;
                document.getElementById('succApptDateTime').textContent = data.date;
                btnPrintTicket.setAttribute('href', data.print_url);
                formPanel.style.display = 'none';
                successPanel.style.display = 'block';
            } else {
                alertBox.textContent = data.message || 'Error al procesar la cita médica.';
                alertBox.style.display = 'block';
                alertBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        })
        .catch(err => {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<i class="fa-solid fa-check"></i> Agendar Cita';
            alertBox.textContent = 'Ocurrió un error inesperado al conectar con el servidor.';
            alertBox.style.display = 'block';
        });
        });
    }
});
</script>
<div id="uploadReceiptModal" class="quick-modal" style="display: none;">
    <div class="quick-modal-backdrop"></div>
    <div class="quick-modal-dialog" style="max-width: 500px; margin-top: 10vh;">
        <div class="quick-modal-content">
            <div class="quick-modal-header">
                <div>
                    <h5 class="m-0 text-white fw-bold d-flex align-items-center gap-2">
                        <i class="fa-solid fa-file-upload"></i> Cargar Comprobante de Pago
                    </h5>
                </div>
                <button type="button" class="quick-modal-close" id="btnCloseUploadReceipt">&times;</button>
            </div>
            <div class="quick-modal-body p-4">
                <form id="formUploadReceipt" method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                    <div class="alert alert-warning py-2 px-3" style="font-size: 0.9rem;">
                        <i class="fas fa-exclamation-triangle"></i> <strong>Comprobante de pago no registrado.</strong><br>
                        Por favor, seleccione el comprobante para esta cita.
                    </div>
                    <div class="mb-4 mt-3">
                        <label class="form-label fw-bold">Archivo (JPG, PNG, PDF):</label>
                        <input type="file" name="payment_receipt" class="form-control form-control-sm" accept="image/*,.pdf" required>
                    </div>
                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <button type="button" class="btn btn-secondary btn-sm" id="btnCancelUploadReceipt">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm fw-bold"><i class="fas fa-upload"></i> Cargar Archivo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var uploadReceiptButtons = document.querySelectorAll('.btn-upload-receipt');
    var uploadReceiptForm = document.getElementById('formUploadReceipt');
    var uploadModal = document.getElementById('uploadReceiptModal');
    var btnCloseUpload = document.getElementById('btnCloseUploadReceipt');
    var btnCancelUpload = document.getElementById('btnCancelUploadReceipt');
    var backdropUpload = uploadModal.querySelector('.quick-modal-backdrop');
    var currentBtn = null;
    function closeUploadModal() {
        uploadModal.style.display = 'none';
        document.body.style.overflow = '';
        uploadReceiptForm.reset();
    }
    uploadReceiptButtons.forEach(function(btn) {
        // Remove Bootstrap data attributes just in case
        btn.removeAttribute('data-bs-toggle');
        btn.removeAttribute('data-bs-target');
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var id = this.getAttribute('data-id');
            uploadReceiptForm.action = BASE_URL + '/admin/appointments/upload-receipt/' + id;
            currentBtn = this;
            uploadModal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        });
    });
    if (btnCloseUpload) btnCloseUpload.addEventListener('click', closeUploadModal);
    if (btnCancelUpload) btnCancelUpload.addEventListener('click', closeUploadModal);
    if (backdropUpload) backdropUpload.addEventListener('click', closeUploadModal);
    if(uploadReceiptForm) {
        uploadReceiptForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var submitBtn = uploadReceiptForm.querySelector('button[type="submit"]');
            var originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cargando...';
            var formData = new FormData(this);
            formData.append('is_ajax', '1');
            fetch(this.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                if(data.success) {
                    if (currentBtn) {
                        var newBtn = document.createElement('a');
                        newBtn.href = data.url;
                        newBtn.target = '_blank';
                        newBtn.className = 'btn btn-sm btn-info';
                        newBtn.title = 'Ver Comprobante';
                        newBtn.innerHTML = '<i class="fas fa-file-invoice"></i>';
                        currentBtn.parentNode.replaceChild(newBtn, currentBtn);
                    }
                    closeUploadModal();
                } else {
                    alert(data.message || 'Error al cargar el comprobante');
                }
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                alert('Error de conexión al cargar el comprobante');
            });
        });
    }
});
</script>
