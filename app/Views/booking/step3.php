<div class="container main-content mt-3">
    <div class="wizard-progress">
        <div class="wizard-step completed"><div class="step-number"><i class="fa-solid fa-check"></i></div><span class="step-label">Especialidad</span></div>
        <div class="wizard-connector completed"></div>
        <div class="wizard-step completed"><div class="step-number"><i class="fa-solid fa-check"></i></div><span class="step-label">Médico</span></div>
        <div class="wizard-connector completed"></div>
        <div class="wizard-step active"><div class="step-number">3</div><span class="step-label">Fecha y Hora</span></div>
        <div class="wizard-connector"></div>
        <div class="wizard-step"><div class="step-number">4</div><span class="step-label">Datos</span></div>
        <div class="wizard-connector"></div>
        <div class="wizard-step"><div class="step-number">5</div><span class="step-label">Confirmación</span></div>
    </div>
    <div class="grid-3">
        <div class="card">
            <div class="card-body text-center">
                <div class="card-icon" style="width: 80px; height: 80px; font-size: 2rem; margin: 0 auto 1rem; border-radius: 50%; background: var(--primary-50); color: var(--primary); display: flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-user-doctor"></i>
                </div>
                <h3><?= htmlspecialchars($doctor['name']) ?></h3>
                <p class="text-muted"><?= htmlspecialchars($doctor['specialty_name']) ?></p>
                <div class="mt-2">
                    <a href="<?= $baseUrl ?>/booking/step/2" class="btn btn-secondary btn-sm btn-block">Cambiar Médico</a>
                </div>
            </div>
        </div>
        <div class="card" style="grid-column: span 2;">
            <div class="card-header">
                <h3>Seleccione Fecha y Hora</h3>
            </div>
            <div class="card-body">
                <form action="<?= $baseUrl ?>/booking/process" method="POST" id="form-datetime">
                    <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                    <input type="hidden" name="action" value="set_datetime">
                    <input type="hidden" name="availability_id" id="selected_slot_id" required>
                    <input type="hidden" name="date" id="selected_date" required>
                    <div class="form-group">
                        <label class="form-label">Fecha de la cita</label>
                        <select class="form-control" id="date_selector" onchange="loadTimeSlots(this.value, <?= $doctor['id'] ?>)">
                            <option value="">-- Seleccione una fecha disponible --</option>
                            <?php foreach ($dates as $date): ?>
                                <option value="<?= $date ?>"><?= date('d/m/Y', strtotime($date)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="slots_container" class="mt-3 hidden">
                        <label class="form-label">Horarios disponibles</label>
                        <div class="slots-grid" id="slots_grid">
                        </div>
                    </div>
                    <div class="mt-3 text-right hidden" id="btn_continue_container">
                        <button type="submit" class="btn btn-primary btn-lg">Continuar <i class="fa-solid fa-arrow-right"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
