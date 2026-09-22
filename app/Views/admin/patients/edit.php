<div class="card max-w-3xl mx-auto">
      <div class="card-body">
        <form action="<?= $baseUrl ?>/admin/patients/update/<?= $patient['id'] ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
            <input type="hidden" name="document_type" id="document_type_hidden" value="cedula">
            <div class="form-group mb-3">
                <label class="form-label">Tipo de Documento *</label>
                <div id="doc-type-selector" style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.25rem;">
                    <label class="doc-type-option active" data-value="cedula" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; border: 2px solid var(--primary); border-radius: var(--radius-md); cursor: pointer; font-size: 0.9rem; font-weight: 500; background: var(--primary-50); color: var(--primary); transition: all 0.2s;">
                        <input type="radio" name="document_type_radio" value="cedula" checked style="display: none;">
                        <i class="fa-solid fa-id-card"></i> Cédula
                    </label>
                    <label class="doc-type-option" data-value="ruc" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; border: 2px solid var(--border); border-radius: var(--radius-md); cursor: pointer; font-size: 0.9rem; font-weight: 500; background: transparent; color: var(--text-2); transition: all 0.2s;">
                        <input type="radio" name="document_type_radio" value="ruc" style="display: none;">
                        <i class="fa-solid fa-building"></i> RUC
                    </label>
                    <label class="doc-type-option disabled" data-value="pasaporte" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; border: 2px solid var(--border); border-radius: var(--radius-md); cursor: not-allowed; font-size: 0.9rem; font-weight: 500; background: transparent; color: var(--text-3); opacity: 0.5; transition: all 0.2s;">
                        <input type="radio" name="document_type_radio" value="pasaporte" disabled style="display: none;">
                        <i class="fa-solid fa-passport"></i> Pasaporte
                    </label>
                    <label class="doc-type-option disabled" data-value="id_extranjera" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; border: 2px solid var(--border); border-radius: var(--radius-md); cursor: not-allowed; font-size: 0.9rem; font-weight: 500; background: transparent; color: var(--text-3); opacity: 0.5; transition: all 0.2s;">
                        <input type="radio" name="document_type_radio" value="id_extranjera" disabled style="display: none;">
                        <i class="fa-solid fa-earth-americas"></i> ID Extranjera
                    </label>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" id="id_number_label">Identificación (Cédula) *</label>
                    <input type="text" name="id_number" id="id_number_input" class="form-control" required value="<?= htmlspecialchars($patient['id_number']) ?>" maxlength="20">
                    <div id="id_validation_feedback" style="font-size: 0.825rem; margin-top: 0.35rem; min-height: 1.2em;"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Nombre Completo *</label>
                    <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($patient['name']) ?>" autocapitalize="words">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Teléfono</label>
                    <input type="text" name="phone" class="form-control" inputmode="numeric" maxlength="15" value="<?= htmlspecialchars($patient['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Género</label>
                    <select name="gender" class="form-control">
                        <option value="">Seleccione...</option>
                        <option value="M" <?= ($patient['gender'] ?? '') === 'M' ? 'selected' : '' ?>>Masculino</option>
                        <option value="F" <?= ($patient['gender'] ?? '') === 'F' ? 'selected' : '' ?>>Femenino</option>
                        <option value="O" <?= ($patient['gender'] ?? '') === 'O' ? 'selected' : '' ?>>Otro</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Fecha de Nacimiento</label>
                    <input type="date" name="date_of_birth" class="form-control" value="<?= htmlspecialchars($patient['date_of_birth'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Dirección Domiciliaria</label>
                    <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($patient['address'] ?? '') ?>" autocapitalize="sentences">
                </div>
            </div>
            <h4 class="mb-2 mt-3 text-primary border-bottom pb-1">Datos de Acceso al Sistema</h4>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Nombre de Usuario (Login)</label>
                    <div style="position: relative; display: flex; align-items: center;">
                        <input type="text" class="form-control" value="<?= htmlspecialchars($patient['username'] ?? $patient['nombre_usuario'] ?? '') ?>" disabled readonly style="background: var(--bg-surface, #f8fafc); font-weight: 600;">
                        <span class="badge badge-info" style="position: absolute; right: 8px; font-size: 0.75rem;">Usuario</span>
                    </div>
                    <small class="text-muted">Identificador único del paciente para iniciar sesión.</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Correo Electrónico (Login)</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($patient['email'] ?? '') ?>" <?= $patient['user_id'] ? 'required' : '' ?>>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="password" class="form-control" placeholder="<?= $patient['user_id'] ? 'Dejar en blanco para no cambiar' : 'Requerida si ingresa correo' ?>" autocomplete="new-password">
                </div>
            </div>
            <div class="text-right mt-3 d-flex justify-content-end align-items-center gap-2">
                <a href="<?= $baseUrl ?>/admin/patients" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Volver</a>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-save"></i> Actualizar Paciente</button>
            </div>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const idInput = document.getElementById('id_number_input');
    const hiddenDocType = document.getElementById('document_type_hidden');
    const feedback = document.getElementById('id_validation_feedback');
    const idLabel = document.getElementById('id_number_label');
    const docTypeOptions = document.querySelectorAll('.doc-type-option');
    const pasaporteOpt = document.querySelector('.doc-type-option[data-value="pasaporte"]');
    const idExtranjeraOpt = document.querySelector('.doc-type-option[data-value="id_extranjera"]');
    let currentDocType = 'cedula';
    function validarModulo10(numero) {
        const coef = [2,1,2,1,2,1,2,1,2];
        let suma = 0;
        for (let i = 0; i < 9; i++) {
            let val = parseInt(numero[i]) * coef[i];
            if (val > 9) val -= 9;
            suma += val;
        }
        const residuo = suma % 10;
        const resultado = residuo === 0 ? 0 : 10 - residuo;
        return resultado === parseInt(numero[9]);
    }
    function validarModulo11Publica(ruc) {
        const coef = [3,2,7,6,5,4,3,2];
        let suma = 0;
        for (let i = 0; i < 8; i++) suma += parseInt(ruc[i]) * coef[i];
        const residuo = suma % 11;
        const resultado = residuo === 0 ? 0 : 11 - residuo;
        return resultado === parseInt(ruc[8]);
    }
    function validarModulo11Privada(ruc) {
        const coef = [4,3,2,7,6,5,4,3,2];
        let suma = 0;
        for (let i = 0; i < 9; i++) suma += parseInt(ruc[i]) * coef[i];
        const residuo = suma % 11;
        const resultado = residuo === 0 ? 0 : 11 - residuo;
        return resultado === parseInt(ruc[9]);
    }
    function isValidCedula(val) {
        if (!/^[0-9]{10}$/.test(val)) return false;
        const prov = parseInt(val.substring(0, 2));
        if (prov < 1 || prov > 24) return false;
        if (parseInt(val[2]) >= 6) return false;
        return validarModulo10(val);
    }
    function isValidRuc(val) {
        if (!/^[0-9]{13}$/.test(val)) return false;
        const prov = parseInt(val.substring(0, 2));
        if (prov < 1 || prov > 24) return false;
        const t = parseInt(val[2]);
        if (t >= 0 && t <= 5) { 
            if (val.substring(10, 13) === '000') return false; 
            return validarModulo10(val); 
        }
        else if (t === 6) { 
            if (val.substring(9, 13) === '0000') return false; 
            return validarModulo11Publica(val); 
        }
        else if (t === 9) { 
            if (val.substring(10, 13) === '000') return false; 
            // El SRI emite RUCs válidos que rompen el módulo 11 (ej. 1793220209001)
            // Por ende, si pasa la validación estructural (13 dígitos, prov, termina en 001), 
            // lo aceptamos.
            return true; 
        }
        return false;
    }
    function selectDocType(type) {
        currentDocType = type;
        hiddenDocType.value = type;
        docTypeOptions.forEach(opt => {
            const v = opt.getAttribute('data-value');
            if (v === type) {
                opt.classList.add('active'); opt.style.borderColor = 'var(--primary)'; opt.style.background = 'var(--primary-50)'; opt.style.color = 'var(--primary)';
            } else {
                opt.classList.remove('active'); opt.style.borderColor = 'var(--border)'; opt.style.background = 'transparent';
                opt.style.color = opt.classList.contains('disabled') ? 'var(--text-3)' : 'var(--text-2)';
            }
        });
        if (type === 'cedula') { idLabel.textContent = 'Identificación (Cédula) *'; idInput.maxLength = 10; }
        else if (type === 'ruc') { idLabel.textContent = 'Número de RUC *'; idInput.maxLength = 13; }
        else if (type === 'pasaporte') { idLabel.textContent = 'Número de Pasaporte *'; idInput.maxLength = 20; }
        else if (type === 'id_extranjera') { idLabel.textContent = 'ID Extranjera *'; idInput.maxLength = 20; }
        validateAndFeedback();
    }
    function enableAlternativeDocs() {
        [pasaporteOpt, idExtranjeraOpt].forEach(opt => { opt.classList.remove('disabled'); opt.style.opacity = '1'; opt.style.cursor = 'pointer'; opt.querySelector('input[type="radio"]').disabled = false; });
    }
    function disableAlternativeDocs() {
        [pasaporteOpt, idExtranjeraOpt].forEach(opt => { opt.classList.add('disabled'); opt.style.opacity = '0.5'; opt.style.cursor = 'not-allowed'; opt.querySelector('input[type="radio"]').disabled = true; });
        if (currentDocType === 'pasaporte' || currentDocType === 'id_extranjera') selectDocType('cedula');
    }
    function validateAndFeedback() {
        const val = idInput.value.trim();
        if (currentDocType === 'pasaporte' || currentDocType === 'id_extranjera') {
            feedback.innerHTML = val.length > 0 ? '<span style="color: var(--success);"><i class="fa-solid fa-circle-check"></i> Documento alternativo aceptado</span>' : '';
            return;
        }
        if (val.length === 0) { feedback.innerHTML = ''; disableAlternativeDocs(); return; }
        if (/^[0-9]+$/.test(val)) {
            if (val.length === 10 && isValidCedula(val)) { feedback.innerHTML = '<span style="color: var(--success);"><i class="fa-solid fa-circle-check"></i> Cédula válida</span>'; disableAlternativeDocs(); return; }
            if (val.length === 13 && isValidRuc(val)) {
                const t = parseInt(val[2]); let tipo = 'Persona Natural'; if (t === 6) tipo = 'Sociedad Pública'; else if (t === 9) tipo = 'Sociedad Privada';
                feedback.innerHTML = '<span style="color: var(--success);"><i class="fa-solid fa-circle-check"></i> RUC válido (' + tipo + ')</span>'; disableAlternativeDocs(); return;
            }
            if (val.length < 10) { feedback.innerHTML = '<span style="color: var(--text-3);"><i class="fa-solid fa-keyboard"></i> Ingresando...</span>'; disableAlternativeDocs(); return; }
            if (val.length > 10 && val.length < 13) { feedback.innerHTML = '<span style="color: var(--text-3);"><i class="fa-solid fa-keyboard"></i> Ingresando RUC...</span>'; disableAlternativeDocs(); return; }
        }
        feedback.innerHTML = '<span style="color: var(--warning);"><i class="fa-solid fa-triangle-exclamation"></i> No es Cédula/RUC válida. Puede seleccionar Pasaporte o ID Extranjera.</span>';
        enableAlternativeDocs();
    }
    docTypeOptions.forEach(opt => {
        opt.addEventListener('click', function(e) {
            if (this.classList.contains('disabled')) { e.preventDefault(); return; }
            this.querySelector('input[type="radio"]').checked = true;
            selectDocType(this.getAttribute('data-value'));
        });
    });
    idInput.addEventListener('input', function() {
        if (currentDocType === 'pasaporte' || currentDocType === 'id_extranjera') { validateAndFeedback(); return; }
        const val = this.value.trim();
        if (/^[0-9]*$/.test(val)) {
            if (val.length <= 10 && currentDocType !== 'cedula') { hiddenDocType.value = 'cedula'; currentDocType = 'cedula'; }
            else if (val.length > 10 && currentDocType !== 'ruc') { hiddenDocType.value = 'ruc'; currentDocType = 'ruc'; }
        }
        validateAndFeedback();
    });
    // ===== Validación de campos específicos =====
    const nameInput = document.querySelector('input[name="name"]');
    const phoneInput = document.querySelector('input[name="phone"]');
    const addressInput = document.querySelector('input[name="address"]');
    function capitalizeWords(str) {
        return str.replace(/[^a-zA-Z0-9\s.,áéíóúÁÉÍÓÚñÑ-]/g, '')
                  .replace(/(^|\s)([a-záéíóúñ])/g, (m, sep, c) => sep + c.toUpperCase());
    }
    function capitalizeFirst(str) {
        str = str.replace(/[^a-zA-Z0-9\s.,#-áéíóúÁÉÍÓÚñÑ]/g, '');
        return str.length > 0 ? str.charAt(0).toUpperCase() + str.slice(1) : str;
    }
    function revalidateName() {
        if (!nameInput) return;
        const pos = nameInput.selectionStart;
        nameInput.value = capitalizeWords(nameInput.value);
        try { nameInput.setSelectionRange(pos, pos); } catch(e){}
    }
    if (nameInput) {
        nameInput.addEventListener('input', revalidateName);
    }
    if (phoneInput) {
        // Teléfono: Solo números
        phoneInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    }
    if (addressInput) {
        // Dirección: Primera letra mayúscula
        addressInput.addEventListener('input', function() {
            const pos = this.selectionStart;
            this.value = capitalizeFirst(this.value);
            try { this.setSelectionRange(pos, pos); } catch(e){}
        });
    }
    // Wrap the original selectDocType to include name revalidation
    const originalSelectDocType = selectDocType;
    selectDocType = function(type) {
        originalSelectDocType(type);
        revalidateName();
    };
    // Auto-detectar tipo de documento del paciente existente al cargar
    const existingVal = idInput.value.trim();
    if (existingVal.length > 0) {
        if (/^[0-9]{10}$/.test(existingVal) && isValidCedula(existingVal)) {
            selectDocType('cedula');
        } else if (/^[0-9]{13}$/.test(existingVal) && isValidRuc(existingVal)) {
            selectDocType('ruc');
        } else {
            // No es cédula ni RUC → habilitar alternativas y dejar como "otro"
            enableAlternativeDocs();
            if (/^[0-9]+$/.test(existingVal)) {
                validateAndFeedback();
            } else {
                // Contiene letras, probablemente pasaporte
                selectDocType('pasaporte');
            }
        }
    } else {
        validateAndFeedback();
    }
    revalidateName();
});
</script>
