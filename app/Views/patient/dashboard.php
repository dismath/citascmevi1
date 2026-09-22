<?php if (!$patientId): ?>
    <div class="alert alert-warning">
        <i class="fa-solid fa-triangle-exclamation"></i> No tienes un perfil de paciente asociado a esta cuenta. Por favor, contacta a recepción.
    </div>
<?php else: ?>
    <div class="grid-3 mb-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: var(--primary-100); color: var(--primary);">
                <i class="fa-regular fa-calendar-check"></i>
            </div>
            <div class="stat-value"><?= count($allAppointments) ?></div>
            <div class="stat-label">Total de Citas</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #d1fae5; color: var(--accent);">
                <i class="fa-solid fa-notes-medical"></i>
            </div>
            <div class="stat-value"><a href="<?= $baseUrl ?>/patient/records" class="text-accent" style="font-size: .9rem;">Ver Historial</a></div>
            <div class="stat-label">Mi Historial Clínico</div>
        </div>
        <div class="stat-card" style="cursor: pointer;" onclick="window.location.href='<?= $baseUrl ?>/'">
            <div class="stat-icon" style="background: #e0e7ff; color: #4f46e5;">
                <i class="fa-solid fa-calendar-plus"></i>
            </div>
            <div class="stat-value"><a href="<?= $baseUrl ?>/" class="text-secondary" style="font-size: .9rem; text-decoration: none;">Agendar</a></div>
            <div class="stat-label">Nueva Cita</div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h3><i class="fa-regular fa-calendar-check text-primary"></i> Mis Citas</h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if(empty($allAppointments)): ?>
                <div class="p-4 text-center text-muted">
                    <i class="fa-regular fa-calendar-xmark" style="font-size: 2.5rem; display: block; margin-bottom: 1rem; color: var(--border);"></i>
                    No tienes citas registradas.
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha y Hora</th>
                                <th>Especialidad</th>
                                <th>Médico</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $settingsModel = new \App\Models\SystemSetting();
                            $maxReschedules = (int)$settingsModel->get('max_reschedules', '1');
                            foreach($allAppointments as $app): 
                            ?>
                            <tr class="row-status-<?= ($app['reschedule_count'] ?? 0) > 0 ? 'rescheduled' : htmlspecialchars($app['status']) ?>">
                                <td><strong><?= date('d/m/Y', strtotime($app['appointment_date'])) ?></strong> <span class="text-muted"><?= date('H:i', strtotime($app['appointment_date'])) ?></span></td>
                                <td><?= htmlspecialchars($app['specialty_name']) ?></td>
                                <td>Dr. <?= htmlspecialchars($app['doctor_name']) ?></td>
                                <td>
                                    <?php if($app['status'] === 'completed'): ?>
                                        <span class="badge badge-success"><i class="fa-solid fa-check-circle"></i> Completada</span>
                                    <?php elseif($app['status'] === 'in_progress'): ?>
                                        <span class="badge badge-warning"><i class="fa-solid fa-spinner fa-spin"></i> En Progreso</span>
                                    <?php elseif($app['status'] === 'confirmed'): ?>
                                        <span class="badge badge-success">Confirmada</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Pendiente</span>
                                    <?php endif; ?>
                                    <?php if(in_array($app['status'], ['pending', 'confirmed'])): ?>
                                        <?php if ($maxReschedules === 0 || ($app['reschedule_count'] ?? 0) < $maxReschedules): ?>
                                            <a href="<?= $baseUrl ?>/patient/appointments/reschedule/<?= $app['id'] ?>" class="btn btn-sm btn-outline-primary" style="margin-left: 0.5rem; padding: 0.1rem 0.5rem; font-size: 0.75rem;">
                                                <i class="fa-solid fa-calendar-days"></i> Reagendar
                                            </a>
                                        <?php else: ?>
                                            <span class="badge badge-secondary" style="margin-left: 0.5rem; font-size: 0.7rem;" title="Límite de reagendamientos alcanzado">
                                                <i class="fa-solid fa-rotate"></i> Reagendada (<?= $app['reschedule_count'] ?>)
                                            </span>
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
<?php endif; ?>
