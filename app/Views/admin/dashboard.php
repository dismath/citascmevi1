<div class="grid-4 mb-3">
    <div class="stat-card">
        <div class="stat-icon" style="background: var(--primary-100); color: var(--primary);">
            <i class="fa-solid fa-calendar-day"></i>
        </div>
        <div class="stat-value"><?= htmlspecialchars($stats['today']) ?></div>
        <div class="stat-label">Citas de Hoy</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #fef3c7; color: var(--warning);">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
        <div class="stat-value"><?= htmlspecialchars($stats['pending']) ?></div>
        <div class="stat-label">Citas Pendientes</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #d1fae5; color: var(--accent);">
            <i class="fa-solid fa-user-doctor"></i>
        </div>
        <div class="stat-value"><?= htmlspecialchars($stats['doctors']) ?></div>
        <div class="stat-label">Médicos Activos</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #e0e7ff; color: #4f46e5;">
            <i class="fa-solid fa-users"></i>
        </div>
        <div class="stat-value"><?= htmlspecialchars($stats['patients']) ?></div>
        <div class="stat-label">Pacientes Registrados</div>
    </div>
</div>
<div class="grid-3 mb-3">
    <div class="card" style="grid-column: span 2;">
        <div class="card-header">
            <h3><i class="fa-solid fa-chart-area text-primary"></i> Actividad últimos 7 días</h3>
        </div>
        <div class="card-body">
            <canvas id="appointmentsChart" height="100"></canvas>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h3><i class="fa-solid fa-bolt text-warning"></i> Citas Recientes</h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($recentAppointments)): ?>
                <div class="p-3 text-center text-muted">No hay citas recientes.</div>
            <?php else: ?>
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <?php foreach($recentAppointments as $app): ?>
                        <li style="padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-light);">
                            <div class="d-flex justify-between align-center mb-1">
                                <strong><?= htmlspecialchars($app['patient_name']) ?></strong>
                                <?php
                                $statusColors = [
                                    'pending' => 'warning',
                                    'confirmed' => 'primary',
                                    'completed' => 'success',
                                    'cancelled' => 'danger'
                                ];
                                $statusLabels = [
                                    'pending' => 'Pendiente',
                                    'confirmed' => 'Confirmada',
                                    'completed' => 'Completada',
                                    'cancelled' => 'Cancelada'
                                ];
                                $color = $statusColors[$app['status']] ?? 'secondary';
                                $label = $statusLabels[$app['status']] ?? $app['status'];
                                ?>
                                <span class="badge badge-<?= $color ?>"><?= $label ?></span>
                            </div>
                            <div class="text-sm text-muted">
                                <i class="fa-solid fa-user-doctor"></i> <?= htmlspecialchars($app['doctor_name']) ?>
                            </div>
                            <div class="text-sm text-muted mt-1">
                                <i class="fa-regular fa-clock"></i> <?= date('d M, H:i', strtotime($app['appointment_date'])) ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <div class="card-footer text-center">
            <a href="<?= $baseUrl ?>/admin/appointments" class="btn btn-sm btn-secondary">Ver todas las citas</a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('appointmentsChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= $chart['labels'] ?>,
                datasets: [
                    {
                        label: 'Confirmadas',
                        data: <?= $chart['confirmed'] ?>,
                        borderColor: '#0ea5e9',
                        backgroundColor: 'rgba(14, 165, 233, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Canceladas',
                        data: <?= $chart['cancelled'] ?>,
                        borderColor: '#ef4444',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top', }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    }
});
</script>
