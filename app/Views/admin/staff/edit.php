<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <div>
            <h3><i class="fa-solid fa-user-pen text-primary"></i> Editar Colaborador</h3>
            <p class="text-sm text-muted" style="margin: 0;">Actualice la información laboral y de acceso de <?= htmlspecialchars($staff['name']) ?>.</p>
        </div>
        <a href="<?= $baseUrl ?>/admin/staff" class="btn btn-sm btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Volver
        </a>
    </div>
    <div class="card-body">
        <form action="<?= $baseUrl ?>/admin/staff/update/<?= \App\Helpers\HashId::encode($staff['id']) ?>" method="POST" id="staff-edit-form">
            <?= \App\Helpers\Session::csrfInput() ?>
            <h4 style="margin-bottom: 1rem; border-bottom: 1px solid var(--border-color, #e5e7eb); padding-bottom: 0.5rem; color: var(--primary);">
                <i class="fa-solid fa-address-card"></i> Datos Personales y Laborales
            </h4>
            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group">
                    <label for="name"><strong>Nombre Completo *</strong></label>
                    <input type="text" id="name" name="name" class="form-control" required value="<?= htmlspecialchars($staff['name']) ?>" autocapitalize="words">
                </div>
                <div class="form-group">
                    <label for="id_number"><strong>Cédula de Identidad *</strong></label>
                    <input type="text" id="id_number" name="id_number" class="form-control" required maxlength="10" inputmode="numeric" value="<?= htmlspecialchars($staff['id_number']) ?>">
                </div>
            </div>
            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group">
                    <label for="department"><strong>Departamento / Área *</strong></label>
                    <select id="department" name="department" class="form-control" required>
                        <?php foreach($departments as $depKey => $depName): ?>
                            <option value="<?= htmlspecialchars($depKey) ?>" <?= $staff['department'] === $depKey ? 'selected' : '' ?>>
                                <?= htmlspecialchars($depName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="position"><strong>Cargo / Puesto *</strong></label>
                    <input type="text" id="position" name="position" class="form-control" required list="positions-list" value="<?= htmlspecialchars($staff['position']) ?>" autocapitalize="words">
                    <datalist id="positions-list">
                        <?php foreach($positions as $pos): ?>
                            <option value="<?= htmlspecialchars($pos) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
            </div>
            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group">
                    <label for="phone">Teléfono / Celular</label>
                    <input type="text" id="phone" name="phone" class="form-control" maxlength="15" inputmode="numeric" value="<?= htmlspecialchars($staff['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="hire_date">Fecha de Ingreso</label>
                    <input type="date" id="hire_date" name="hire_date" class="form-control" value="<?= htmlspecialchars($staff['hire_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="status"><strong>Estado Laboral *</strong></label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="active" <?= $staff['status'] === 'active' ? 'selected' : '' ?>>Activo</option>
                        <option value="inactive" <?= $staff['status'] === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
                        <option value="terminated" <?= $staff['status'] === 'terminated' ? 'selected' : '' ?>>Terminado</option>
                    </select>
                </div>
            </div>
            <div class="form-group" style="margin-bottom: 1rem;">
                <label for="address">Dirección de Residencia</label>
                <input type="text" id="address" name="address" class="form-control" value="<?= htmlspecialchars($staff['address'] ?? '') ?>" autocapitalize="sentences">
            </div>
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="notes">Observaciones / Notas Internas</label>
                <textarea id="notes" name="notes" class="form-control" rows="2" autocapitalize="sentences"><?= htmlspecialchars($staff['notes'] ?? '') ?></textarea>
            </div>
            <h4 style="margin-bottom: 1rem; border-bottom: 1px solid var(--border-color, #e5e7eb); padding-bottom: 0.5rem; color: var(--primary);">
                <i class="fa-solid fa-key"></i> Cuenta de Acceso al Sistema
            </h4>
            <div style="background: var(--bg-surface, #f8fafc); padding: 1.25rem; border-radius: 8px; border: 1px solid var(--border-color, #e2e8f0); margin-bottom: 1.5rem;">
                <?php if(!empty($staff['user_id'])): ?>
                    <div style="margin-bottom: 1rem;">
                        <span class="badge badge-success"><i class="fa-solid fa-link"></i> Cuenta de Usuario Vinculada (ID: <?= $staff['user_id'] ?>)</span>
                    </div>
                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div class="form-group">
                            <label for="username"><strong>Nombre de Usuario</strong></label>
                            <input type="text" id="username" class="form-control" disabled readonly value="<?= htmlspecialchars($staff['username'] ?? $staff['nombre_usuario'] ?? '') ?>" style="background: var(--bg-surface-2, #f1f5f9); font-weight: 600;">
                            <small class="text-muted">Login de usuario</small>
                        </div>
                        <div class="form-group">
                            <label for="email"><strong>Correo Electrónico (Login) *</strong></label>
                            <input type="email" id="email" name="email" class="form-control" required value="<?= htmlspecialchars($staff['email'] ?? $staff['user_email'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="role_id"><strong>Rol en el Sistema *</strong></label>
                            <select id="role_id" name="role_id" class="form-control" onchange="updateRoleEditPreview(this)">
                                <?php foreach($roles as $role): ?>
                                    <?php 
                                    if (in_array(strtolower($role['name']), ['doctor', 'medico', 'patient', 'paciente'])) continue;
                                    $rName = $role['name'];
                                    $rLabel = !empty($role['display_name']) ? $role['display_name'] : ucfirst($rName);
                                    if (strtoupper($rLabel) === 'TECNICO') $rLabel = 'Técnico';
                                    if (strtolower($rName) === 'admin') $rLabel = 'Administrador';
                                    $rDesc = !empty($role['description']) ? $role['description'] : '';
                                    if (empty($rDesc)) {
                                        if ($rName === 'admin') $rDesc = 'Acceso Total al Sistema';
                                        elseif ($rName === 'receptionist') $rDesc = 'Recepción y Citas';
                                    }
                                    $pCount = (int)($role['permissions_count'] ?? 0);
                                    $permText = ($rName === 'admin') ? 'Acceso Total' : ($pCount . ' ' . ($pCount === 1 ? 'permiso' : 'permisos'));
                                    $isSelected = (($staff['role_id'] ?? '') == $role['id'] || ($staff['primary_role'] ?? '') === $rName);
                                    ?>
                                    <option value="<?= $role['id'] ?>" 
                                            data-label="<?= htmlspecialchars($rLabel) ?>"
                                            data-desc="<?= htmlspecialchars($rDesc) ?>"
                                            data-perms="<?= htmlspecialchars($permText) ?>"
                                            <?= $isSelected ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($rLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div id="role_edit_preview_box" style="margin-bottom: 1rem; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.6rem 0.85rem; font-size: 0.82rem; display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fa-solid fa-key text-primary"></i> 
                            <span id="role_edit_preview_text"><strong>Rol seleccionado:</strong> Cargando privilegios...</span>
                        </div>
                        <a href="<?= $baseUrl ?>/admin/roles" target="_blank" style="font-size: 0.78rem; text-decoration: none; color: #2563eb; display: inline-flex; align-items: center; gap: 4px; font-weight: 500;">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i> Matriz de Roles y Permisos
                        </a>
                    </div>
                    <div class="form-group" style="margin-top: 0.5rem;">
                        <label for="password">Cambiar Contraseña (opcional)</label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Dejar en blanco para mantener la contraseña actual">
                        <small class="text-muted">Si escribe una nueva contraseña, se actualizará en la tabla unificada de usuarios (mínimo 8 caracteres).</small>
                    </div>
                <?php else: ?>
                    <div style="margin-bottom: 0.75rem;">
                        <span class="badge badge-secondary"><i class="fa-solid fa-unlink"></i> Sin cuenta de acceso actualmente</span>
                    </div>
                    <div class="form-check" style="margin-bottom: 1rem;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 600;">
                            <input type="checkbox" id="create_user" name="create_user" value="1" onchange="document.getElementById('new_user_box').style.display = this.checked ? 'block' : 'none'">
                            Crear cuenta de acceso ahora para este colaborador
                        </label>
                    </div>
                    <div id="new_user_box" style="display: none;">
                        <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div class="form-group">
                                <label for="email"><strong>Correo Electrónico *</strong></label>
                                <input type="email" id="email" name="email" class="form-control" placeholder="ejemplo@clinicacmevi.com" value="<?= htmlspecialchars($staff['email'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="role"><strong>Rol de Acceso *</strong></label>
                                <select id="role" name="role" class="form-control">
                                    <?php foreach($roles as $role): ?>
                                        <?php 
                                        if (in_array(strtolower($role['name']), ['doctor', 'medico', 'patient', 'paciente'])) continue;
                                        $rName = $role['name'];
                                        $rLabel = !empty($role['display_name']) ? $role['display_name'] : ucfirst($rName);
                                        if (strtoupper($rLabel) === 'TECNICO') $rLabel = 'Técnico';
                                        if (strtolower($rName) === 'admin') $rLabel = 'Administrador';
                                        ?>
                                        <option value="<?= htmlspecialchars($rName) ?>" <?= $rName === 'receptionist' ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($rLabel) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="password">Contraseña Inicial (opcional)</label>
                            <input type="password" id="password" name="password" class="form-control" placeholder="Dejar en blanco para generar una aleatoria">
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="d-flex justify-between align-center" style="margin-top: 1.5rem;">
                <a href="<?= $baseUrl ?>/admin/staff" class="btn btn-secondary btn-sm" style="height: 38px; display: inline-flex; align-items: center; gap: 0.4rem;"><i class="fa-solid fa-arrow-left"></i> Cancelar</a>
                <button type="submit" class="btn btn-primary btn-sm" style="height: 38px; display: inline-flex; align-items: center; gap: 0.4rem;">
                    <i class="fa-solid fa-save"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>
<script>
function updateRoleEditPreview(selectEl) {
    if (!selectEl) return;
    const selectedOpt = selectEl.options[selectEl.selectedIndex];
    const previewText = document.getElementById('role_edit_preview_text');
    if (selectedOpt && previewText) {
        const label = selectedOpt.getAttribute('data-label') || selectedOpt.text;
        const perms = selectedOpt.getAttribute('data-perms') || '';
        const desc = selectedOpt.getAttribute('data-desc') || '';
        previewText.innerHTML = '<strong>' + label + ':</strong> ' + (perms ? '<span class="badge" style="background:#e0f2fe; color:#0369a1; font-weight:600; padding:2px 6px; border-radius:4px; font-size:0.75rem;">' + perms + '</span> ' : '') + desc;
    }
}

/* ─── Capitalizar campos de texto ──────────────────────────────────── */
function capitalizeWords(str) {
    return str.replace(/[^a-zA-Z0-9\s.,áéíóúÁÉÍÓÚñÑ-]/g, '')
              .replace(/(^|\s)([a-záéíóúñ])/g, (m, sep, c) => sep + c.toUpperCase());
}
function capitalizeFirst(str) {
    str = str.replace(/[^a-zA-Z0-9\s.,#-áéíóúÁÉÍÓÚñÑ]/g, '');
    return str.length > 0 ? str.charAt(0).toUpperCase() + str.slice(1) : str;
}
document.addEventListener('DOMContentLoaded', function() {
    const roleIdSelect = document.getElementById('role_id');
    if (roleIdSelect) updateRoleEditPreview(roleIdSelect);

    const nameInput     = document.getElementById('name');
    const positionInput = document.getElementById('position');
    const addressInput  = document.getElementById('address');
    const notesInput    = document.getElementById('notes');
    [nameInput, positionInput].forEach(function(el) {
        if (!el) return;
        el.addEventListener('input', function() {
            const pos = this.selectionStart;
            this.value = capitalizeWords(this.value);
            try { this.setSelectionRange(pos, pos); } catch(e){}
        });
    });
    [addressInput, notesInput].forEach(function(el) {
        if (!el) return;
        el.addEventListener('input', function() {
            const pos = this.selectionStart;
            this.value = capitalizeFirst(this.value);
            try { this.setSelectionRange(pos, pos); } catch(e){}
        });
    });
});
</script>
