<div class="container main-content mt-3">
    <div class="wizard-progress">
        <div class="wizard-step completed">
            <div class="step-number"><i class="fa-solid fa-check"></i></div>
            <span class="step-label">Especialidad</span>
        </div>
        <div class="wizard-connector completed"></div>
        <div class="wizard-step active">
            <div class="step-number">2</div>
            <span class="step-label">Fecha</span>
        </div>
        <div class="wizard-connector"></div>
        <div class="wizard-step">
            <div class="step-number">3</div>
            <span class="step-label">Confirmación</span>
        </div>
        <div class="wizard-connector"></div>
        <div class="wizard-step">
            <div class="step-number">4</div>
            <span class="step-label">Datos</span>
        </div>
        <div class="wizard-connector"></div>
        <div class="wizard-step">
            <div class="step-number">5</div>
            <span class="step-label">Finalizar</span>
        </div>
    </div>
    <div class="card p-0">
        <div class="card-header d-flex justify-between align-center p-3" style="background: var(--primary); color: white;">
            <button class="btn btn-sm" style="background: rgba(255,255,255,0.2); color: white;" onclick="changeMonth(-1)"><i class="fa-solid fa-chevron-left"></i></button>
            <h2 id="calendar-month-year" style="margin: 0;">Cargando...</h2>
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
            <div class="p-3 text-center">
                <a href="<?= $baseUrl ?>/booking/step/1" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Volver a Especialidades</a>
            </div>
        </div>
    </div>
</div>
<div id="bookingModal" class="modal" style="display: none; position: fixed; z-index: 900; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5);">
    <div class="modal-content card" style="margin: 1% auto; width: 90%; max-width: 1200px; padding: 0;">
        <div class="modal-header" style="background: #1e3a8a; color: white; padding: 10px; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0;">Citas disponibles</h3>
            <span class="close" onclick="closeModal()" style="cursor: pointer; font-size: 1.5rem;">&times;</span>
        </div>
        <div class="modal-body p-3">
            <div class="mb-3">
                <h4 style="margin-bottom: 5px;">Especialidad: <span style="font-weight: normal;"><?= htmlspecialchars($specialty['name']) ?></span></h4>
                <h4>Fecha: <span id="modal-date-display" style="font-weight: normal;"></span> <i class="fa-regular fa-calendar"></i></h4>
            </div>
            <table class="table" style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                <thead>
                    <tr>
                        <th style="background: #1e3a8a; color: white; padding: 10px; text-align: left; width: 40%;">Médico</th>
                        <th style="background: #1e3a8a; color: white; padding: 10px; text-align: left; width: 60%;">Turnos disponibles</th>
                    </tr>
                </thead>
                <tbody id="doctors-list">
                </tbody>
            </table>
            <div class="legend" style="font-size: 0.9rem;">
                <strong>Información</strong><br>
                Disponibles: <span class="badge" style="background: #e2e8f0; color: #64748b;">No Disponibles</span><br>
                Disponibles: <span class="badge badge-info">Disponibles</span><br>
                Ocupado: <span class="badge text-warning" style="border: 1px solid #f59e0b;">Ocupado</span><br>
                Bloqueado: <span class="badge badge-danger">Bloqueado</span><br>
                Seleccionado: <span class="badge badge-success">Seleccionado</span>
            </div>
        </div>
        <div class="modal-footer p-3 text-right" style="border-top: 1px solid #ddd;">
            <button class="btn btn-secondary" onclick="closeModal()"><i class="fa-solid fa-xmark"></i> Cerrar</button>
        </div>
    </div>
</div>
<form id="bookingForm" action="<?= $baseUrl ?>/booking/process" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
    <input type="hidden" name="action" value="set_datetime_doctor">
    <input type="hidden" name="doctor_id" id="form_doctor_id">
    <input type="hidden" name="availability_id" id="form_availability_id">
    <input type="hidden" name="date" id="form_date">
</form>
<script>
    const specialtyId = <?= $specialty['id'] ?>;
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
        // Clear old cells (keep headers)
        while (grid.children.length > 7) {
            grid.removeChild(grid.lastChild);
        }
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        const monthNames = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
        monthYear.textContent = monthNames[month] + " " + year;
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        // Fill empty spaces before first day
        for (let i = 0; i < firstDay; i++) {
            const emptyCell = document.createElement('div');
            emptyCell.style.background = '#f9fafb';
            emptyCell.style.padding = '40px 10px';
            grid.appendChild(emptyCell);
        }
        // Fill days
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
            dayNum.style.background = '#6b7280';
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
            statusText.style.fontSize = '0.9rem';
            statusText.style.marginTop = '15px';
            statusText.style.textAlign = 'center';
            if (slotCount > 0) {
                statusText.innerHTML = `Citas<br>Disponibles<br>${slotCount}`;
            } else {
                statusText.textContent = 'No disponible';
                statusText.style.color = '#4b5563';
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
        document.getElementById('modal-date-display').textContent = dateStr;
        document.getElementById('bookingModal').style.display = 'block';
        const list = document.getElementById('doctors-list');
        list.innerHTML = '<tr><td colspan="2" class="text-center">Cargando médicos...</td></tr>';
        fetch(`<?= $baseUrl ?>/api/specialty-slots/${specialtyId}/${dateStr}`)
            .then(res => res.json())
            .then(data => {
                list.innerHTML = '';
                if (data.success && data.data.length > 0) {
                    data.data.forEach(doc => {
                        const tr = document.createElement('tr');
                        tr.style.borderBottom = '1px solid #ddd';
                        // Doctor Info
                        const tdDoc = document.createElement('td');
                        tdDoc.style.padding = '10px';
                        tdDoc.style.verticalAlign = 'top';
                        let feeHtml = doc.show_fee === '1' ? `<br><small>Precio: $${parseFloat(doc.consultation_fee).toFixed(2)}</small>` : '';
                        tdDoc.innerHTML = `
                            <strong><i class="fa-solid fa-user-doctor"></i> ${doc.name}</strong>
                            ${feeHtml}
                        `;
                        // Slots
                        const tdSlots = document.createElement('td');
                        tdSlots.style.padding = '10px';
                        if (doc.slots.length === 0) {
                            tdSlots.innerHTML = '<div class="text-center text-muted">No disponible</div>';
                        } else {
                            const slotsContainer = document.createElement('div');
                            slotsContainer.style.display = 'flex';
                            slotsContainer.style.flexWrap = 'wrap';
                            slotsContainer.style.gap = '10px';
                            doc.slots.forEach(slot => {
                                const btn = document.createElement('button');
                                btn.textContent = slot.start_time.substring(0,5);
                                btn.style.border = 'none';
                                btn.style.padding = '5px 15px';
                                btn.style.borderRadius = '15px';
                                btn.style.cursor = 'pointer';
                                btn.style.fontWeight = 'bold';
                                if (slot.status === 'available') {
                                    btn.style.background = '#bae6fd';
                                    btn.style.color = '#0369a1';
                                    btn.onclick = () => selectSlot(doc.id, slot.id, dateStr);
                                } else {
                                    btn.style.background = '#e2e8f0';
                                    btn.style.color = '#64748b';
                                    btn.disabled = true;
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
                    list.innerHTML = '<tr><td colspan="2" class="text-center text-muted">No hay médicos disponibles para esta fecha.</td></tr>';
                }
            })
            .catch(err => console.error(err));
    }
    function closeModal() {
        document.getElementById('bookingModal').style.display = 'none';
    }
    function selectSlot(doctorId, slotId, dateStr) {
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
                document.getElementById('bookingForm').submit();
            } else {
                alert('Lo sentimos, este turno ya no está disponible o acaba de ser tomado por otra persona.');
                openModal(dateStr); 
            }
        })
        .catch(err => {
            console.error(err);
            document.getElementById('bookingForm').submit();
        });
    }
    // Close modal if clicked outside
    window.onclick = function(event) {
        const modal = document.getElementById('bookingModal');
        if (event.target == modal) {
            closeModal();
        }
    }
    // Init
    fetchDates();
</script>
