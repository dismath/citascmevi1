<?php
/**
 * Vista: Login Unificado
 * Sirve para todos los roles: admin, recepcionista, doctor y paciente.
 * El sistema detecta el rol automáticamente por el email ingresado.
 */
?>
<div class="container main-content d-flex align-center" style="min-height: 80vh;">
    <div class="card mx-auto shadow-lg" style="max-width: 460px; width: 100%;">
        <div class="card-header bg-surface-3 text-center d-block" style="padding: 2rem 2rem 1.5rem;">
            <div style="font-size: 2.8rem; color: var(--primary); margin-bottom: 0.75rem;">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <h1 class="mb-1" style="font-size: 1.4rem; font-weight: 700;">Iniciar Sesión</h1>
            <p class="text-muted mb-0" style="font-size: 0.875rem;">
                Acceso seguro para todos los usuarios del sistema
            </p>
        </div>
        <div class="card-body p-4">
            <form action="<?= $baseUrl ?>/login" method="POST" id="login-form" autocomplete="off" novalidate>
                <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                <div style="position: absolute; left: -9999px; top: -9999px; opacity: 0; pointer-events: none;" aria-hidden="true">
                    <label for="website_hp">No llenar este campo</label>
                    <input type="text" id="website_hp" name="website_hp" tabindex="-1" autocomplete="off">
                </div>
                <div class="form-group mb-3">
                    <label class="form-label" for="login-email">Correo Electrónico o Nombre de Usuario</label>
                    <div class="input-group">
                        <span class="btn btn-secondary"
                            style="border-right: none; background: var(--surface-3); pointer-events: none;">
                            <i class="fa-solid fa-user text-muted"></i>
                        </span>
                        <input
                            type="text"
                            id="login-email"
                            name="email"
                            class="form-control"
                            placeholder="E-mail o Nombre de Usuario"
                            required
                            autofocus
                            autocomplete="username"
                            style="border-left: none; padding-left: 0;">
                    </div>
                </div>
                <div class="form-group mb-1">
                    <label class="form-label" for="login-password">Contraseña</label>
                    <div class="input-group">
                        <span class="btn btn-secondary"
                            style="border-right: none; background: var(--surface-3); pointer-events: none;">
                            <i class="fa-solid fa-lock text-muted"></i>
                        </span>
                        <input
                            type="password"
                            id="login-password"
                            name="password"
                            class="form-control"
                            placeholder="••••••••"
                            required
                            autocomplete="current-password"
                            style="border-left: none; border-right: none; padding-left: 0;">
                        <button type="button"
                            id="toggle-password"
                            class="btn btn-secondary"
                            title="Mostrar / ocultar contraseña"
                            style="background: var(--surface-3); border-left: none;"
                            onclick="togglePassword()">
                            <i class="fa-solid fa-eye text-muted" id="eye-icon"></i>
                        </button>
                    </div>
                </div>
                <div class="text-end mb-4">
                    <a href="<?= $baseUrl ?>/forgot-password"
                        style="font-size: 0.82rem; color: var(--primary); text-decoration: none;">
                        ¿Olvidaste tu contraseña?
                    </a>
                </div>
                <button type="submit" id="login-btn" class="btn btn-primary btn-block btn-lg mb-3">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    <span id="login-btn-text">Iniciar Sesión</span>
                </button>
            </form>
        </div>
        <div class="card-footer text-center bg-surface-3"
            style="padding: 0.9rem; border-top: 1px solid var(--border);">
            <small class="text-muted">
                <i class="fa-solid fa-lock" style="margin-right: 4px; color: var(--success);"></i>
                Conexión segura &mdash; Portal de Salud
            </small>
        </div>
    </div>
</div>
<script>
/**
 * Alterna visibilidad de la contraseña (accesibilidad).
 */
function togglePassword() {
    const input  = document.getElementById('login-password');
    const icon   = document.getElementById('eye-icon');
    const btn    = document.getElementById('toggle-password');
    const isPass = input.type === 'password';
    input.type = isPass ? 'text' : 'password';
    icon.className = isPass ? 'fa-solid fa-eye-slash text-muted' : 'fa-solid fa-eye text-muted';
    btn.title = isPass ? 'Ocultar contraseña' : 'Mostrar contraseña';
}
/**
 * Estado de carga en el botón al enviar el formulario.
 * Previene doble submit accidental.
 */
document.getElementById('login-form').addEventListener('submit', function () {
    const btn  = document.getElementById('login-btn');
    const text = document.getElementById('login-btn-text');
    btn.disabled = true;
    text.textContent = 'Verificando...';
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Verificando...';
});
</script>