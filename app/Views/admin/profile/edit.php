<div class="card" style="max-width: 600px; margin: 0 auto;">
    <div class="card-header">
        <h3><i class="fa-solid fa-id-badge text-primary"></i> Mi Perfil</h3>
    </div>
    <div class="card-body">
        <form action="<?= $baseUrl ?>/admin/profile/update" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <div class="form-group mb-3">
                <label class="form-label">Nombre Completo</label>
                <div class="input-icon">
                    <i class="fa-solid fa-user"></i>
                    <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($staff['name'] ?? $staff['nombre_usuario'] ?? '') ?>" required>
                </div>
            </div>
            <?php if (!empty($staff['position'])): ?>
            <div class="form-group mb-3">
                <label class="form-label">Cargo</label>
                <div class="input-icon">
                    <i class="fa-solid fa-briefcase"></i>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($staff['position'] ?? '') ?>" disabled readonly style="opacity: 0.7;">
                </div>
                <small class="text-muted">El cargo es asignado por el administrador.</small>
            </div>
            <?php endif; ?>
            <?php if (!empty($staff['department'])): ?>
            <div class="form-group mb-3">
                <label class="form-label">Departamento</label>
                <div class="input-icon">
                    <i class="fa-solid fa-building"></i>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($staff['department'] ?? '') ?>" disabled readonly style="opacity: 0.7;">
                </div>
            </div>
            <?php endif; ?>
            <div class="form-group mb-3">
                <label class="form-label">Teléfono</label>
                <div class="input-icon">
                    <i class="fa-solid fa-phone"></i>
                    <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($staff['phone'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group mb-3">
                <label class="form-label">Dirección</label>
                <div class="input-icon">
                    <i class="fa-solid fa-location-dot"></i>
                    <input type="text" class="form-control" name="address" value="<?= htmlspecialchars($staff['address'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group mb-3">
                <label class="form-label">Nombre de Usuario (Para Iniciar Sesión)</label>
                <div class="input-icon">
                    <i class="fa-solid fa-at"></i>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($staff['username'] ?? $staff['nombre_usuario'] ?? '') ?>" disabled readonly style="background: var(--bg-surface, #f8fafc); font-weight: 600; opacity: 0.9;">
                </div>
                <small class="text-muted">Su nombre de usuario único para identificarse y acceder al sistema.</small>
            </div>
            <div class="form-group mb-3">
                <label class="form-label">Correo Electrónico (Para Iniciar Sesión)</label>
                <div class="input-icon">
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($staff['email'] ?? '') ?>" required>
                </div>
            </div>
            <div style="border-top: 1px solid var(--border-light); margin: 1.5rem 0;"></div>
            <h4 class="mb-3" style="color: var(--text-muted); font-size: 0.95rem;">Seguridad</h4>
            <div class="form-group mb-3">
                <label class="form-label">Nueva Contraseña</label>
                <input type="password" class="form-control" name="password" placeholder="••••••••" autocomplete="new-password">
                <small class="text-muted">Dejar en blanco para mantener la contraseña actual</small>
            </div>
            <div class="form-group mb-4">
                <label class="form-label">Confirmar Contraseña</label>
                <input type="password" class="form-control" name="confirm_password" placeholder="••••••••" autocomplete="new-password">
            </div>
            <div class="form-group mb-4" style="background: var(--bg-surface, #f8fafc); padding: 1.25rem; border-radius: 8px; border: 1px solid var(--border-color, #e2e8f0);">
                <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem;">
                    <div>
                        <label for="two_factor_enabled" style="font-weight: 600; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem; cursor: pointer;">
                            <i class="fa-solid fa-shield-halved text-primary"></i> Autenticación en Dos Pasos (2FA) por Correo
                        </label>
                        <p class="text-xs text-muted" style="margin: 0;">
                            Al activar esta opción, cada vez que inicie sesión se le enviará un código de verificación de 6 dígitos a su correo electrónico.
                        </p>
                    </div>
                    <div style="padding-top: 2px;">
                        <input type="checkbox" name="two_factor_enabled" id="two_factor_enabled" value="1" <?= !empty($staff['two_factor_enabled']) ? 'checked' : '' ?> style="width: 1.35rem; height: 1.35rem; cursor: pointer;">
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100">
                <i class="fa-solid fa-save"></i> Guardar Cambios
            </button>
        </form>
    </div>
</div>
