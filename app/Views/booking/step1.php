<div class="container main-content">
    <div class="wizard-progress">
        <div class="wizard-step active">
            <div class="step-number">1</div>
            <span class="step-label">Especialidad</span>
        </div>
        <div class="wizard-connector"></div>
        <div class="wizard-step">
            <div class="step-number">2</div>
            <span class="step-label">Médico</span>
        </div>
        <div class="wizard-connector"></div>
        <div class="wizard-step">
            <div class="step-number">3</div>
            <span class="step-label">Fecha y Hora</span>
        </div>
        <div class="wizard-connector"></div>
        <div class="wizard-step">
            <div class="step-number">4</div>
            <span class="step-label">Datos</span>
        </div>
        <div class="wizard-connector"></div>
        <div class="wizard-step">
            <div class="step-number">5</div>
            <span class="step-label">Confirmación</span>
        </div>
    </div>
    <div class="card p-3">
        <h2 class="mb-3 text-center">Agende su Cita Médica</h2>
        <?php foreach ($specialties as $code => $group): ?>
            <?php if (!empty($group['items'])): ?>
                <h3 class="mt-3 mb-2 text-primary"><?= htmlspecialchars($group['name']) ?></h3>
                <div class="selection-grid mb-3">
                    <?php foreach ($group['items'] as $sp): ?>
                        <form action="<?= $baseUrl ?>/booking/process" method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                            <input type="hidden" name="action" value="set_specialty">
                            <input type="hidden" name="specialty_id" value="<?= $sp['id'] ?>">
                            <div class="selection-card" onclick="this.parentNode.submit();">
                                <div class="card-icon"><i class="fa-solid fa-<?= htmlspecialchars($sp['icon'] ?? 'stethoscope') ?>"></i></div>
                                <h4><?= htmlspecialchars($sp['name']) ?></h4>
                            </div>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>