<div class="card max-w-3xl mx-auto">
       <div class="card-body">
        <form action="<?= $baseUrl ?>/admin/doctors/update/<?= $doctor['id'] ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
            <h4 class="mb-2 text-primary border-bottom pb-1">Datos Personales</h4>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Nombre Completo *</label>
                    <input type="text" id="doctor-name" name="name" class="form-control" required value="<?= htmlspecialchars($doctor['name'] ?? '') ?>" autocapitalize="words">
                </div>
                <div class="form-group">
                    <label class="form-label">Cédula / Identificación *</label>
                    <input type="text" name="id_number" class="form-control" required maxlength="10" inputmode="numeric" value="<?= htmlspecialchars($doctor['id_number'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Teléfono</label>
                    <input type="text" name="phone" class="form-control" maxlength="15" inputmode="numeric" value="<?= htmlspecialchars($doctor['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Dirección / Consultorio</label>
                    <input type="text" id="doctor-address" name="address" class="form-control" value="<?= htmlspecialchars($doctor['address'] ?? '') ?>" autocapitalize="sentences">
                </div>
            </div>
            <h4 class="mb-2 mt-3 text-primary border-bottom pb-1">Datos Profesionales</h4>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Especialidad *</label>
                    <div class="d-flex gap-1 align-center">
                        <select name="specialty_id" id="specialty-select" class="form-control" required style="flex: 1;">
                            <option value="">Seleccione...</option>
                            <?php foreach($specialties as $sp): ?>
                                <option value="<?= $sp['id'] ?>" <?= $sp['id'] == $doctor['specialty_id'] ? 'selected' : '' ?>><?= htmlspecialchars($sp['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-sm btn-success" onclick="openNewSpecialtyModal()" title="Crear nueva especialidad" style="white-space: nowrap; height: 38px;">
                            <i class="fa-solid fa-plus"></i> Nueva
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Licencia Médica / Registro</label>
                    <input type="text" name="medical_license" class="form-control" value="<?= htmlspecialchars($doctor['medical_license'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Costo de Consulta ($)</label>
                    <input type="number" step="0.01" name="consultation_fee" class="form-control" value="<?= htmlspecialchars($doctor['consultation_fee'] ?? '0.00') ?>">
                </div>
                <div class="form-group d-flex align-center mt-3">
                    <label class="form-label mb-0 d-flex align-center gap-1" style="cursor:pointer;">
                        <input type="checkbox" name="show_fee" value="1" <?= $doctor['show_fee'] ? 'checked' : '' ?> style="width: 18px; height: 18px;">
                        Mostrar costo al paciente
                    </label>
                </div>
            </div>
            <h4 class="mb-2 mt-3 text-primary border-bottom pb-1">Datos de Acceso al Sistema</h4>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Nombre de Usuario (Login)</label>
                    <div style="position: relative; display: flex; align-items: center;">
                        <input type="text" class="form-control" value="<?= htmlspecialchars($doctor['username'] ?? $doctor['nombre_usuario'] ?? '') ?>" disabled readonly style="background: var(--bg-surface, #f8fafc); font-weight: 600;">
                        <span class="badge badge-info" style="position: absolute; right: 8px; font-size: 0.75rem;">Usuario</span>
                    </div>
                    <small class="text-muted">Identificador único del usuario para iniciar sesión.</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Correo Electrónico (Login)</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($doctor['email'] ?? '') ?>" placeholder="ejemplo@correo.com">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="password" class="form-control" placeholder="Dejar en blanco para no cambiar" autocomplete="new-password">
                </div>
            </div>
            <div class="text-right mt-3 d-flex justify-content-end align-items-center gap-2">
                <a href="<?= $baseUrl ?>/admin/doctors" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Volver</a>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-save"></i> Actualizar Médico</button>
            </div>
        </form>
    </div>
</div>
<div id="modal-new-specialty" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div class="card" style="max-width:460px; width:95%; margin:auto; border-radius:12px; box-shadow: 0 8px 32px rgba(0,0,0,0.3);">
        <div class="card-header">
            <h4 style="margin:0;"><i class="fa-solid fa-plus-circle text-success"></i> Nueva Especialidad</h4>
            <button type="button" onclick="closeNewSpecialtyModal()" class="btn btn-sm btn-secondary" style="font-size:1.1rem; line-height:1; padding:4px 10px;">&times;</button>
        </div>
        <div class="card-body">
            <div id="modal-specialty-error" class="alert alert-danger" style="display:none;"></div>
            <div class="form-group mb-3">
                <label class="form-label">Nombre de la Especialidad *</label>
                <input type="text" id="new-specialty-name" class="form-control" placeholder="Ej: Cardiología" required>
            </div>
            <div class="form-group mb-3">
                <label class="form-label">Tipo de Catálogo *</label>
                <select id="new-specialty-catalog-type" class="form-control" required>
                    <option value="">-- Seleccionar Tipo de Catálogo --</option>
                    <?php if(!empty($catalogTypes)): ?>
                        <?php foreach($catalogTypes as $ct): ?>
                            <option value="<?= htmlspecialchars($ct['code']) ?>" <?= $ct['code'] === 'ESPEC' ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ct['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="ESPEC" selected>Especialidad</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group mb-3">
                <label class="form-label">Descripción (opcional)</label>
                <input type="text" id="new-specialty-desc" class="form-control" placeholder="Breve descripción...">
            </div>
            <div class="d-flex gap-1 justify-end">
                <button type="button" class="btn btn-secondary" onclick="closeNewSpecialtyModal()">Cancelar</button>
                <button type="button" class="btn btn-success" id="btn-save-specialty" onclick="saveNewSpecialty()">
                    <i class="fa-solid fa-save"></i> Guardar Especialidad
                </button>
            </div>
        </div>
    </div>
</div>
<script>
function openNewSpecialtyModal() {
    document.getElementById('modal-new-specialty').style.display = 'flex';
    document.getElementById('new-specialty-name').value = '';
    document.getElementById('new-specialty-desc').value = '';
    const catSelect = document.getElementById('new-specialty-catalog-type');
    if (catSelect) catSelect.value = 'ESPEC';
    document.getElementById('modal-specialty-error').style.display = 'none';
    setTimeout(() => document.getElementById('new-specialty-name').focus(), 100);
}
function closeNewSpecialtyModal() {
    document.getElementById('modal-new-specialty').style.display = 'none';
}
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeNewSpecialtyModal(); });
function saveNewSpecialty() {
    const name = document.getElementById('new-specialty-name').value.trim();
    const desc = document.getElementById('new-specialty-desc').value.trim();
    const catSelect = document.getElementById('new-specialty-catalog-type');
    const catalogTypeCode = catSelect ? catSelect.value.trim() : 'ESPEC';
    const errorDiv = document.getElementById('modal-specialty-error');
    const btn = document.getElementById('btn-save-specialty');
    if (!name) { 
        errorDiv.textContent = 'El nombre de la especialidad es obligatorio.'; 
        errorDiv.style.display = 'block'; 
        return; 
    }
    if (!catalogTypeCode) {
        errorDiv.textContent = 'Debe seleccionar un tipo de catálogo.';
        errorDiv.style.display = 'block';
        return;
    }
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando...';
    errorDiv.style.display = 'none';
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    fetch('<?= $baseUrl ?>/admin/specialties/store-ajax', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            name, 
            description: desc, 
            catalog_type_code: catalogTypeCode, 
            csrf_token: csrfToken 
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const select = document.getElementById('specialty-select');
            const option = document.createElement('option');
            option.value = data.specialty.id;
            option.textContent = data.specialty.name;
            option.selected = true;
            select.appendChild(option);
            closeNewSpecialtyModal();
        } else {
            errorDiv.textContent = data.message || 'Error al crear la especialidad.';
            errorDiv.style.display = 'block';
        }
    })
    .catch(() => { errorDiv.textContent = 'Error de conexión. Intente nuevamente.'; errorDiv.style.display = 'block'; })
    .finally(() => { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-save"></i> Guardar Especialidad'; });
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
const doctorNameInput    = document.getElementById('doctor-name');
const doctorAddressInput = document.getElementById('doctor-address');
if (doctorNameInput) {
    doctorNameInput.addEventListener('input', function() {
        const pos = this.selectionStart;
        this.value = capitalizeWords(this.value);
        try { this.setSelectionRange(pos, pos); } catch(e){}
    });
}
if (doctorAddressInput) {
    doctorAddressInput.addEventListener('input', function() {
        const pos = this.selectionStart;
        this.value = capitalizeFirst(this.value);
        try { this.setSelectionRange(pos, pos); } catch(e){}
    });
}
</script>
