<?php
$activeTab = $activeTab ?? 'historial';
$treatments = $treatments ?? [];
$prescriptions = $prescriptions ?? [];
?>
<div class="d-flex justify-between align-center mb-3">
    <div id="appointment-action-container">
        <?php if ($activeAppointment && ($activeAppointment['has_notes'] ?? 0) > 0): ?>
            <span class="badge badge-secondary" style="padding: 0.5rem 1rem; font-size: 0.9rem;">
                <i class="fa-solid fa-check"></i> Cita Atendida
            </span>
        <?php elseif ($activeAppointment && in_array($activeAppointment['status'], ['confirmed', 'agendada'])): ?>
            <form id="startAppointmentForm" action="<?= $baseUrl ?>/doctor/appointments/<?= $activeAppointment['id'] ?>/start" method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <button type="submit" class="btn btn-success" id="btnStartAppointment">
                    <i class="fa-solid fa-play"></i> Iniciar Atención
                </button>
            </form>
        <?php elseif ($activeAppointment && $activeAppointment['status'] === 'in_progress'): ?>
            <span class="badge badge-warning" id="inProgressBadge" style="padding: 0.5rem 1rem; font-size: 0.9rem;">
                <i class="fa-solid fa-spinner fa-spin"></i> Atención en Progreso
            </span>
        <?php endif; ?>
    </div>
    <button onclick="history.back()" class="btn btn-secondary">
        <i class="fa-solid fa-arrow-left"></i> Volver
    </button>
</div>
<div class="grid-3 mb-3">
    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-user text-primary"></i> Información del Paciente</h3>
        </div>
        <div class="card-body">
            <div class="info-row">
                <span class="info-label">Cédula</span>
                <span class="info-value"><?= htmlspecialchars($patient['id_number']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Email</span>
                <span class="info-value"><?= htmlspecialchars($patient['email'] ?? 'No registrado') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Teléfono</span>
                <span class="info-value"><?= htmlspecialchars($patient['phone']) ?></span>
            </div>
            <div style="border-top: 1px solid var(--border-light); margin: 1rem 0;"></div>
            <div class="info-row">
                <span class="info-label">Tipo de Sangre</span>
                <span class="info-value"><span class="badge badge-danger"><?= htmlspecialchars($history['blood_type'] ?? 'N/D') ?></span></span>
            </div>
            <div class="info-row">
                <span class="info-label">Alergias</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($history['allergies'] ?? 'Ninguna')) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Enfermedades Crónicas</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($history['chronic_diseases'] ?? 'Ninguna')) ?></span>
            </div>
        </div>
    </div>
    <div class="card" style="grid-column: span 2;">
        <div class="card-header" style="border-bottom: none; padding-bottom: 0;">
            <div class="custom-tabs">
                <button class="tab-btn <?= $activeTab === 'historial' ? 'active' : '' ?>" id="btn-tab-historial" onclick="switchTab('historial')">
                    <i class="fa-solid fa-clock-rotate-left"></i> Historial Clínico
                </button>
                <button class="tab-btn <?= $activeTab === 'atencion' ? 'active' : '' ?>" id="btn-tab-atencion" onclick="switchTab('atencion')">
                    <i class="fa-solid fa-stethoscope"></i> Atención Actual
                </button>
                <button class="tab-btn <?= $activeTab === 'tratamiento' ? 'active' : '' ?>" id="btn-tab-tratamiento" onclick="switchTab('tratamiento')">
                    <i class="fa-solid fa-notes-medical"></i> Tratamiento
                </button>
                <button class="tab-btn <?= $activeTab === 'receta' ? 'active' : '' ?>" id="btn-tab-receta" onclick="switchTab('receta')">
                    <i class="fa-solid fa-prescription-bottle-medical"></i> Receta Médica
                </button>
            </div>
        </div>
        <div class="card-body" style="padding-top: 1rem; border-top: 1px solid var(--border-light);">
            <div id="tab-historial" class="tab-pane <?= $activeTab === 'historial' ? 'active' : '' ?>" style="<?= $activeTab === 'historial' ? '' : 'display: none;' ?>">
                <?php if(empty($notes)): ?>
                    <div class="alert alert-info">
                        <i class="fa-solid fa-info-circle"></i> No hay registros de atenciones previas.
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
                                    <?php if(!empty($note['appointment_id'])): ?>
                                        <div class="alert alert-secondary d-flex justify-between align-center" style="padding: 0.5rem; margin-bottom: 1rem; border-left: 4px solid var(--primary); flex-wrap: wrap; gap: 0.5rem;">
                                            <div>
                                                <i class="fa-solid fa-link text-primary"></i> <strong>Atención vinculada a Cita Confirmada #<?= $note['appointment_id'] ?></strong> 
                                                (<?= date('d/m/Y H:i', strtotime($note['appointment_date'])) ?>)
                                            </div>

                                        </div>
                                    <?php endif; ?>
                                    <?php if(!empty($note['chief_complaint'])): ?>
                                    <p><strong>Motivo de Consulta:</strong> <?= htmlspecialchars($note['chief_complaint']) ?></p>
                                    <?php endif; ?>
                                    <p><strong>Diagnóstico:</strong><br><?= nl2br(htmlspecialchars($note['diagnosis'])) ?></p>
                                    <?php if(!empty($note['treatment'])): ?>
                                        <p><strong>Tratamiento (Histórico):</strong><br><?= nl2br(htmlspecialchars($note['treatment'])) ?></p>
                                    <?php endif; ?>
                                    <?php if(!empty($note['clinical_notes'])): ?>
                                        <p><strong>Notas:</strong><br><?= nl2br(htmlspecialchars($note['clinical_notes'])) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div id="tab-atencion" class="tab-pane <?= $activeTab === 'atencion' ? 'active' : '' ?>" style="<?= $activeTab === 'atencion' ? '' : 'display: none;' ?>">
                <div id="notStartedAlert" class="alert alert-warning text-center" style="padding: 2rem; <?= ($activeAppointment && $activeAppointment['status'] === 'in_progress') ? 'display: none;' : '' ?>">
                    <?php if ($activeAppointment && ($activeAppointment['has_notes'] ?? 0) > 0): ?>
                        <i class="fa-solid fa-circle-check" style="font-size: 2rem; margin-bottom: 1rem; color: #10b981;"></i>
                        <h4>Atención ya finalizada</h4>
                        <p>La evolución clínica de esta cita ya fue registrada y guardada exitosamente. Puede consultar los detalles en la pestaña <strong>Historial Clínico</strong>.</p>
                    <?php else: ?>
                        <i class="fa-solid fa-lock" style="font-size: 2rem; margin-bottom: 1rem; color: #f59e0b;"></i>
                        <h4>La atención no ha sido iniciada</h4>
                        <p>Para escribir una nueva evolución, primero debe iniciar la atención médica usando el botón verde en la parte superior.</p>
                        <?php if ($activeAppointment && in_array($activeAppointment['status'], ['confirmed', 'agendada'])): ?>
                            <div style="margin-top: 1.25rem;">
                                <button type="button" class="btn btn-success" onclick="triggerStartAppointment();">
                                    <i class="fa-solid fa-play"></i> Iniciar Atención Médica Ahora
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if (!$activeAppointment): ?>
                        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(0,0,0,0.1);">
                            <p class="text-muted" style="font-size: 0.85rem; margin-bottom: 0.5rem;">¿Desea agregar una nota sin una cita asignada hoy?</p>
                            <button class="btn btn-sm btn-secondary" onclick="document.getElementById('freeNoteForm').style.display = 'block'; this.style.display = 'none';">
                                Agregar Evolución Libre
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                <div id="freeNoteForm" style="<?= ($activeAppointment && $activeAppointment['status'] === 'in_progress') ? 'display:block;' : 'display:none;' ?>">
                    <?php if ($activeAppointment): ?>
                        <div class="alert alert-info mb-4" id="activeApptBanner" style="border-left: 4px solid #0ea5e9;">
                            <div class="d-flex justify-between align-center" style="flex-wrap: wrap; gap: 0.5rem;">
                                <div>
                                    <h5 style="margin-bottom: 0.25rem;"><i class="fa-solid fa-calendar-check"></i> <strong>Cita Confirmada #<?= $activeAppointment['id'] ?></strong></h5>
                                    <span style="font-size: 0.9rem;">
                                        <strong>Fecha y Hora:</strong> <?= date('d/m/Y', strtotime($activeAppointment['appointment_date'])) ?> a las <?= date('H:i', strtotime($activeAppointment['appointment_date'])) ?>
                                    </span>
                                </div>

                            </div>
                        </div>
                    <?php endif; ?>
                    <form action="<?= $baseUrl ?>/doctor/patients/<?= $patient['id'] ?>/notes/store" method="POST" id="medicalEvolutionForm">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="appointment_id" id="formAppointmentId" value="<?= $activeAppointment ? $activeAppointment['id'] : '' ?>">
                        <div class="form-group mb-3">
                            <label class="form-label" style="font-weight: 600;">Motivo de Consulta <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="chief_complaint" id="chief_complaint" required placeholder="Ej: Dolor de cabeza, control de glucosa, malestar general...">
                        </div>
                        <div class="form-group mb-4">
                            <label class="form-label" style="font-weight: 600;">Diagnóstico <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="diagnosis" id="diagnosis" rows="4" required placeholder="Describa el diagnóstico clínico y evaluación médica..."></textarea>
                        </div>
                        <div class="d-flex justify-between align-center mt-4" style="padding-top: 1rem; border-top: 1px solid var(--border-light);">
                            <span class="text-muted" style="font-size: 0.85rem;">
                                <i class="fa-solid fa-circle-info"></i> Al guardar la evolución, la cita conservará el estado confirmada. Para tratamientos y recetas use las pestañas superiores.
                            </span>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa-solid fa-check"></i> Finalizar y Guardar Evolución
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <div id="tab-tratamiento" class="tab-pane <?= $activeTab === 'tratamiento' ? 'active' : '' ?>" style="<?= $activeTab === 'tratamiento' ? '' : 'display: none;' ?>">
                <div style="background: var(--bg-surface, #f8fafc); border: 1px solid var(--border-color, #e2e8f0); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem;">
                    <h4 class="mb-3" style="font-size: 1.05rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-notes-medical text-primary"></i> Registrar Nuevo Tratamiento
                    </h4>
                    <form action="<?= $baseUrl ?>/doctor/patients/<?= $patient['id'] ?>/treatments/store" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="appointment_id" value="<?= $activeAppointment ? $activeAppointment['id'] : '' ?>">
                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;" class="mb-3">
                            <div class="form-group">
                                <label class="form-label" style="font-weight: 600;">Nombre del Tratamiento / Procedimiento <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="title" required placeholder="Ej: Fisioterapia lumbar, Lavado ótico, Manejo dietético...">
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="font-weight: 600;">Tipo de Tratamiento</label>
                                <select class="form-control" name="treatment_type">
                                    <option value="Tratamiento Médico">Tratamiento Médico</option>
                                    <option value="Terapia Física">Terapia Física</option>
                                    <option value="Procedimiento Menor">Procedimiento Menor</option>
                                    <option value="Recomendación Terapéutica">Recomendación Terapéutica</option>
                                    <option value="Cuidado General">Cuidado General</option>
                                </select>
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;" class="mb-3">
                            <div class="form-group">
                                <label class="form-label" style="font-weight: 600;">Frecuencia</label>
                                <input type="text" class="form-control" name="frequency" placeholder="Ej: Cada 12 horas, 3 veces por semana">
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="font-weight: 600;">Duración Estimada</label>
                                <input type="text" class="form-control" name="duration" placeholder="Ej: 15 días, 1 mes, 6 sesiones">
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="font-weight: 600;">Fecha de Inicio</label>
                                <input type="date" class="form-control" name="start_date" value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                        <div class="form-group mb-3">
                            <label class="form-label" style="font-weight: 600;">Indicaciones y Detalles del Tratamiento <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="description" rows="3" required placeholder="Detalle los cuidados, procedimientos, recomendaciones o metas terapéuticas..."></textarea>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-save"></i> Guardar Tratamiento
                            </button>
                        </div>
                    </form>
                </div>
                <h4 class="mb-3" style="font-size: 1rem; color: var(--text-muted);">
                    <i class="fa-solid fa-list-check"></i> Tratamientos Registrados del Paciente
                </h4>
                <?php if (empty($treatments)): ?>
                    <div class="alert alert-info">
                        <i class="fa-solid fa-info-circle"></i> No hay tratamientos registrados para este paciente.
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php foreach ($treatments as $t): ?>
                            <div style="border: 1px solid var(--border-color, #e2e8f0); border-radius: 8px; padding: 1rem; background: #fff;">
                                <div class="d-flex justify-between align-center mb-2">
                                    <div>
                                        <h5 style="margin: 0; font-size: 1.05rem;">
                                            <strong><?= htmlspecialchars($t['title']) ?></strong>
                                            <span class="badge badge-secondary" style="font-size: 0.75rem; margin-left: 0.5rem;"><?= htmlspecialchars($t['treatment_type']) ?></span>
                                        </h5>
                                        <small class="text-muted">
                                            Dr. <?= htmlspecialchars($t['doctor_name'] ?? 'Médico') ?> — <?= date('d/m/Y', strtotime($t['created_at'])) ?>
                                        </small>
                                    </div>
                                    <div>
                                        <?php if ($t['status'] === 'active'): ?>
                                            <span class="badge badge-success">Activo</span>
                                        <?php elseif ($t['status'] === 'completed'): ?>
                                            <span class="badge badge-secondary">Completado</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Cancelado</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <p style="margin: 0.5rem 0; font-size: 0.95rem; line-height: 1.5;">
                                    <?= nl2br(htmlspecialchars($t['description'])) ?>
                                </p>
                                <div class="d-flex justify-between align-center mt-2 pt-2" style="border-top: 1px dashed var(--border-light); font-size: 0.85rem; color: var(--text-muted);">
                                    <div>
                                        <?php if (!empty($t['frequency'])): ?>
                                            <span><strong>Frecuencia:</strong> <?= htmlspecialchars($t['frequency']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($t['duration'])): ?>
                                            <span style="margin-left: 1rem;"><strong>Duración:</strong> <?= htmlspecialchars($t['duration']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($t['status'] === 'active'): ?>
                                        <form action="<?= $baseUrl ?>/doctor/treatments/<?= $t['id'] ?>/status" method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="patient_id" value="<?= $patient['id'] ?>">
                                            <input type="hidden" name="status" value="completed">
                                            <button type="submit" class="btn btn-sm btn-outline-success" style="font-size: 0.8rem; padding: 0.25rem 0.6rem;">
                                                <i class="fa-solid fa-check"></i> Marcar Completado
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div id="tab-receta" class="tab-pane <?= $activeTab === 'receta' ? 'active' : '' ?>" style="<?= $activeTab === 'receta' ? '' : 'display: none;' ?>">
                <div style="background: var(--bg-surface, #f8fafc); border: 1px solid var(--border-color, #e2e8f0); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem;">
                    <h4 class="mb-3" style="font-size: 1.05rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-prescription-bottle-medical text-primary"></i> Prescribir Receta Médica
                    </h4>
                    <form action="<?= $baseUrl ?>/doctor/patients/<?= $patient['id'] ?>/prescriptions/store" method="POST" id="prescriptionForm">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="appointment_id" value="<?= $activeAppointment ? $activeAppointment['id'] : '' ?>">
                        <div id="medicationsContainer">
                            <div class="medication-row mb-3" style="background: #fff; border: 1px solid var(--border-color, #e2e8f0); border-radius: 8px; padding: 1rem; position: relative;">
                                <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 0.75rem;" class="mb-2">
                                    <div class="form-group">
                                        <label class="form-label" style="font-weight: 600; font-size: 0.85rem;">Medicamento <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="medications[0][medication_name]" required placeholder="Ej: Paracetamol 500mg">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" style="font-weight: 600; font-size: 0.85rem;">Dosis <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="medications[0][dosage]" required placeholder="Ej: 1 tableta">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" style="font-weight: 600; font-size: 0.85rem;">Frecuencia <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="medications[0][frequency]" required placeholder="Ej: Cada 8 horas">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" style="font-weight: 600; font-size: 0.85rem;">Duración <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="medications[0][duration]" required placeholder="Ej: 5 días">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" style="font-weight: 600; font-size: 0.85rem;">Indicaciones de Uso</label>
                                    <input type="text" class="form-control" name="medications[0][instructions]" placeholder="Ej: Tomar vía oral después de las comidas con abundante agua">
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-between align-center mt-3">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="addMedicationRow()">
                                <i class="fa-solid fa-plus"></i> Agregar Otro Medicamento
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-file-prescription"></i> Guardar y Emitir Receta
                            </button>
                        </div>
                    </form>
                </div>
                <h4 class="mb-3" style="font-size: 1rem; color: var(--text-muted);">
                    <i class="fa-solid fa-clock-rotate-left"></i> Historial de Prescripciones del Paciente
                </h4>
                <?php if (empty($prescriptions)): ?>
                    <div class="alert alert-info">
                        <i class="fa-solid fa-info-circle"></i> No hay recetas emitidas para este paciente.
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php foreach ($prescriptions as $p): ?>
                            <div style="border: 1px solid var(--border-color, #e2e8f0); border-radius: 8px; padding: 1.25rem; background: #fff;">
                                <div class="d-flex justify-between align-center mb-2">
                                    <div>
                                        <h5 style="margin: 0; font-size: 1.05rem; color: var(--primary);">
                                            <i class="fa-solid fa-pills"></i> <strong><?= htmlspecialchars($p['medication_name']) ?></strong>
                                        </h5>
                                        <small class="text-muted">
                                            Receta #<?= $p['id'] ?> — Dr(a). <?= htmlspecialchars($p['doctor_name']) ?> (<?= htmlspecialchars($p['specialty_name'] ?? 'Medicina') ?>) — <?= date('d/m/Y H:i', strtotime($p['created_at'])) ?>
                                        </small>
                                    </div>
                                    <a href="<?= $baseUrl ?>/doctor/prescriptions/<?= $p['id'] ?>/pdf" target="_blank" class="btn btn-sm btn-danger" title="Descargar o Imprimir Receta">
                                        <i class="fa-solid fa-file-pdf"></i> Imprimir PDF
                                    </a>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.5rem; background: var(--bg-surface, #f8fafc); padding: 0.75rem; border-radius: 6px; font-size: 0.9rem;" class="mb-2">
                                    <div><strong>Dosis:</strong> <?= htmlspecialchars($p['dosage']) ?></div>
                                    <div><strong>Frecuencia:</strong> <?= htmlspecialchars($p['frequency']) ?></div>
                                    <div><strong>Duración:</strong> <?= htmlspecialchars($p['duration']) ?></div>
                                </div>
                                <?php if (!empty($p['instructions'])): ?>
                                    <div style="font-size: 0.9rem;">
                                        <strong>Indicaciones:</strong> <?= nl2br(htmlspecialchars($p['instructions'])) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<style>
/* Estilos para las Pestañas (Tabs) */
.custom-tabs {
    display: flex;
    gap: 1rem;
    overflow-x: auto;
}
.tab-btn {
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    padding: 0.75rem 1rem;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-muted);
    cursor: pointer;
    transition: all 0.2s ease;
}
.tab-btn:hover {
    color: var(--text);
}
.tab-btn.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
}
@keyframes fadeInForm {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}
.fade-in-form {
    animation: fadeInForm 0.35s ease forwards;
}
</style>
<script>
let medIndex = 1;
function addMedicationRow() {
    const container = document.getElementById('medicationsContainer');
    if (!container) return;
    const row = document.createElement('div');
    row.className = 'medication-row mb-3 fade-in-form';
    row.style.background = '#fff';
    row.style.border = '1px solid var(--border-color, #e2e8f0)';
    row.style.borderRadius = '8px';
    row.style.padding = '1rem';
    row.style.position = 'relative';
    row.innerHTML = `
        <button type="button" class="btn btn-sm btn-icon btn-danger" style="position: absolute; top: 0.5rem; right: 0.5rem;" onclick="this.closest('.medication-row').remove();" title="Eliminar medicamento">
            <i class="fa-solid fa-times"></i>
        </button>
        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 0.75rem; padding-right: 2rem;" class="mb-2">
            <div class="form-group">
                <label class="form-label" style="font-weight: 600; font-size: 0.85rem;">Medicamento <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="medications[${medIndex}][medication_name]" required placeholder="Ej: Ibuprofeno 400mg">
            </div>
            <div class="form-group">
                <label class="form-label" style="font-weight: 600; font-size: 0.85rem;">Dosis <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="medications[${medIndex}][dosage]" required placeholder="Ej: 1 tableta">
            </div>
            <div class="form-group">
                <label class="form-label" style="font-weight: 600; font-size: 0.85rem;">Frecuencia <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="medications[${medIndex}][frequency]" required placeholder="Ej: Cada 8 horas">
            </div>
            <div class="form-group">
                <label class="form-label" style="font-weight: 600; font-size: 0.85rem;">Duración <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="medications[${medIndex}][duration]" required placeholder="Ej: 3 días">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label" style="font-weight: 600; font-size: 0.85rem;">Indicaciones de Uso</label>
            <input type="text" class="form-control" name="medications[${medIndex}][instructions]" placeholder="Ej: Tomar con comida">
        </div>
    `;
    container.appendChild(row);
    medIndex++;
}
function toggleAccordion(btn) {
    const item = btn.closest('.accordion-item-custom');
    const wasOpen = item.classList.contains('open');
    document.querySelectorAll('.accordion-item-custom').forEach(function(el) {
        el.classList.remove('open');
    });
    if (!wasOpen) {
        item.classList.add('open');
    }
}
function switchTab(tabId) {
    // Esconder todos los panes
    document.querySelectorAll('.tab-pane').forEach(el => {
        el.style.display = 'none';
        el.classList.remove('active');
    });
    // Mostrar el pane seleccionado
    const targetPane = document.getElementById('tab-' + tabId);
    if (targetPane) {
        targetPane.style.display = 'block';
        targetPane.classList.add('active');
    }
    // Cambiar estado activo de los botones de pestañas
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    const activeBtn = document.getElementById('btn-tab-' + tabId);
    if (activeBtn) {
        activeBtn.classList.add('active');
    }
}
function triggerStartAppointment() {
    const form = document.getElementById('startAppointmentForm');
    if (form) {
        form.dispatchEvent(new Event('submit', { cancelable: true }));
    }
}
function showNotification(msg, type) {
    const existing = document.getElementById('temp-toast-msg');
    if (existing) existing.remove();
    const toast = document.createElement('div');
    toast.id = 'temp-toast-msg';
    toast.className = 'alert alert-' + (type === 'success' ? 'success' : 'info');
    toast.style.position = 'fixed';
    toast.style.top = '20px';
    toast.style.right = '20px';
    toast.style.zIndex = '9999';
    toast.style.boxShadow = '0 10px 25px rgba(0,0,0,0.15)';
    toast.style.borderRadius = '8px';
    toast.style.padding = '0.75rem 1.25rem';
    toast.style.display = 'flex';
    toast.style.alignItems = 'center';
    toast.style.gap = '0.75rem';
    toast.innerHTML = '<i class="fa-solid fa-circle-check text-success"></i> <strong>' + msg + '</strong>';
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.4s ease';
        setTimeout(() => toast.remove(), 400);
    }, 3500);
}
document.addEventListener('DOMContentLoaded', function() {
    const startForm = document.getElementById('startAppointmentForm');
    if (startForm) {
        startForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnStartAppointment');
            const originalContent = btn ? btn.innerHTML : '';
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Iniciando...';
            }
            try {
                const formData = new FormData(startForm);
                formData.append('ajax', '1');
                const response = await fetch(startForm.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = await response.json();
                if (data.success) {
                    // 1. Actualizar el contenedor superior reemplazando botón por badge
                    const container = document.getElementById('appointment-action-container');
                    if (container) {
                        container.innerHTML = '<span class="badge badge-warning" id="inProgressBadge" style="padding: 0.5rem 1rem; font-size: 0.9rem;"><i class="fa-solid fa-spinner fa-spin"></i> Atención en Progreso</span>';
                    }
                    // 2. Ocultar mensaje de atención no iniciada
                    const notStartedAlert = document.getElementById('notStartedAlert');
                    if (notStartedAlert) {
                        notStartedAlert.style.display = 'none';
                    }
                    // 3. Mostrar el formulario de evolución médica con efecto suave
                    const noteForm = document.getElementById('freeNoteForm');
                    if (noteForm) {
                        noteForm.classList.add('fade-in-form');
                        noteForm.style.display = 'block';
                    }
                    // 4. Asignar el ID de la cita en el campo oculto si viene devuelto
                    if (data.appointment_id) {
                        const apptInput = document.getElementById('formAppointmentId');
                        if (apptInput) apptInput.value = data.appointment_id;
                    }
                    // 5. Cambiar a la pestaña "Atención Actual"
                    switchTab('atencion');
                    // 6. Enfocar automáticamente el primer campo del formulario
                    setTimeout(() => {
                        const input = document.getElementById('chief_complaint');
                        if (input) {
                            input.focus();
                            input.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    }, 180);
                    // 7. Notificación visual
                    showNotification(data.message || 'Atención médica iniciada.', 'success');
                } else {
                    alert(data.message || 'Error al iniciar la atención.');
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = originalContent;
                    }
                }
            } catch (err) {
                console.warn('Error en llamada AJAX, usando envío tradicional:', err);
                startForm.submit();
            }
        });
    }
});
</script>
