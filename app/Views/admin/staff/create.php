<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <div>
            <h3><i class="fa-solid fa-user-plus text-primary"></i> Registrar Nuevo Colaborador</h3>
            <p class="text-sm text-muted" style="margin: 0;">Ingrese los datos del personal administrativo u operativo de la clínica.</p>
        </div>
        <a href="<?= $baseUrl ?>/admin/staff" class="btn btn-sm btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Volver
        </a>
    </div>
    <div class="card-body">
        <form action="<?= $baseUrl ?>/admin/staff/store" method="POST" id="staff-create-form">
            <?= \App\Helpers\Session::csrfInput() ?>
            <h4 style="margin-bottom: 1rem; border-bottom: 1px solid var(--border-color, #e5e7eb); padding-bottom: 0.5rem; color: var(--primary);">
                <i class="fa-solid fa-address-card"></i> Datos Personales y Laborales
            </h4>
            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group">
                    <label for="name"><strong>Nombre Completo *</strong></label>
                    <input type="text" id="name" name="name" class="form-control" required placeholder="Ej: María José Morales" autocapitalize="words" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="id_number"><strong>Cédula de Identidad *</strong></label>
                    <input type="text" id="id_number" name="id_number" class="form-control" required maxlength="10" inputmode="numeric" placeholder="Ej: 1712345678" value="<?= htmlspecialchars($_POST['id_number'] ?? '') ?>">
                    <small class="text-muted">10 dígitos numéricos para cédula ecuatoriana.</small>
                </div>
            </div>
            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group">
                    <label for="department"><strong>Departamento / Área *</strong></label>
                    <select id="department" name="department" class="form-control" required>
                        <?php foreach($departments as $depKey => $depName): ?>
                            <option value="<?= htmlspecialchars($depKey) ?>" <?= ($_POST['department'] ?? 'Recepción') === $depKey ? 'selected' : '' ?>>
                                <?= htmlspecialchars($depName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="position"><strong>Cargo / Puesto *</strong></label>
                    <input type="text" id="position" name="position" class="form-control" required list="positions-list" placeholder="Ej: Recepcionista Principal" autocapitalize="words" value="<?= htmlspecialchars($_POST['position'] ?? 'Recepcionista') ?>">
                    <datalist id="positions-list">
                        <?php foreach($positions as $pos): ?>
                            <option value="<?= htmlspecialchars($pos) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
            </div>
            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group">
                    <label for="phone">Teléfono / Celular</label>
                    <input type="text" id="phone" name="phone" class="form-control" maxlength="15" inputmode="numeric" placeholder="Ej: 0991234567" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="hire_date">Fecha de Ingreso</label>
                    <input type="date" id="hire_date" name="hire_date" class="form-control" value="<?= htmlspecialchars($_POST['hire_date'] ?? date('Y-m-d')) ?>">
                </div>
            </div>
            <div class="form-group" style="margin-bottom: 1rem;">
                <label for="address">Dirección de Residencia</label>
                <input type="text" id="address" name="address" class="form-control" placeholder="Ej: Av. Amazonas y República" autocapitalize="sentences" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
            </div>
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="notes">Observaciones / Notas Internas</label>
                <textarea id="notes" name="notes" class="form-control" rows="2" placeholder="Notas sobre turno, contrato o responsabilidades..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
            </div>
            <h4 style="margin-bottom: 1rem; border-bottom: 1px solid var(--border-color, #e5e7eb); padding-bottom: 0.5rem; color: var(--primary);">
                <i class="fa-solid fa-key"></i> Acceso al Sistema (Login Unificado)
            </h4>
            <div style="background: var(--bg-surface, #f8fafc); padding: 1.25rem; border-radius: 8px; border: 1px solid var(--border-color, #e2e8f0); margin-bottom: 1.5rem;">
                <div class="form-check" style="margin-bottom: 1rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 600;">
                        <input type="checkbox" id="create_user" name="create_user" value="1" checked onchange="toggleUserFields(this.checked)">
                        Habilitar cuenta de usuario en el portal web
                    </label>
                </div>
                <div id="user_fields" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="email"><strong>Correo Electrónico *</strong></label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="ejemplo@clinicacmevi.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        <small class="text-muted">Servirá como usuario para iniciar sesión.</small>
                    </div>
                    <div class="form-group">
                        <label for="role"><strong>Rol de Acceso *</strong></label>
                        <select id="role" name="role" class="form-control" onchange="updateRolePreview(this)">
                            <?php 
                            $selectedRole = $_POST['role'] ?? 'receptionist';
                            foreach ($roles as $r): 
                                if (in_array(strtolower($r['name']), ['doctor', 'medico', 'patient', 'paciente'])) continue;
                                $rName = $r['name'];
                                $rLabel = !empty($r['display_name']) ? $r['display_name'] : ucfirst($rName);
                                if (strtoupper($rLabel) === 'TECNICO') $rLabel = 'Técnico';
                                if (strtolower($rName) === 'admin') $rLabel = 'Administrador';
                                $rDesc = !empty($r['description']) ? $r['description'] : '';
                                if (empty($rDesc)) {
                                    if ($rName === 'admin') $rDesc = 'Acceso total al sistema y configuraciones';
                                    elseif ($rName === 'receptionist') $rDesc = 'Gestión de citas, pacientes y agenda';
                                }
                                $pCount = (int)($r['permissions_count'] ?? 0);
                                $permText = ($rName === 'admin') ? 'Acceso Total' : ($pCount . ' ' . ($pCount === 1 ? 'permiso' : 'permisos'));
                                $isSelected = ($selectedRole === $rName || $selectedRole == $r['id']);
                            ?>
                                <option value="<?= htmlspecialchars($rName) ?>" 
                                        data-label="<?= htmlspecialchars($rLabel) ?>" 
                                        data-desc="<?= htmlspecialchars($rDesc) ?>" 
                                        data-perms="<?= htmlspecialchars($permText) ?>"
                                        <?= $isSelected ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($rLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Determina los privilegios y módulos a los que tendrá acceso.</small>
                    </div>
                </div>
                <!-- Previsualización reactiva de permisos del rol seleccionado -->
                <div id="role_preview_box" style="margin-top: 0.75rem; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.6rem 0.85rem; font-size: 0.82rem; display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-key text-primary"></i> 
                        <span id="role_preview_text"><strong>Rol seleccionado:</strong> Cargando privilegios...</span>
                    </div>
                    <a href="<?= $baseUrl ?>/admin/roles" target="_blank" style="font-size: 0.78rem; text-decoration: none; color: #2563eb; display: inline-flex; align-items: center; gap: 4px; font-weight: 500;">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i> Matriz de Roles y Permisos
                    </a>
                </div>
                <div style="margin-top: 0.75rem; font-size: 0.85rem; color: var(--text-muted);">
                    <i class="fa-solid fa-shield-halved text-info"></i> El sistema generará una contraseña temporal segura y enviará automáticamente un correo al colaborador con su enlace de activación.
                </div>
            </div>
            <div class="d-flex justify-between align-center" style="margin-top: 1.5rem;">
                <a href="<?= $baseUrl ?>/admin/staff" class="btn btn-secondary btn-sm" style="height: 38px; display: inline-flex; align-items: center; gap: 0.4rem;"><i class="fa-solid fa-arrow-left"></i> Cancelar</a>
                <button type="submit" class="btn btn-primary btn-sm" style="height: 38px; display: inline-flex; align-items: center; gap: 0.4rem;">
                    <i class="fa-solid fa-save"></i> Guardar Colaborador
                </button>
            </div>
        </form>
    </div>
</div>
<script>
function updateRolePreview(selectEl) {
    if (!selectEl) return;
    const selectedOpt = selectEl.options[selectEl.selectedIndex];
    const previewText = document.getElementById('role_preview_text');
    if (selectedOpt && previewText) {
        const label = selectedOpt.getAttribute('data-label') || selectedOpt.text;
        const perms = selectedOpt.getAttribute('data-perms') || '';
        const desc = selectedOpt.getAttribute('data-desc') || '';
        previewText.innerHTML = '<strong>' + label + ':</strong> ' + (perms ? '<span class="badge" style="background:#e0f2fe; color:#0369a1; font-weight:600; padding:2px 6px; border-radius:4px; font-size:0.75rem;">' + perms + '</span> ' : '') + desc;
    }
}
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role');
    if (roleSelect) updateRolePreview(roleSelect);
});

function toggleUserFields(enabled) {
    const fields = document.getElementById('user_fields');
    const previewBox = document.getElementById('role_preview_box');
    const emailInput = document.getElementById('email');
    if (enabled) {
        fields.style.display = 'grid';
        if (previewBox) previewBox.style.display = 'flex';
        emailInput.setAttribute('required', 'required');
    } else {
        fields.style.display = 'none';
        if (previewBox) previewBox.style.display = 'none';
        emailInput.removeAttribute('required');
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
    const nameInput     = document.getElementById('name');
    const positionInput = document.getElementById('position');
    const addressInput  = document.getElementById('address');
    [nameInput, positionInput].forEach(function(el) {
        if (!el) return;
        el.addEventListener('input', function() {
            const pos = this.selectionStart;
            this.value = capitalizeWords(this.value);
            try { this.setSelectionRange(pos, pos); } catch(e){}
        });
    });
    if (addressInput) {
        addressInput.addEventListener('input', function() {
            const pos = this.selectionStart;
            this.value = capitalizeFirst(this.value);
            try { this.setSelectionRange(pos, pos); } catch(e){}
        });
    }
});
</script>
