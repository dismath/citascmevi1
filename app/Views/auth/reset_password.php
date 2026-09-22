<?php
/**
 * Vista: Restablecer Contraseña (Ingreso de nueva clave)
 */
?>
<div class="container main-content d-flex align-center" style="min-height: 80vh; padding: 2rem 1rem;">
    <div class="card mx-auto shadow-lg" style="max-width: 500px; width: 100%; border-radius: 12px; overflow: hidden; border: 1px solid var(--border);">
        <div class="card-header bg-surface-3 text-center d-block" style="padding: 2.2rem 2rem 1.5rem; border-bottom: 1px solid var(--border);">
            <div style="font-size: 2.6rem; color: var(--primary); margin-bottom: 0.6rem;">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <h1 class="mb-1" style="font-size: 1.45rem; font-weight: 700; color: var(--text);">Establecer Mi Contraseña</h1>
            <p class="text-muted mb-0" style="font-size: 0.88rem;">
                Cree una nueva contraseña segura para acceder a su portal
            </p>
            <?php if (!empty($userEmail)): ?>
                <div class="mt-3 p-2 d-inline-flex align-items-center" style="background: rgba(26, 115, 232, 0.08); border-radius: 20px; padding: 6px 16px !important; max-width: 90%;">
                    <i class="fa-solid fa-user-circle me-2 text-primary" style="font-size: 1.1rem;"></i>
                    <span style="font-size: 0.84rem; font-weight: 600; color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <?= htmlspecialchars(!empty($userName) ? "{$userName} ({$userEmail})" : $userEmail) ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-body p-4">
            <?php $flashError = \App\Helpers\Session::getFlash('error'); ?>
            <?php if ($flashError): ?>
                <div class="alert alert-danger d-flex align-items-center mb-4" role="alert" style="border-radius: 8px; font-size: 0.88rem;">
                    <i class="fa-solid fa-circle-exclamation me-2" style="font-size: 1.1rem; flex-shrink: 0;"></i>
                    <div><?= htmlspecialchars($flashError) ?></div>
                </div>
            <?php endif; ?>
            <div id="js-error-alert" class="alert alert-danger align-items-center mb-4" role="alert" style="display: none; border-radius: 8px; font-size: 0.88rem;">
                <i class="fa-solid fa-circle-exclamation me-2" style="font-size: 1.1rem; flex-shrink: 0;"></i>
                <div id="js-error-text"></div>
            </div>
            <form action="<?= $baseUrl ?>/reset-password" method="POST" id="reset-form" autocomplete="off" novalidate>
                <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token ?? '') ?>">
                <div class="form-group mb-3">
                    <label class="form-label" for="new-password" style="font-weight: 600; font-size: 0.88rem;">
                        Nueva Contraseña <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="btn btn-secondary"
                            style="border-right: none; background: var(--surface-3); pointer-events: none;">
                            <i class="fa-solid fa-lock text-muted"></i>
                        </span>
                        <input
                            type="password"
                            id="new-password"
                            name="password"
                            class="form-control"
                            placeholder="Mínimo 8 caracteres (letras y números)"
                            required
                            minlength="8"
                            autocomplete="new-password"
                            style="border-left: none; border-right: none; padding-left: 0;">
                        <button type="button"
                            id="toggle-pwd-1"
                            class="btn btn-secondary"
                            title="Mostrar / ocultar contraseña"
                            style="background: var(--surface-3); border-left: none;"
                            onclick="toggleVisibility('new-password', 'eye-icon-1')">
                            <i class="fa-solid fa-eye text-muted" id="eye-icon-1"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label" for="confirm-password" style="font-weight: 600; font-size: 0.88rem;">
                        Confirmar Contraseña <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="btn btn-secondary"
                            style="border-right: none; background: var(--surface-3); pointer-events: none;">
                            <i class="fa-solid fa-lock-check text-muted"></i>
                        </span>
                        <input
                            type="password"
                            id="confirm-password"
                            name="password_confirm"
                            class="form-control"
                            placeholder="Repita la nueva contraseña"
                            required
                            minlength="8"
                            autocomplete="new-password"
                            style="border-left: none; border-right: none; padding-left: 0;">
                        <button type="button"
                            id="toggle-pwd-2"
                            class="btn btn-secondary"
                            title="Mostrar / ocultar contraseña"
                            style="background: var(--surface-3); border-left: none;"
                            onclick="toggleVisibility('confirm-password', 'eye-icon-2')">
                            <i class="fa-solid fa-eye text-muted" id="eye-icon-2"></i>
                        </button>
                    </div>
                </div>
                <div class="p-3 mb-4" style="background: var(--surface-3); border-radius: 8px; border: 1px solid var(--border); font-size: 0.8rem;">
                    <div class="fw-bold mb-2 text-muted" style="font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px;">
                        Requisitos de seguridad:
                    </div>
                    <div id="req-len" class="d-flex align-items-center mb-1 text-muted">
                        <i class="fa-solid fa-circle-dot me-2" id="icon-len" style="font-size: 0.75rem;"></i>
                        <span>Al menos 8 caracteres</span>
                    </div>
                    <div id="req-alphanum" class="d-flex align-items-center mb-1 text-muted">
                        <i class="fa-solid fa-circle-dot me-2" id="icon-alphanum" style="font-size: 0.75rem;"></i>
                        <span>Combinación de letras y números</span>
                    </div>
                    <div id="req-match" class="d-flex align-items-center text-muted">
                        <i class="fa-solid fa-circle-dot me-2" id="icon-match" style="font-size: 0.75rem;"></i>
                        <span>Las dos contraseñas deben coincidir</span>
                    </div>
                </div>
                <button type="submit" id="submit-btn" class="btn btn-primary btn-block btn-lg mb-3" style="width: 100%;">
                    <i class="fa-solid fa-check-double me-2"></i>
                    <span id="btn-text">Guardar y Establecer Contraseña</span>
                </button>
                <div class="text-center pt-2">
                    <a href="<?= $baseUrl ?>/login?setup_cancelled=1" class="btn btn-outline-secondary w-100 mb-2" style="font-size: 0.9rem; font-weight: 500; border-radius: 6px;">
                        <i class="fa-solid fa-arrow-left me-1"></i> Cancelar y Volver al Inicio de Sesión
                    </a>
                    <p class="text-muted mb-0" style="font-size: 0.8rem; line-height: 1.4;">
                        <i class="fa-regular fa-clock me-1 text-primary"></i>
                        Si cancela ahora, este enlace seguirá disponible en su correo por <strong>24 horas</strong> para establecer su contraseña cuando lo desee.
                    </p>
                </div>
            </form>
        </div>
        <div class="card-footer text-center bg-surface-3" style="padding: 0.9rem; border-top: 1px solid var(--border);">
            <small class="text-muted">
                <i class="fa-solid fa-shield-halved text-success me-1"></i>
                Establecimiento protegido &mdash; Portal de Salud
            </small>
        </div>
    </div>
</div>
<script>
function toggleVisibility(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (!input || !icon) return;
    const isPass = input.type === 'password';
    input.type = isPass ? 'text' : 'password';
    icon.className = isPass ? 'fa-solid fa-eye-slash text-muted' : 'fa-solid fa-eye text-muted';
}
const pwdInput = document.getElementById('new-password');
const confirmInput = document.getElementById('confirm-password');
const jsErrorAlert = document.getElementById('js-error-alert');
const jsErrorText = document.getElementById('js-error-text');
function updateRule(elemId, iconId, isValid) {
    const row = document.getElementById(elemId);
    const icon = document.getElementById(iconId);
    if (!row || !icon) return;
    if (isValid) {
        row.className = 'd-flex align-items-center mb-1 text-success';
        icon.className = 'fa-solid fa-circle-check me-2';
    } else {
        row.className = 'd-flex align-items-center mb-1 text-muted';
        icon.className = 'fa-solid fa-circle-dot me-2';
    }
}
function validateLive() {
    const val = pwdInput.value;
    const confirmVal = confirmInput.value;
    const hasMinLen = val.length >= 8;
    const hasAlphaNum = /[A-Za-z]/.test(val) && /[0-9]/.test(val);
    const doesMatch = Boolean(confirmVal && val === confirmVal);
    updateRule('req-len', 'icon-len', hasMinLen);
    updateRule('req-alphanum', 'icon-alphanum', hasAlphaNum);
    updateRule('req-match', 'icon-match', doesMatch);
    if (confirmVal && val !== confirmVal) {
        const rowMatch = document.getElementById('req-match');
        const iconMatch = document.getElementById('icon-match');
        rowMatch.className = 'd-flex align-items-center text-danger';
        iconMatch.className = 'fa-solid fa-circle-xmark me-2';
    }
}
pwdInput.addEventListener('input', validateLive);
confirmInput.addEventListener('input', validateLive);
document.getElementById('reset-form').addEventListener('submit', function(e) {
    jsErrorAlert.style.display = 'none';
    const val = pwdInput.value;
    const confirmVal = confirmInput.value;
    if (!val || val.length < 8) {
        e.preventDefault();
        showJsError('La contraseña debe tener al menos 8 caracteres.');
        pwdInput.focus();
        return;
    }
    if (!/[A-Za-z]/.test(val) || !/[0-9]/.test(val)) {
        e.preventDefault();
        showJsError('La contraseña debe contener al menos una letra y un número.');
        pwdInput.focus();
        return;
    }
    if (val !== confirmVal) {
        e.preventDefault();
        showJsError('Las contraseñas ingresadas no coinciden.');
        confirmInput.focus();
        return;
    }
    const btn = document.getElementById('submit-btn');
    const text = document.getElementById('btn-text');
    btn.disabled = true;
    text.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> Guardando contraseña...';
});
function showJsError(msg) {
    jsErrorText.textContent = msg;
    jsErrorAlert.style.display = 'flex';
}
</script>
