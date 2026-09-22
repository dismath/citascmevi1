<div class="container main-content mt-3">
    <div class="wizard-progress">
        <div class="wizard-step completed"><div class="step-number"><i class="fa-solid fa-check"></i></div><span class="step-label">Especialidad</span></div>
        <div class="wizard-connector completed"></div>
        <div class="wizard-step completed"><div class="step-number"><i class="fa-solid fa-check"></i></div><span class="step-label">Fecha</span></div>
        <div class="wizard-connector completed"></div>
        <div class="wizard-step active"><div class="step-number">3</div><span class="step-label">Confirmación</span></div>
        <div class="wizard-connector"></div>
        <div class="wizard-step"><div class="step-number">4</div><span class="step-label">Datos</span></div>
        <div class="wizard-connector"></div>
        <div class="wizard-step"><div class="step-number">5</div><span class="step-label">Finalizar</span></div>
    </div>
    <div class="card">
        <div class="card-header text-center bg-primary text-white p-3">
            <h3 style="margin: 0;">Confirmar Selección de Cita</h3>
        </div>
        <div class="card-body">
            <div class="grid-2" style="display: grid;  gap: 20px;">
                <div class="p-3" style="background: #f8fafc; border-radius: 8px;">
                    <h4 class="mb-3 text-primary border-bottom pb-2">Datos del Médico</h4>
                    <p><strong>Especialidad:</strong> <?= htmlspecialchars($specialty['name']) ?></p>
                    <p><strong>Médico:</strong> <?= htmlspecialchars($doctor['name']) ?></p>
                    <?php if (!empty($doctor['medical_license'])): ?>
                    <p><strong>Licencia:</strong> <?= htmlspecialchars($doctor['medical_license']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="p-3" style="background: #f0fdf4; border-radius: 8px;">
                    <h4 class="mb-3 text-success border-bottom pb-2">Fecha y Hora</h4>
                    <p><strong>Fecha:</strong> <span class="badge badge-success" style="font-size: 1.1rem;"><?= date('d/m/Y', strtotime($slot['available_date'])) ?></span></p>
                    <p><strong>Hora:</strong> <span class="badge badge-primary" style="font-size: 1.1rem;"><?= date('H:i', strtotime($slot['start_time'])) ?></span></p>
                </div>
            </div>
            <div class="text-center mt-4 pt-3 border-top d-flex justify-content-between">
                <a href="<?= $baseUrl ?>/booking/step/2" class="btn btn-secondary btn-lg"><i class="fa-solid fa-arrow-left"></i> Cambiar Fecha/Médico</a>
                <form action="<?= $baseUrl ?>/booking/process" method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                    <input type="hidden" name="action" value="set_datetime">
                    <input type="hidden" name="availability_id" value="<?= $slot['id'] ?>">
                    <input type="hidden" name="date" value="<?= $slot['available_date'] ?>">
                    <button type="submit" class="btn btn-primary btn-lg">Continuar <i class="fa-solid fa-arrow-right"></i></button>
                </form>
            </div>
        </div>
    </div>
</div>
