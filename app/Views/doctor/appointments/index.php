<div class="d-flex justify-between align-center mb-3">
    <div></div>
    <a href="<?= $baseUrl ?>/doctor/dashboard" class="btn btn-secondary">
        <i class="fa-solid fa-arrow-left"></i> Volver al Calendario
    </a>
</div>
<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-user-group text-primary"></i> Mis Citas</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if(empty($appointments)): ?>
            <div class="p-3 text-center text-muted">No hay citas registradas.</div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha y Hora</th>
                            <th>Paciente</th>
                            <th>Identificación</th>
                            <th>Teléfono</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($appointments as $app): ?>
                        <tr class="row-status-<?= ($app['reschedule_count'] ?? 0) > 0 ? 'rescheduled' : htmlspecialchars($app['status']) ?>">
                            <td>
                                <strong><?= date('d/m/Y', strtotime($app['appointment_date'])) ?></strong><br>
                                <span class="text-muted"><?= date('H:i', strtotime($app['appointment_date'])) ?></span>
                            </td>
                            <td><?= htmlspecialchars($app['patient_name']) ?></td>
                            <td><?= htmlspecialchars($app['id_number']) ?></td>
                            <td><?= htmlspecialchars($app['phone']) ?></td>
                            <td>
                                <?php if($app['status'] === 'completed'): ?>
                                    <span class="badge badge-success"><i class="fa-solid fa-check-circle"></i> Completada</span>
                                <?php elseif($app['status'] === 'in_progress'): ?>
                                    <span class="badge badge-warning"><i class="fa-solid fa-spinner fa-spin"></i> En Progreso</span>
                                <?php else: ?>
                                    <span class="badge badge-info">Confirmada</span>
                                <?php endif; ?>
                                <?php if (($app['reschedule_count'] ?? 0) > 0): ?>
                                    <span class="badge badge-warning" style="margin-top: 4px; display: inline-block;" title="Reagendada <?= $app['reschedule_count'] ?> vez/veces">
                                        <i class="fa-solid fa-rotate"></i> <?= $app['reschedule_count'] ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($app['status'] === 'completed'): ?>
                                    <span class="text-muted">-</span>
                                <?php elseif(($app['has_notes'] ?? 0) > 0): ?>
                                    <button class="btn btn-sm btn-secondary" disabled title="Atención médica ya finalizada">
                                        <i class="fa-solid fa-check"></i> Atendida
                                    </button>
                                <?php else: ?>
                                    <?php $isToday = date('Y-m-d', strtotime($app['appointment_date'])) === date('Y-m-d'); ?>
                                    <?php if($isToday): ?>
                                        <a href="<?= $baseUrl ?>/doctor/appointments/<?= $app['id'] ?>/start" class="btn btn-sm <?= $app['status'] === 'in_progress' ? 'btn-success' : 'btn-primary' ?>">
                                            <i class="fa-solid fa-notes-medical"></i> <?= $app['status'] === 'in_progress' ? 'Continuar' : 'Atender' ?>
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" disabled title="Solo se puede atender el mismo día de la cita">
                                            <i class="fa-solid fa-lock"></i> Atender
                                        </button>
                                    <?php endif; ?>
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
