<?php if ($todayCount > 0): ?>
<div class="alert alert-warning mb-3" style="display: flex; align-items: center; gap: 1rem; padding: 1rem 1.5rem; border-left: 5px solid #f59e0b; animation: slideDown 0.4s ease;">
    <div style="font-size: 2rem; animation: bellRing 1s ease infinite;">
        <i class="fa-solid fa-bell"></i>
    </div>
    <div style="flex: 1;">
        <strong style="font-size: 1.1rem;">🩺 Tienes <?= $todayCount ?> cita<?= $todayCount > 1 ? 's' : '' ?> pendiente<?= $todayCount > 1 ? 's' : '' ?> por atender hoy</strong>
        <p style="margin: 0.25rem 0 0; opacity: 0.85;">Revisa tu lista de citas confirmadas para iniciar la atención médica.</p>
    </div>
    <a href="<?= $baseUrl ?>/doctor/appointments" class="btn btn-warning" style="white-space: nowrap;">
        <i class="fa-solid fa-arrow-right"></i> Ir a Mis Citas
    </a>
</div>
<?php endif; ?>
<?php if ($upcomingCount > 0): ?>
<div class="alert alert-info mb-3" style="display: flex; align-items: center; gap: 1rem; padding: 1rem 1.5rem; border-left: 5px solid var(--primary);">
    <div style="font-size: 1.5rem; color: var(--primary);">
        <i class="fa-regular fa-calendar-check"></i>
    </div>
    <div style="flex: 1;">
        <strong>Próximos 7 días:</strong> Tienes <strong><?= $upcomingCount ?></strong> cita<?= $upcomingCount > 1 ? 's' : '' ?> confirmada<?= $upcomingCount > 1 ? 's' : '' ?> programada<?= $upcomingCount > 1 ? 's' : '' ?>.
    </div>
</div>
<?php endif; ?>
<div class="grid-4 mb-3">
    <div class="stat-card" style="cursor: pointer;" onclick="window.location.href='<?= $baseUrl ?>/doctor/appointments'">
        <div class="stat-icon" style="background: <?= $todayCount > 0 ? '#fef3c7' : '#d1fae5' ?>; color: <?= $todayCount > 0 ? '#d97706' : 'var(--accent)' ?>;">
            <i class="fa-solid fa-calendar-day"></i>
        </div>
        <div class="stat-value"><?= $todayCount ?></div>
        <div class="stat-label">Citas Hoy por Atender</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #e0e7ff; color: #4f46e5;">
            <i class="fa-solid fa-calendar-week"></i>
        </div>
        <div class="stat-value"><?= $upcomingCount ?></div>
        <div class="stat-label">Citas Próximos 7 Días</div>
    </div>
    <div class="stat-card" style="cursor: pointer;" onclick="window.location.href='<?= $baseUrl ?>/doctor/schedule'">
        <div class="stat-icon" style="background: #d1fae5; color: var(--accent);">
            <i class="fa-solid fa-clock"></i>
        </div>
        <div class="stat-value"><a href="<?= $baseUrl ?>/doctor/schedule" class="text-primary" style="font-size: .9rem;">Ver Horario</a></div>
        <div class="stat-label">Mi Horario de Turnos</div>
    </div>
    <div class="stat-card" style="cursor: pointer;" onclick="window.location.href='<?= $baseUrl ?>/'">
        <div class="stat-icon" style="background: #e0e7ff; color: #4f46e5;">
            <i class="fa-solid fa-calendar-plus"></i>
        </div>
        <div class="stat-value"><a href="<?= $baseUrl ?>/" class="text-secondary" style="font-size: .9rem; text-decoration: none;">Agendar</a></div>
        <div class="stat-label">Nueva Cita</div>
    </div>
</div>
<?php if ($todayCount > 0): ?>
<div class="card mb-3">
    <div class="card-header" style="background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: white;">
        <h3 style="color: white;"><i class="fa-solid fa-clipboard-list"></i> Citas de Hoy — <?= date('d/m/Y') ?></h3>
        <span class="badge" style="background: rgba(255,255,255,0.3); color: white; font-size: 1rem; padding: 0.4rem 1rem;">
            <?= $todayCount ?> pendiente<?= $todayCount > 1 ? 's' : '' ?>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Hora</th>
                        <th>Paciente</th>
                        <th>Identificación</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($todayAppointments as $app): ?>
                    <tr class="row-status-<?= ($app['reschedule_count'] ?? 0) > 0 ? 'rescheduled' : htmlspecialchars($app['status']) ?>">
                        <td>
                            <strong style="font-size: 1.1rem;"><?= date('H:i', strtotime($app['appointment_date'])) ?></strong>
                        </td>
                        <td><?= htmlspecialchars($app['patient_name']) ?></td>
                        <td><?= htmlspecialchars($app['id_number']) ?></td>
                        <td>
                            <?php if ($app['status'] === 'in_progress'): ?>
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
                            <?php if (($app['has_notes'] ?? 0) > 0): ?>
                                <button class="btn btn-sm btn-secondary" disabled title="Atención médica ya finalizada">
                                    <i class="fa-solid fa-check"></i> Atendida
                                </button>
                            <?php else: ?>
                                <a href="<?= $baseUrl ?>/doctor/appointments/<?= $app['id'] ?>/start" class="btn btn-sm <?= $app['status'] === 'in_progress' ? 'btn-success' : 'btn-primary' ?>">
                                    <i class="fa-solid fa-notes-medical"></i> <?= $app['status'] === 'in_progress' ? 'Continuar' : 'Atender' ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-calendar-days text-primary"></i> Calendario de Citas Confirmadas</h3>
    </div>
    <div class="card-body">
        <?php if (!$doctorId): ?>
            <div class="alert alert-warning">
                <i class="fa-solid fa-triangle-exclamation"></i> No tienes un perfil de médico asociado a este usuario.
            </div>
        <?php else: ?>
            <div id="doctor-calendar"></div>
        <?php endif; ?>
    </div>
</div>
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/es.js'></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($doctorId): ?>
    var calendarEl = document.getElementById('doctor-calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth'
        },
        events: BASE_URL + '/api/doctor/appointments/<?= $doctorId ?>',
        eventContent: function(arg) {
            if (arg.event.display === 'background') return;
            let html = '<div style="display: flex; gap: 4px; flex-wrap: wrap;">';
            if (arg.event.extendedProps.dispCount > 0) {
                html += `<span style="background: #d1fae5; color: #065f46; border-radius: 12px; padding: 2px 6px; font-size: 0.75rem; font-weight: 600; white-space: nowrap;">${arg.event.extendedProps.dispCount} disp.</span>`;
            }
            if (arg.event.extendedProps.resCount > 0) {
                html += `<span style="background: #fef3c7; color: #92400e; border-radius: 12px; padding: 2px 6px; font-size: 0.75rem; font-weight: 600; white-space: nowrap;">${arg.event.extendedProps.resCount} res.</span>`;
            }
            html += '</div>';
            return { html: html };
        },
        dateClick: function(info) {
            window.location.href = BASE_URL + '/doctor/appointments?date=' + info.dateStr;
        }
    });
    calendar.render();
    <?php endif; ?>
});
</script>
<style>
.custom-summary-event {
    background: transparent !important;
    border: none !important;
}
.custom-summary-event .fc-event-main {
    padding: 0 !important;
}
.custom-summary-event:hover {
    background: transparent !important;
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-15px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes bellRing {
    0%, 100% { transform: rotate(0); }
    10% { transform: rotate(14deg); }
    20% { transform: rotate(-12deg); }
    30% { transform: rotate(10deg); }
    40% { transform: rotate(-8deg); }
    50% { transform: rotate(0); }
}
</style>
