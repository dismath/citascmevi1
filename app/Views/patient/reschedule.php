<div class="card mb-4">
    <div class="card-header d-flex justify-between align-center">
        <h3><i class="fa-solid fa-calendar-days text-primary"></i> Reagendar Cita #<?= $appointment['id'] ?></h3>
        <a href="<?= $baseUrl ?>/patient/dashboard" class="btn btn-sm btn-secondary">Cancelar y Volver</a>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="fa-solid fa-circle-info"></i> Estás reagendando tu cita de <strong><?= htmlspecialchars($appointment['specialty_name'] ?? 'Especialidad') ?></strong>.
            Selecciona un nuevo día en el calendario para ver los médicos y turnos disponibles.
        </div>
    </div>
</div>
<div class="card p-0">
    <div class="card-header d-flex justify-between align-center p-3" style="background: var(--primary); color: white;">
        <button class="btn btn-sm" style="background: rgba(255,255,255,0.2); color: white;" onclick="changeMonth(-1)"><i class="fa-solid fa-chevron-left"></i></button>
        <h2 id="calendar-month-year" style="margin: 0; font-size: 1.25rem;">Cargando...</h2>
        <button class="btn btn-sm" style="background: rgba(255,255,255,0.2); color: white;" onclick="changeMonth(1)"><i class="fa-solid fa-chevron-right"></i></button>
    </div>
    <div class="card-body p-0">
        <div class="calendar-grid" id="calendar-grid" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background: #ddd;">
            <div class="calendar-day-header" style="background: #1e3a8a; color: white; padding: 10px; font-weight: bold; text-align: center;"><span class="full-day">Domingo</span><span class="short-day">Dom</span></div>
            <div class="calendar-day-header" style="background: #1e3a8a; color: white; padding: 10px; font-weight: bold; text-align: center;"><span class="full-day">Lunes</span><span class="short-day">Lun</span></div>
            <div class="calendar-day-header" style="background: #1e3a8a; color: white; padding: 10px; font-weight: bold; text-align: center;"><span class="full-day">Martes</span><span class="short-day">Mar</span></div>
            <div class="calendar-day-header" style="background: #1e3a8a; color: white; padding: 10px; font-weight: bold; text-align: center;"><span class="full-day">Miércoles</span><span class="short-day">Mié</span></div>
            <div class="calendar-day-header" style="background: #1e3a8a; color: white; padding: 10px; font-weight: bold; text-align: center;"><span class="full-day">Jueves</span><span class="short-day">Jue</span></div>
            <div class="calendar-day-header" style="background: #1e3a8a; color: white; padding: 10px; font-weight: bold; text-align: center;"><span class="full-day">Viernes</span><span class="short-day">Vie</span></div>
            <div class="calendar-day-header" style="background: #1e3a8a; color: white; padding: 10px; font-weight: bold; text-align: center;"><span class="full-day">Sábado</span><span class="short-day">Sáb</span></div>
        </div>
    </div>
</div>
<div id="bookingModal" class="modal" style="display: none; position: fixed; z-index: 900; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5);">
    <div class="modal-content card" style="margin: 5% auto; width: 90%; max-width: 800px; padding: 0;">
        <div class="modal-header" style="background: #1e3a8a; color: white; padding: 15px; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0;"><i class="fa-regular fa-clock"></i> Turnos disponibles</h3>
            <span class="close" onclick="closeModal()" style="cursor: pointer; font-size: 1.5rem;">&times;</span>
        </div>
        <div class="modal-body p-4">
            <div class="mb-4 text-center">
                <h4 style="margin-bottom: 5px; color: var(--primary);">Fecha Seleccionada: <span id="modal-date-display" style="font-weight: 700;"></span></h4>
            </div>
            <div class="table-wrapper">
                <table class="table" style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                    <thead>
                        <tr>
                            <th style="background: var(--surface-2); padding: 10px; text-align: left; width: 40%;">Médico</th>
                            <th style="background: var(--surface-2); padding: 10px; text-align: left; width: 60%;">Horarios</th>
                        </tr>
                    </thead>
                    <tbody id="doctors-list">
                    </tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer p-3 text-right" style="border-top: 1px solid var(--border-light); background: var(--surface);">
            <button class="btn btn-secondary" onclick="closeModal()"><i class="fa-solid fa-xmark"></i> Cancelar</button>
        </div>
    </div>
</div>
<form id="rescheduleForm" action="<?= $baseUrl ?>/patient/appointments/update-schedule/<?= $appointment['id'] ?>" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    <input type="hidden" name="doctor_id" id="form_doctor_id">
    <input type="hidden" name="availability_id" id="form_availability_id">
    <input type="hidden" name="date" id="form_date">
</form>
<script>
    const specialtyId = <?= (int)$appointment['specialty_id'] ?>;
    let currentDate = new Date();
    let availableDates = {};
    function fetchDates() {
        fetch(`<?= $baseUrl ?>/api/specialty-dates/${specialtyId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    data.data.forEach(d => {
                        availableDates[d.available_date] = d.slot_count;
                    });
                    renderCalendar();
                }
            })
            .catch(err => console.error(err));
    }
    function renderCalendar() {
        const grid = document.getElementById('calendar-grid');
        const monthYear = document.getElementById('calendar-month-year');
        while (grid.children.length > 7) {
            grid.removeChild(grid.lastChild);
        }
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        const monthNames = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
        monthYear.textContent = monthNames[month] + " " + year;
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        for (let i = 0; i < firstDay; i++) {
            const emptyCell = document.createElement('div');
            emptyCell.style.background = 'var(--surface-2)';
            emptyCell.style.padding = '40px 10px';
            grid.appendChild(emptyCell);
        }
        for (let i = 1; i <= daysInMonth; i++) {
            const cell = document.createElement('div');
            const dateStr = `${year}-${String(month+1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
            const slotCount = availableDates[dateStr] || 0;
            cell.style.background = slotCount > 0 ? '#ecfdf5' : '#fffbeb';
            cell.style.padding = '10px';
            cell.style.position = 'relative';
            cell.style.height = '100px';
            cell.style.display = 'flex';
            cell.style.flexDirection = 'column';
            cell.style.alignItems = 'center';
            cell.style.justifyContent = 'center';
            cell.style.transition = 'all 0.2s';
            if(slotCount > 0) {
                cell.style.cursor = 'pointer';
                cell.onclick = () => openModal(dateStr);
                cell.onmouseover = () => cell.style.background = '#d1fae5';
                cell.onmouseout = () => cell.style.background = '#ecfdf5';
            }
            const dayNum = document.createElement('div');
            dayNum.textContent = i;
            dayNum.style.position = 'absolute';
            dayNum.style.top = '5px';
            dayNum.style.right = '5px';
            dayNum.style.background = slotCount > 0 ? 'var(--primary)' : '#9ca3af';
            dayNum.style.color = 'white';
            dayNum.style.borderRadius = '50%';
            dayNum.style.width = '24px';
            dayNum.style.height = '24px';
            dayNum.style.display = 'flex';
            dayNum.style.alignItems = 'center';
            dayNum.style.justifyContent = 'center';
            dayNum.style.fontSize = '0.8rem';
            const statusText = document.createElement('div');
            statusText.className = 'status-text';
            statusText.style.fontSize = '0.85rem';
            statusText.style.marginTop = '15px';
            statusText.style.textAlign = 'center';
            statusText.style.fontWeight = '600';
            if (slotCount > 0) {
                statusText.innerHTML = `<span style="color: #059669;">${slotCount} Turnos</span>`;
            } else {
                statusText.textContent = 'Agotado';
                statusText.style.color = '#9ca3af';
            }
            cell.appendChild(dayNum);
            cell.appendChild(statusText);
            grid.appendChild(cell);
        }
    }
    function changeMonth(offset) {
        currentDate.setMonth(currentDate.getMonth() + offset);
        renderCalendar();
    }
    function openModal(dateStr) {
        const dateObj = new Date(dateStr + 'T00:00:00');
        document.getElementById('modal-date-display').textContent = dateObj.toLocaleDateString('es-ES', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        document.getElementById('bookingModal').style.display = 'block';
        const list = document.getElementById('doctors-list');
        list.innerHTML = '<tr><td colspan="2" class="text-center" style="padding: 2rem;"><i class="fa-solid fa-spinner fa-spin text-primary" style="font-size: 2rem;"></i></td></tr>';
        fetch(`<?= $baseUrl ?>/api/specialty-slots/${specialtyId}/${dateStr}`)
            .then(res => res.json())
            .then(data => {
                list.innerHTML = '';
                if (data.success && data.data.length > 0) {
                    data.data.forEach(doc => {
                        const tr = document.createElement('tr');
                        tr.style.borderBottom = '1px solid var(--border-light)';
                        const tdDoc = document.createElement('td');
                        tdDoc.style.padding = '15px 10px';
                        tdDoc.style.verticalAlign = 'middle';
                        tdDoc.innerHTML = `<strong><i class="fa-solid fa-user-doctor text-primary"></i> Dr. ${doc.name}</strong>`;
                        const tdSlots = document.createElement('td');
                        tdSlots.style.padding = '15px 10px';
                        if (doc.slots.length === 0) {
                            tdSlots.innerHTML = '<span class="text-muted"><i class="fa-solid fa-calendar-xmark"></i> Sin horarios libres</span>';
                        } else {
                            const slotsContainer = document.createElement('div');
                            slotsContainer.style.display = 'flex';
                            slotsContainer.style.flexWrap = 'wrap';
                            slotsContainer.style.gap = '8px';
                            doc.slots.forEach(slot => {
                                const btn = document.createElement('button');
                                btn.textContent = slot.start_time.substring(0,5);
                                btn.className = 'btn btn-sm';
                                if (slot.status === 'available') {
                                    btn.style.background = '#e0f2fe';
                                    btn.style.color = '#0284c7';
                                    btn.style.border = '1px solid #7dd3fc';
                                    btn.onclick = () => selectSlot(doc.id, slot.id, dateStr);
                                } else {
                                    btn.style.background = '#f1f5f9';
                                    btn.style.color = '#94a3b8';
                                    btn.style.border = '1px solid #e2e8f0';
                                    btn.disabled = true;
                                    btn.style.textDecoration = 'line-through';
                                }
                                slotsContainer.appendChild(btn);
                            });
                            tdSlots.appendChild(slotsContainer);
                        }
                        tr.appendChild(tdDoc);
                        tr.appendChild(tdSlots);
                        list.appendChild(tr);
                    });
                } else {
                    list.innerHTML = '<tr><td colspan="2" class="text-center text-muted" style="padding: 2rem;">No hay médicos disponibles para esta fecha.</td></tr>';
                }
            })
            .catch(err => console.error(err));
    }
    function closeModal() {
        document.getElementById('bookingModal').style.display = 'none';
    }
    function selectSlot(doctorId, slotId, dateStr) {
        if (confirm('¿Estás seguro de que deseas reagendar tu cita para este nuevo horario?')) {
            document.getElementById('form_doctor_id').value = doctorId;
            document.getElementById('form_availability_id').value = slotId;
            document.getElementById('form_date').value = dateStr;
            // Bloqueo temporal (Soft Booking)
            const formData = new FormData();
            formData.append('availability_id', slotId);
            fetch('/booking/lock-slot', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('rescheduleForm').submit();
                } else {
                    alert('Lo sentimos, este turno ya no está disponible o acaba de ser tomado por otra persona.');
                    openModal(dateStr); 
                }
            })
            .catch(err => {
                console.error(err);
                document.getElementById('rescheduleForm').submit();
            });
        }
    }
    window.onclick = function(event) {
        const modal = document.getElementById('bookingModal');
        if (event.target == modal) {
            closeModal();
        }
    }
    fetchDates();
</script>
