<div class="schedule-tabs">
    <button class="schedule-tab active" data-tab="viewer" id="tab-viewer">
        <i class="fa-solid fa-calendar-week"></i>
        <span>Ver / Gestionar Turnos</span>
    </button>
    <?php if (\App\Helpers\Auth::hasPermission('schedules_create')): ?>
    <button class="schedule-tab" data-tab="generator" id="tab-generator">
        <i class="fa-solid fa-calendar-plus"></i>
        <span>Generador de Turnos</span>
    </button>
    <?php endif; ?>
</div>
<div class="schedule-panel active" id="panel-viewer">
    <div class="card animate-in">
        <div class="card-body">
            <div class="schedule-doctor-selector">
                <div class="form-group mb-0" style="flex: 1; min-width: 220px;">
                    <label class="form-label"><i class="fa-solid fa-user-doctor"></i> Médico Profesional</label>
                    <select id="calendarDoctorSelect" class="form-control">
                        <option value="">Seleccione un médico...</option>
                        <?php foreach($doctors as $doc): ?>
                            <option value="<?= $doc['id'] ?>" <?= $selectedDoctorId == $doc['id'] ? 'selected' : '' ?>><?= htmlspecialchars($doc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div id="calendarContainer" style="display: none;">
                <div class="cal-nav">
                    <button class="btn btn-secondary btn-sm" id="calPrev">
                        <i class="fa-solid fa-chevron-left"></i>
                    </button>
                    <h4 id="calTitle" class="cal-title"></h4>
                    <button class="btn btn-secondary btn-sm" id="calNext">
                        <i class="fa-solid fa-chevron-right"></i>
                    </button>
                    <button class="btn btn-secondary btn-sm" id="calToday" style="margin-left: 0.5rem;">
                        <i class="fa-solid fa-crosshairs"></i> Hoy
                    </button>
                </div>
                <div class="cal-legend">
                    <span class="cal-legend-item"><span class="cal-legend-dot" style="background: var(--accent);"></span> Disponible</span>
                    <span class="cal-legend-item"><span class="cal-legend-dot" style="background: var(--warning);"></span> Reservado</span>
                    <span class="cal-legend-item"><span class="cal-legend-dot" style="background: var(--danger);"></span> Bloqueado</span>
                </div>
                <div class="cal-grid-wrapper">
                    <div class="cal-weekdays">
                        <div>Lun</div><div>Mar</div><div>Mié</div><div>Jue</div><div>Vie</div><div>Sáb</div><div>Dom</div>
                    </div>
                    <div class="cal-grid" id="calGrid"></div>
                </div>
                <div id="dayDetailPanel" class="day-detail-panel" style="display: none;">
                    <div class="day-detail-header">
                        <h4 id="dayDetailTitle"><i class="fa-solid fa-calendar-day"></i> <span></span></h4>
                        <button class="btn btn-sm btn-secondary" id="closeDayDetail"><i class="fa-solid fa-times"></i></button>
                    </div>
                    <div id="dayDetailContent" class="day-detail-content"></div>
                </div>
            </div>
            <div id="calendarEmpty" class="schedule-empty-state">
                <div class="empty-icon">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
                <h4>Selecciona un Médico</h4>
                <p>Elige un médico profesional del listado para ver su calendario de turnos disponibles.</p>
            </div>
        </div>
    </div>
</div>
<?php if (\App\Helpers\Auth::hasPermission('schedules_create')): ?>
<div class="schedule-panel" id="panel-generator">
    <div class="card animate-in">
        <div class="card-body">
            <div class="alert alert-info mb-3" style="font-size: 0.9rem;">
                <i class="fa-solid fa-circle-info"></i> Genera turnos de Lunes a Viernes en el rango seleccionado. No se duplican los existentes.
            </div>
            <form action="<?= $baseUrl ?>/admin/schedules/generate" method="POST">
                <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                <div class="form-group">
                    <label class="form-label">Médico Profesional *</label>
                    <select name="doctor_id" class="form-control" required>
                        <option value="">Seleccione...</option>
                        <?php foreach($doctors as $doc): ?>
                            <option value="<?= $doc['id'] ?>"><?= htmlspecialchars($doc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Fecha de Inicio *</label>
                        <input type="date" name="start_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Fecha de Fin *</label>
                        <input type="date" name="end_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Hora Inicio *</label>
                        <input type="time" name="start_time" class="form-control" required value="08:00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Hora Fin *</label>
                        <input type="time" name="end_time" class="form-control" required value="17:00">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Duración por Turno *</label>
                    <select name="interval" class="form-control" required>
                        <option value="15">15 Minutos</option>
                        <option value="20">20 Minutos</option>
                        <option value="30" selected>30 Minutos</option>
                        <option value="45">45 Minutos</option>
                        <option value="60">1 Hora</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-block mt-2">
                    <i class="fa-solid fa-calendar-plus"></i> Generar Disponibilidad
                </button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<style>
/* ---- Schedule Tabs ---- */
.schedule-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    background: var(--surface);
    padding: 0.4rem;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-light);
    box-shadow: var(--shadow-sm);
}
.schedule-tab {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    padding: 0.85rem 1.25rem;
    border: none;
    background: transparent;
    border-radius: var(--radius);
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-muted);
    cursor: pointer;
    transition: var(--transition);
    position: relative;
}
.schedule-tab:hover {
    color: var(--primary);
    background: var(--primary-50);
}
.schedule-tab.active {
    background: var(--gradient-primary);
    color: #fff;
    box-shadow: 0 4px 14px rgba(14,165,233,.3);
}
.schedule-tab i {
    font-size: 1.1rem;
}
/* ---- Tab Panels ---- */
.schedule-panel {
    display: none;
    animation: slideUp 0.35s ease;
}
.schedule-panel.active {
    display: block;
}
/* ---- Doctor Selector ---- */
.schedule-doctor-selector {
    display: flex;
    gap: 1rem;
    align-items: flex-end;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
    padding-bottom: 1.25rem;
    border-bottom: 1px solid var(--border-light);
}
/* ---- Calendar Navigation ---- */
.cal-nav {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}
.cal-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--text);
    min-width: 180px;
    text-align: center;
    text-transform: capitalize;
}
/* ---- Calendar Legend ---- */
.cal-legend {
    display: flex;
    gap: 1.25rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}
.cal-legend-item {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--text-secondary);
}
.cal-legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}
/* ---- Calendar Grid ---- */
.cal-grid-wrapper {
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
}
.cal-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    background: var(--surface-3);
    border-bottom: 1px solid var(--border);
}
.cal-weekdays > div {
    padding: 0.6rem;
    text-align: center;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.cal-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
}
.cal-cell {
    min-height: 85px;
    padding: 0.5rem;
    border-right: 1px solid var(--border-light);
    border-bottom: 1px solid var(--border-light);
    transition: var(--transition);
    cursor: default;
    position: relative;
}
.cal-cell:nth-child(7n) {
    border-right: none;
}
.cal-cell:hover {
    background: var(--surface-2);
}
.cal-cell.empty {
    background: var(--surface-2);
    cursor: default;
}
.cal-cell.past-date {
    background: var(--surface-2);
    opacity: 0.6;
    cursor: not-allowed;
}
.cal-cell.past-date:hover {
    background: var(--surface-2);
}
.cal-cell.today {
    background: var(--primary-50);
}
.cal-cell.today .cal-day-number {
    background: var(--primary);
    color: #fff;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.cal-cell.has-slots {
    cursor: pointer;
}
.cal-cell.has-slots:hover {
    background: var(--primary-50);
    transform: scale(1.02);
    z-index: 2;
    box-shadow: var(--shadow-md);
}
.cal-cell.selected {
    background: var(--primary-100);
    box-shadow: inset 0 0 0 2px var(--primary);
}
.cal-day-number {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 0.35rem;
}
.cal-cell.empty .cal-day-number,
.cal-cell.past-date .cal-day-number {
    color: var(--text-muted);
    opacity: 0.4;
}
.cal-cell.weekend .cal-day-number {
    color: var(--danger);
    opacity: 0.6;
}
.cal-slot-indicators {
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
}
.cal-slot-pill {
    font-size: 0.6rem;
    padding: 1px 5px;
    border-radius: 50px;
    font-weight: 700;
    line-height: 1.5;
}
.cal-slot-pill.available { background: #d1fae5; color: #065f46; }
.cal-slot-pill.booked { background: #fef3c7; color: #92400e; }
.cal-slot-pill.blocked { background: #fee2e2; color: #991b1b; }
/* ---- Day Detail Panel ---- */
.day-detail-panel {
    margin-top: 1.25rem;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    animation: slideUp 0.3s ease;
}
.day-detail-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    background: var(--surface-3);
    border-bottom: 1px solid var(--border);
}
.day-detail-header h4 {
    margin: 0;
    font-size: 1rem;
    color: var(--primary-dark);
}
.day-detail-content {
    padding: 1.25rem;
    max-height: 500px;
    overflow-y: auto;
}
.slot-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.7rem 1rem;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border-light);
    margin-bottom: 0.5rem;
    transition: var(--transition);
    background: var(--surface);
}
.slot-card:hover {
    box-shadow: var(--shadow-sm);
    border-color: var(--border);
}
.slot-time {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--text);
    min-width: 120px;
}
.slot-time i {
    color: var(--primary);
    margin-right: 0.3rem;
}
.slot-actions {
    display: flex;
    gap: 0.4rem;
    align-items: center;
}
.slot-actions form { display: inline; }
/* ---- Empty State ---- */
.schedule-empty-state {
    text-align: center;
    padding: 3rem 1.5rem;
}
.schedule-empty-state .empty-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: var(--primary-50);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.25rem;
}
.schedule-empty-state .empty-icon i {
    font-size: 2rem;
    color: var(--primary);
}
.schedule-empty-state h4 {
    color: var(--text);
    margin-bottom: 0.5rem;
}
.schedule-empty-state p {
    color: var(--text-muted);
    font-size: 0.9rem;
    max-width: 400px;
    margin: 0 auto;
}
/* ---- Loading Spinner ---- */
.cal-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    gap: 0.75rem;
    color: var(--text-muted);
    font-size: 0.9rem;
}
.cal-loading i {
    animation: spin 1s linear infinite;
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
/* ---- Responsive ---- */
@media (max-width: 768px) {
    .schedule-tabs {
        flex-direction: column;
    }
    .cal-cell {
        min-height: 60px;
        padding: 0.3rem;
    }
    .cal-day-number { font-size: 0.75rem; }
    .cal-slot-pill { font-size: 0.55rem; padding: 0px 3px; }
    .slot-card { flex-direction: column; align-items: flex-start; gap: 0.5rem; }
    .cal-title { min-width: auto; font-size: 1rem; }
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const BASE = typeof BASE_URL !== 'undefined' ? BASE_URL : '';
    const CSRF = '<?= \App\Helpers\Session::generateCsrf() ?>';
    const MONTHS_ES = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    const DAYS_ES = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
    // ---- Tab Switching ----
    document.querySelectorAll('.schedule-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.schedule-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.schedule-panel').forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('panel-' + this.dataset.tab).classList.add('active');
        });
    });
    // ---- Calendar State ----
    let currentYear = new Date().getFullYear();
    let currentMonth = new Date().getMonth(); // 0-indexed
    let selectedDoctorId = document.getElementById('calendarDoctorSelect').value;
    let monthData = {};
    let selectedDate = null;
    const calGrid = document.getElementById('calGrid');
    const calTitle = document.getElementById('calTitle');
    const calContainer = document.getElementById('calendarContainer');
    const calEmpty = document.getElementById('calendarEmpty');
    const dayPanel = document.getElementById('dayDetailPanel');
    const dayContent = document.getElementById('dayDetailContent');
    const dayTitle = document.getElementById('dayDetailTitle').querySelector('span');
    // ---- Doctor select ----
    document.getElementById('calendarDoctorSelect').addEventListener('change', function() {
        selectedDoctorId = this.value;
        selectedDate = null;
        dayPanel.style.display = 'none';
        if (selectedDoctorId) {
            calContainer.style.display = 'block';
            calEmpty.style.display = 'none';
            loadMonth();
        } else {
            calContainer.style.display = 'none';
            calEmpty.style.display = 'block';
        }
    });
    // ---- Navigation ----
    document.getElementById('calPrev').addEventListener('click', () => {
        currentMonth--;
        if (currentMonth < 0) { currentMonth = 11; currentYear--; }
        selectedDate = null; dayPanel.style.display = 'none';
        loadMonth();
    });
    document.getElementById('calNext').addEventListener('click', () => {
        currentMonth++;
        if (currentMonth > 11) { currentMonth = 0; currentYear++; }
        selectedDate = null; dayPanel.style.display = 'none';
        loadMonth();
    });
    document.getElementById('calToday').addEventListener('click', () => {
        currentYear = new Date().getFullYear();
        currentMonth = new Date().getMonth();
        selectedDate = null; dayPanel.style.display = 'none';
        loadMonth();
    });
    document.getElementById('closeDayDetail').addEventListener('click', () => {
        dayPanel.style.display = 'none';
        selectedDate = null;
        document.querySelectorAll('.cal-cell.selected').forEach(c => c.classList.remove('selected'));
    });
    // ---- Load Month Data ----
    async function loadMonth() {
        calTitle.textContent = MONTHS_ES[currentMonth] + ' ' + currentYear;
        calGrid.innerHTML = '<div class="cal-loading" style="grid-column: 1 / -1;"><i class="fa-solid fa-spinner"></i> Cargando...</div>';
        try {
            const res = await fetch(`${BASE}/api/schedule-month/${selectedDoctorId}?year=${currentYear}&month=${currentMonth + 1}`);
            const json = await res.json();
            if (json.success) {
                monthData = json.data;
            } else {
                monthData = {};
            }
        } catch(e) {
            monthData = {};
        }
        renderCalendar();
    }
    // ---- Render Calendar ----
    function renderCalendar() {
        calGrid.innerHTML = '';
        const firstDay = new Date(currentYear, currentMonth, 1);
        const lastDay = new Date(currentYear, currentMonth + 1, 0);
        const daysInMonth = lastDay.getDate();
        // Monday=0 based (ISO week start)
        let startDow = firstDay.getDay(); // 0=Sun
        startDow = startDow === 0 ? 6 : startDow - 1; // Convert: Mon=0, Sun=6
        const today = new Date();
        const todayStr = today.getFullYear() + '-' + String(today.getMonth()+1).padStart(2,'0') + '-' + String(today.getDate()).padStart(2,'0');
        // Empty cells before first day
        for (let i = 0; i < startDow; i++) {
            const cell = document.createElement('div');
            cell.className = 'cal-cell empty';
            calGrid.appendChild(cell);
        }
        // Day cells
        for (let d = 1; d <= daysInMonth; d++) {
            const dateStr = currentYear + '-' + String(currentMonth+1).padStart(2,'0') + '-' + String(d).padStart(2,'0');
            const dayOfWeek = new Date(currentYear, currentMonth, d).getDay();
            const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;
            const isPast = dateStr < todayStr;
            const cell = document.createElement('div');
            cell.className = 'cal-cell';
            if (isPast) cell.classList.add('past-date');
            if (isWeekend) cell.classList.add('weekend');
            if (dateStr === todayStr) cell.classList.add('today');
            if (dateStr === selectedDate && !isPast) cell.classList.add('selected');
            const dayNum = document.createElement('div');
            dayNum.className = 'cal-day-number';
            dayNum.textContent = d;
            cell.appendChild(dayNum);
            // Slot indicators (only if not past date)
            if (monthData[dateStr] && !isPast) {
                const info = monthData[dateStr];
                cell.classList.add('has-slots');
                const indicators = document.createElement('div');
                indicators.className = 'cal-slot-indicators';
                if (info.available > 0) {
                    const pill = document.createElement('span');
                    pill.className = 'cal-slot-pill available';
                    pill.textContent = info.available + ' disp.';
                    indicators.appendChild(pill);
                }
                if (info.booked > 0) {
                    const pill = document.createElement('span');
                    pill.className = 'cal-slot-pill booked';
                    pill.textContent = info.booked + ' res.';
                    indicators.appendChild(pill);
                }
                if (info.blocked > 0) {
                    const pill = document.createElement('span');
                    pill.className = 'cal-slot-pill blocked';
                    pill.textContent = info.blocked + ' bloq.';
                    indicators.appendChild(pill);
                }
                cell.appendChild(indicators);
                // Click to show day detail
                cell.addEventListener('click', () => {
                    document.querySelectorAll('.cal-cell.selected').forEach(c => c.classList.remove('selected'));
                    cell.classList.add('selected');
                    selectedDate = dateStr;
                    loadDayDetail(dateStr);
                });
            }
            calGrid.appendChild(cell);
        }
        // Fill remaining cells
        const totalCells = startDow + daysInMonth;
        const remainder = totalCells % 7;
        if (remainder > 0) {
            for (let i = 0; i < 7 - remainder; i++) {
                const cell = document.createElement('div');
                cell.className = 'cal-cell empty';
                calGrid.appendChild(cell);
            }
        }
    }
    // ---- Load Day Detail ----
    async function loadDayDetail(dateStr) {
        const dateObj = new Date(dateStr + 'T12:00:00');
        dayTitle.textContent = DAYS_ES[dateObj.getDay()] + ' ' + dateObj.getDate() + ' de ' + MONTHS_ES[dateObj.getMonth()] + ', ' + dateObj.getFullYear();
        dayPanel.style.display = 'block';
        dayContent.innerHTML = '<div class="cal-loading"><i class="fa-solid fa-spinner"></i> Cargando turnos...</div>';
        try {
            const res = await fetch(`${BASE}/api/schedule-day/${selectedDoctorId}/${dateStr}`);
            const json = await res.json();
            if (json.success && json.data.length > 0) {
                renderDaySlots(json.data, dateStr);
            } else {
                dayContent.innerHTML = '<div class="alert alert-warning"><i class="fa-solid fa-info-circle"></i> No hay turnos para este día.</div>';
            }
        } catch(e) {
            dayContent.innerHTML = '<div class="alert alert-error"><i class="fa-solid fa-exclamation-triangle"></i> Error al cargar los turnos.</div>';
        }
        // Scroll into view
        dayPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    // ---- Render Day Slots ----
    function renderDaySlots(slots, dateStr) {
        const availableSlots = slots.filter(s => s.status === 'available');
        const bookedSlots = slots.filter(s => s.status === 'booked');
        const blockedSlots = slots.filter(s => s.status === 'blocked');
        const deletableCount = availableSlots.length + blockedSlots.length;

        let html = `
            <div class="day-slots-toolbar" style="display: flex; justify-content: space-between; align-items: center; padding: 0.65rem 1rem; background: var(--surface-2, #f8fafc); border: 1px solid var(--border-light, #e2e8f0); border-radius: var(--radius-sm, 6px); margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
                <div class="text-muted small">
                    <i class="fa-solid fa-layer-group text-primary me-1"></i>
                    <strong>${slots.length}</strong> turno(s) en total &bull; 
                    <span class="text-success fw-bold">${availableSlots.length} disp.</span>
                    ${bookedSlots.length > 0 ? ` &bull; <span class="text-warning fw-bold">${bookedSlots.length} res.</span>` : ''}
                    ${blockedSlots.length > 0 ? ` &bull; <span class="text-danger fw-bold">${blockedSlots.length} blq.</span>` : ''}
                </div>
                <div>
                    <form action="${BASE}/admin/schedules/delete-all" method="POST" style="margin: 0;" onsubmit="return confirm('¿Está seguro de que desea eliminar todos los turnos disponibles de esta fecha (${dateStr}) para este médico?\\n\\nLos turnos ya reservados por pacientes se mantendrán protegidos.');">
                        <input type="hidden" name="csrf_token" value="${CSRF}">
                        <input type="hidden" name="doctor_id" value="${selectedDoctorId}">
                        <input type="hidden" name="date" value="${dateStr}">
                        <button type="submit" class="btn btn-sm btn-danger d-inline-flex align-items-center gap-1" ${deletableCount === 0 ? 'disabled style="opacity:0.6; cursor:not-allowed;" title="No hay turnos disponibles para eliminar"' : `title="Eliminar todos los ${deletableCount} turnos disponibles de este día"`}>
                            <i class="fa-solid fa-trash-can"></i> ${deletableCount === 0 ? 'Sin turnos para eliminar' : `Eliminar todos los turnos (${deletableCount})`}
                        </button>
                    </form>
                </div>
            </div>
        `;
        slots.forEach(slot => {
            const startTime = slot.start_time.substring(0, 5);
            const endTime = slot.end_time.substring(0, 5);
            let statusBadge = '';
            let actions = '';
            switch(slot.status) {
                case 'available':
                    statusBadge = '<span class="badge badge-success">Disponible</span>';
                    actions = `
                        <form action="${BASE}/admin/schedules/block/${slot.id}" method="POST" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="${CSRF}">
                            <input type="hidden" name="doctor_id" value="${selectedDoctorId}">
                            <input type="hidden" name="date" value="${dateStr}">
                            <button type="submit" class="btn btn-sm btn-warning" title="Bloquear"><i class="fa-solid fa-lock"></i></button>
                        </form>
                        <form action="${BASE}/admin/schedules/delete/${slot.id}" method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este turno?')">
                            <input type="hidden" name="csrf_token" value="${CSRF}">
                            <input type="hidden" name="doctor_id" value="${selectedDoctorId}">
                            <input type="hidden" name="date" value="${dateStr}">
                            <button type="submit" class="btn btn-sm btn-danger" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                        </form>`;
                    break;
                case 'booked':
                    statusBadge = '<span class="badge badge-warning">Reservado</span>';
                    if (slot.notes) {
                        actions = `<span class="text-muted" style="font-size: 0.8rem;">${slot.notes}</span>`;
                    }
                    break;
                case 'blocked':
                    statusBadge = '<span class="badge badge-danger">Bloqueado</span>';
                    actions = `
                        <form action="${BASE}/admin/schedules/unblock/${slot.id}" method="POST" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="${CSRF}">
                            <input type="hidden" name="doctor_id" value="${selectedDoctorId}">
                            <input type="hidden" name="date" value="${dateStr}">
                            <button type="submit" class="btn btn-sm btn-success" title="Desbloquear"><i class="fa-solid fa-lock-open"></i></button>
                        </form>`;
                    break;
                default:
                    statusBadge = `<span class="badge badge-secondary">${slot.status}</span>`;
            }
            html += `
                <div class="slot-card">
                    <div class="slot-time"><i class="fa-regular fa-clock"></i> ${startTime} – ${endTime}</div>
                    ${statusBadge}
                    <div class="slot-actions">${actions}</div>
                </div>`;
        });
        dayContent.innerHTML = html;
    }
    // ---- Initial load if doctor pre-selected ----
    if (selectedDoctorId) {
        calContainer.style.display = 'block';
        calEmpty.style.display = 'none';
        loadMonth();
    }
});
</script>
