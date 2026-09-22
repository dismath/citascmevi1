<?php
/**
 * Vista: Recuperar Contraseña (Solicitud de enlace)
 */
?>
<div class="container main-content d-flex align-center" style="min-height: 80vh;">
    <div class="card mx-auto shadow-lg" style="max-width: 480px; width: 100%;">
        <div class="card-header bg-surface-3 text-center d-block" style="padding: 2.2rem 2rem 1.5rem;">
            <div style="font-size: 2.8rem; color: var(--primary); margin-bottom: 0.75rem;">
                <i class="fa-solid fa-unlock-keyhole"></i>
            </div>
            <h1 class="mb-1" style="font-size: 1.4rem; font-weight: 700;">Recuperar Contraseña</h1>
            <p class="text-muted mb-0" style="font-size: 0.875rem; line-height: 1.5;">
                Ingrese el correo electrónico asociado a su cuenta y le enviaremos las instrucciones para restablecer su clave.
            </p>
        </div>
        <div class="card-body p-4">
            <?php $flashError = \App\Helpers\Session::getFlash('error'); ?>
            <?php if ($flashError): ?>
                <div class="alert alert-danger d-flex align-items-center mb-4" role="alert" style="border-radius: 8px;">
                    <i class="fa-solid fa-circle-exclamation me-2" style="font-size: 1.1rem; flex-shrink: 0;"></i>
                    <div style="font-size: 0.88rem;"><?= htmlspecialchars($flashError) ?></div>
                </div>
            <?php endif; ?>
            <?php $flashSuccess = \App\Helpers\Session::getFlash('success'); ?>
            <?php if ($flashSuccess): ?>
                <div class="alert alert-success d-flex align-items-center mb-4" role="alert" style="border-radius: 8px;">
                    <i class="fa-solid fa-circle-check me-2" style="font-size: 1.1rem; flex-shrink: 0;"></i>
                    <div style="font-size: 0.88rem;"><?= htmlspecialchars($flashSuccess) ?></div>
                </div>
            <?php endif; ?>
            <form action="<?= $baseUrl ?>/forgot-password" method="POST" id="forgot-form" autocomplete="off" novalidate>
                <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                <div style="position: absolute; left: -9999px; top: -9999px; opacity: 0; pointer-events: none;" aria-hidden="true">
                    <label for="website_hp">No llenar este campo</label>
                    <input type="text" id="website_hp" name="website_hp" tabindex="-1" autocomplete="off">
                </div>
                <div class="form-group mb-4">
                    <label class="form-label" for="recovery-email" style="font-weight: 600; font-size: 0.88rem;">
                        Correo Electrónico Registrado
                    </label>
                    <div class="input-group">
                        <span class="btn btn-secondary"
                            style="border-right: none; background: var(--surface-3); pointer-events: none;">
                            <i class="fa-solid fa-envelope text-muted"></i>
                        </span>
                        <input
                            type="email"
                            id="recovery-email"
                            name="email"
                            class="form-control"
                            placeholder="su-correo@ejemplo.com"
                            required
                            autofocus
                            autocomplete="email"
                            style="border-left: none; padding-left: 0;">
                    </div>
                </div>
                <button type="submit" id="submit-btn" class="btn btn-primary btn-block btn-lg mb-3" style="width: 100%;">
                    <i class="fa-solid fa-paper-plane me-2"></i>
                    <span id="btn-text">Enviar Enlace de Recuperación</span>
                </button>
                <div class="text-center mt-3">
                    <a href="<?= $baseUrl ?>/login" class="text-decoration-none" style="font-size: 0.88rem; color: var(--primary); font-weight: 500;">
                        <i class="fa-solid fa-arrow-left me-1"></i> Volver a Iniciar Sesión
                    </a>
                </div>
            </form>
        </div>
        <div class="card-footer text-center bg-surface-3" style="padding: 0.9rem; border-top: 1px solid var(--border);">
            <small class="text-muted">
                <i class="fa-solid fa-shield-check" style="margin-right: 4px; color: var(--success);"></i>
                Seguridad y privacidad garantizada &mdash; Portal de Salud
            </small>
        </div>
    </div>
</div>
<script>
document.getElementById('forgot-form').addEventListener('submit', function() {
    const btn = document.getElementById('submit-btn');
    const text = document.getElementById('btn-text');
    btn.disabled = true;
    text.textContent = 'Enviando enlace seguro...';
});
</script>
