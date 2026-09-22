<?php if (!$doctorId && empty($allDoctors)): ?>
    <div class="alert alert-warning">
        <i class="fa-solid fa-triangle-exclamation"></i> No tienes un perfil de médico asociado a este usuario.
    </div>
<?php else: ?>
    <?php if (!empty($allDoctors)): ?>
        <div class="card mb-3" style="border-left: 4px solid var(--primary);">
            <div class="card-body py-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-user-doctor text-primary"></i>
                    <strong style="font-size: 0.9rem;">Médico a gestionar:</strong>
                    <select class="form-control" style="width: auto; display: inline-block; padding: 0.25rem 0.5rem; font-size: 0.88rem;" onchange="window.location.href='?doctor_id=' + this.value + '&year=<?= $year ?>&month=<?= $month ?>'">
                        <?php foreach($allDoctors as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= $doctorId == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <span class="badge badge-primary"><i class="fa-solid fa-shield-halved"></i> Modo Gestión Administrativa</span>
            </div>
        </div>
    <?php endif; ?>
    <?php
    $months = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    $monthName = $months[$month];
    $prevMonth = $month - 1;
    $prevYear = $year;
    if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
    $nextMonth = $month + 1;
    $nextYear = $year;
    if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
    // Calendar logic
    $timestamp = mktime(0, 0, 0, $month, 1, $year);
    $daysInMonth = date('t', $timestamp);
    $firstDayOfWeek = date('N', $timestamp); // 1 = Lunes, 7 = Domingo
    $prevTimestamp = mktime(0, 0, 0, $prevMonth, 1, $prevYear);
    $daysInPrevMonth = date('t', $prevTimestamp);
    ?>
    <div class="grid-3 mb-3">
        <div class="card">
            <div class="card-header">
                <h3><i class="fa-solid fa-bolt text-warning"></i> Generar Horarios</h3>
            </div>
            <div class="card-body">
                <form action="<?= $baseUrl ?>/doctor/schedule/generate" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <div class="form-group">
                        <label class="form-label">Desde Fecha</label>
                        <input type="date" class="form-control" name="start_date" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Hasta Fecha</label>
                        <input type="date" class="form-control" name="end_date" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Hora Inicio</label>
                        <input type="time" class="form-control" name="start_time" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Hora Fin</label>
                        <input type="time" class="form-control" name="end_time" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Intervalo (Minutos)</label>
                        <select class="form-control" name="interval" required>
                            <option value="15">15 Minutos</option>
                            <option value="20">20 Minutos</option>
                            <option value="30" selected>30 Minutos</option>
                            <option value="45">45 Minutos</option>
                            <option value="60">1 Hora</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fa-solid fa-bolt"></i> Generar
                    </button>
                </form>
            </div>
        </div>
        <div class="card" style="grid-column: span 2;">
            <div class="card-body" style="padding: 1.5rem;">
                <div class="d-flex align-center gap-2 mb-3">
                    <div class="d-flex align-center gap-1">
                        <a href="?year=<?= $prevYear ?>&month=<?= $prevMonth ?>" class="btn btn-icon btn-secondary"><i class="fa-solid fa-chevron-left"></i></a>
                        <h2 style="margin: 0 1rem; font-size: 1.25rem; font-weight: 700; width: 140px; text-align: center;"><?= $monthName ?> <?= $year ?></h2>
                        <a href="?year=<?= $nextYear ?>&month=<?= $nextMonth ?>" class="btn btn-icon btn-secondary"><i class="fa-solid fa-chevron-right"></i></a>
                    </div>
                    <a href="?year=<?= date('Y') ?>&month=<?= date('m') ?>" class="btn btn-secondary btn-sm" style="margin-left: .5rem;"><i class="fa-solid fa-crosshairs"></i> Hoy</a>
                </div>
                <div class="d-flex gap-3 mb-3" style="font-size: .85rem; font-weight: 500; color: var(--text-muted);">
                    <div class="d-flex align-center gap-1"><span style="width:10px;height:10px;border-radius:50%;background:#10b981;"></span> Disponible</div>
                    <div class="d-flex align-center gap-1"><span style="width:10px;height:10px;border-radius:50%;background:#f59e0b;"></span> Reservado</div>
                    <div class="d-flex align-center gap-1"><span style="width:10px;height:10px;border-radius:50%;background:#ef4444;"></span> Bloqueado</div>
                </div>
                <div class="custom-calendar-grid">
                    <div class="cal-header">LUN</div>
                    <div class="cal-header">MAR</div>
                    <div class="cal-header">MIÉ</div>
                    <div class="cal-header">JUE</div>
                    <div class="cal-header">VIE</div>
                    <div class="cal-header">SÁB</div>
                    <div class="cal-header">DOM</div>
                    <?php 
                    // Previous month days
                    for ($i = 1; $i < $firstDayOfWeek; $i++) {
                        $d = $daysInPrevMonth - ($firstDayOfWeek - 1) + $i;
                        echo '<div class="cal-day other-month">' . $d . '</div>';
                    }
                    // Current month days
                    for ($d = 1; $d <= $daysInMonth; $d++) {
                        $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
                        $isWeekend = (date('N', strtotime($dateStr)) >= 6);
                        $class = $isWeekend ? ' weekend' : '';
                        $isToday = ($dateStr === date('Y-m-d'));
                        $daySummary = $summary[$dateStr] ?? null;
                        echo '<div class="cal-day' . $class . '" onclick="showSlots(\'' . $dateStr . '\')">';
                        if ($isToday) {
                            echo '<div class="cal-date today">' . $d . '</div>';
                        } else {
                            echo '<div class="cal-date">' . $d . '</div>';
                        }
                        if ($daySummary) {
                            echo '<div class="cal-badges">';
                            if (!empty($daySummary['available']) && $daySummary['available'] > 0) {
                                echo '<span class="cal-badge badge-disp">' . $daySummary['available'] . ' disp.</span>';
                            }
                            if (!empty($daySummary['booked']) && $daySummary['booked'] > 0) {
                                echo '<span class="cal-badge badge-res">' . $daySummary['booked'] . ' res.</span>';
                            }
                            if (!empty($daySummary['blocked']) && $daySummary['blocked'] > 0) {
                                echo '<span class="cal-badge badge-blk">' . $daySummary['blocked'] . ' blq.</span>';
                            }
                            echo '</div>';
                        }
                        echo '</div>';
                    }
                    // Next month days to fill grid
                    $totalCells = ($firstDayOfWeek - 1) + $daysInMonth;
                    $remaining = 42 - $totalCells; // Always 6 rows for consistency
                    for ($i = 1; $i <= $remaining; $i++) {
                        echo '<div class="cal-day other-month">' . $i . '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-overlay" id="slotsModal">
        <div class="modal" style="max-width: 600px;">
            <div class="modal-header">
                <h3><i class="fa-regular fa-clock text-primary"></i> Turnos del <span id="slotsModalDate"></span></h3>
                <button type="button" class="modal-close" onclick="closeSlotsModal()">&times;</button>
            </div>
            <div class="slots-modal-toolbar" style="display: flex; justify-content: space-between; align-items: center; padding: 0.65rem 1.25rem; background-color: #f8fafc; border-bottom: 1px solid var(--border-light); font-size: 0.85rem; flex-wrap: wrap; gap: 0.5rem;">
                <div class="text-muted d-flex align-items-center gap-1">
                    <i class="fa-solid fa-layer-group text-primary"></i>
                    <span id="slotsModalSummaryText">Total: 0 turnos</span>
                </div>
                <div>
                    <form id="formDoctorDeleteAll" action="<?= $baseUrl ?>/doctor/schedule/delete-all" method="POST" onsubmit="return confirmDeleteAll();" style="margin: 0;">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="date" id="deleteAllSlotsDate" value="">
                        <?php if (isset($doctorId) && $doctorId): ?>
                            <input type="hidden" name="doctor_id" value="<?= (int)$doctorId ?>">
                        <?php endif; ?>
                        <button type="submit" class="btn btn-sm btn-danger" id="btnDoctorDeleteAll" style="display: inline-flex; align-items: center; gap: 0.35rem; font-weight: 600; padding: 0.35rem 0.75rem; border-radius: 4px; box-shadow: 0 1px 2px rgba(239,68,68,0.2);">
                            <i class="fa-solid fa-trash-can"></i> <span id="btnDoctorDeleteAllText">Eliminar todos los turnos</span>
                        </button>
                    </form>
                </div>
            </div>
            <div class="modal-body" style="padding: 0;">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Hora</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="slotsModalBody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <style>
        .custom-calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            border-top: 1px solid var(--border-light);
            border-left: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            overflow: hidden;
        }
        .cal-header {
            background: var(--surface-2);
            padding: .75rem;
            text-align: center;
            font-size: .75rem;
            font-weight: 600;
            color: var(--text-muted);
            border-right: 1px solid var(--border-light);
            border-bottom: 1px solid var(--border-light);
        }
        .cal-day {
            min-height: 100px;
            background: var(--surface);
            border-right: 1px solid var(--border-light);
            border-bottom: 1px solid var(--border-light);
            padding: .5rem;
            display: flex;
            flex-direction: column;
            gap: .25rem;
            cursor: pointer;
            transition: background .2s;
        }
        .cal-day:hover {
            background: #f8fafc;
        }
        .cal-day.weekend {
            color: #ef4444;
        }
        .cal-day.other-month {
            background: #f1f5f9;
            color: #cbd5e1;
            cursor: default;
        }
        .cal-day.other-month:hover {
            background: #f1f5f9;
        }
        .cal-date {
            font-size: .9rem;
            font-weight: 500;
            margin-bottom: .25rem;
        }
        .cal-date.today {
            background: var(--primary);
            color: #fff;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        .cal-badges {
            display: flex;
            flex-wrap: wrap;
            gap: .25rem;
        }
        .cal-badge {
            font-size: .65rem;
            padding: .15rem .35rem;
            border-radius: 4px;
            font-weight: 600;
        }
        .badge-disp { background: #d1fae5; color: #065f46; }
        .badge-res { background: #fef3c7; color: #92400e; }
        .badge-blk { background: #fee2e2; color: #991b1b; }
    </style>
    <script>
        const monthSlots = <?= json_encode($monthSlots) ?>;
        function showSlots(dateStr) {
            const slots = monthSlots.filter(s => s.available_date === dateStr);
            if (slots.length === 0) {
                alert('No hay turnos programados en esta fecha.');
                return;
            }
            // Format date for title
            const dateObj = new Date(dateStr + 'T00:00:00');
            const formattedDate = dateObj.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
            document.getElementById('slotsModalDate').textContent = formattedDate;

            // Update delete-all form date
            const inputDateAll = document.getElementById('deleteAllSlotsDate');
            if (inputDateAll) inputDateAll.value = dateStr;

            const availableSlots = slots.filter(s => s.status === 'available');
            const bookedSlots = slots.filter(s => s.status === 'booked');
            const blockedSlots = slots.filter(s => s.status === 'blocked');
            const deletableCount = availableSlots.length + blockedSlots.length;

            const summaryText = document.getElementById('slotsModalSummaryText');
            if (summaryText) {
                summaryText.innerHTML = `<strong>${slots.length}</strong> turno(s) &bull; <span class="text-success font-weight-bold">${availableSlots.length} disp.</span>${bookedSlots.length > 0 ? ` &bull; <span class="text-warning font-weight-bold">${bookedSlots.length} res.</span>` : ''}${blockedSlots.length > 0 ? ` &bull; <span class="text-danger font-weight-bold">${blockedSlots.length} blq.</span>` : ''}`;
            }

            const btnDeleteAll = document.getElementById('btnDoctorDeleteAll');
            const btnDeleteAllText = document.getElementById('btnDoctorDeleteAllText');
            if (btnDeleteAll) {
                if (deletableCount === 0) {
                    btnDeleteAll.disabled = true;
                    btnDeleteAll.style.opacity = '0.6';
                    btnDeleteAll.style.cursor = 'not-allowed';
                    btnDeleteAll.title = 'No hay turnos disponibles para eliminar en esta fecha (todos están reservados).';
                    if (btnDeleteAllText) btnDeleteAllText.textContent = 'Sin turnos para eliminar';
                } else {
                    btnDeleteAll.disabled = false;
                    btnDeleteAll.style.opacity = '1';
                    btnDeleteAll.style.cursor = 'pointer';
                    btnDeleteAll.title = `Eliminar todos los turnos disponibles (${deletableCount}) de esta fecha`;
                    if (btnDeleteAllText) btnDeleteAllText.textContent = `Eliminar todos los turnos (${deletableCount})`;
                }
            }

            const tbody = document.getElementById('slotsModalBody');
            tbody.innerHTML = '';
            slots.forEach(slot => {
                const tr = document.createElement('tr');
                // Hora
                const tdTime = document.createElement('td');
                const startTime = slot.start_time.substring(0, 5);
                const endTime = slot.end_time.substring(0, 5);
                tdTime.innerHTML = `<strong>${startTime}</strong> - ${endTime}`;
                // Estado
                const tdStatus = document.createElement('td');
                if (slot.status === 'available') {
                    tdStatus.innerHTML = '<span class="badge badge-success">Disponible</span>';
                } else if (slot.status === 'booked') {
                    tdStatus.innerHTML = '<span class="badge badge-warning">Reservado</span>';
                } else {
                    tdStatus.innerHTML = '<span class="badge badge-danger">Bloqueado</span>';
                }
                // Acciones
                const tdAction = document.createElement('td');
                if (slot.status === 'available') {
                    tdAction.innerHTML = `
                        <form action="<?= $baseUrl ?>/doctor/schedule/delete/${slot.id}" method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar este turno?');">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="date" value="${dateStr}">
                            <?php if (isset($doctorId) && $doctorId): ?>
                                <input type="hidden" name="doctor_id" value="<?= (int)$doctorId ?>">
                            <?php endif; ?>
                            <button type="submit" class="btn btn-sm btn-danger" title="Eliminar este turno individual"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    `;
                } else {
                    tdAction.innerHTML = '<button class="btn btn-sm btn-secondary" disabled title="Turno reservado o no disponible"><i class="fa-solid fa-lock"></i></button>';
                }
                tr.appendChild(tdTime);
                tr.appendChild(tdStatus);
                tr.appendChild(tdAction);
                tbody.appendChild(tr);
            });
            document.getElementById('slotsModal').classList.add('active');
        }
        function closeSlotsModal() {
            document.getElementById('slotsModal').classList.remove('active');
        }
        function confirmDeleteAll() {
            const formattedDate = document.getElementById('slotsModalDate').textContent;
            return confirm(`¿Está seguro de que desea eliminar TODOS los turnos disponibles de la fecha ${formattedDate}?\n\nNota: Los turnos que ya tengan citas reservadas por pacientes no serán eliminados.`);
        }
        <?php if (!empty($_GET['date'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showSlots(<?= json_encode($_GET['date']) ?>);
        });
        <?php endif; ?>
    </script>
<?php endif; ?>
