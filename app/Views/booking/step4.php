<div class="container main-content mt-3">
    <div class="wizard-progress">
        <div class="wizard-step completed"><div class="step-number"><i class="fa-solid fa-check"></i></div><span class="step-label">Especialidad</span></div>
        <div class="wizard-connector completed"></div>
        <div class="wizard-step completed"><div class="step-number"><i class="fa-solid fa-check"></i></div><span class="step-label">Médico</span></div>
        <div class="wizard-connector completed"></div>
        <div class="wizard-step completed"><div class="step-number"><i class="fa-solid fa-check"></i></div><span class="step-label">Fecha y Hora</span></div>
        <div class="wizard-connector completed"></div>
        <div class="wizard-step active"><div class="step-number">4</div><span class="step-label">Datos</span></div>
        <div class="wizard-connector"></div>
        <div class="wizard-step"><div class="step-number">5</div><span class="step-label">Confirmación</span></div>
    </div>
    <div class="grid-3">
        <div class="card" style="grid-column: span 2;">
            <div class="card-header">
                <h3>Datos del Paciente</h3>
            </div>
            <div class="card-body">
                <form action="<?= $baseUrl ?>/booking/process" method="POST" id="formPatientData">
                    <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                    <input type="hidden" name="action" value="set_patient">
                    <input type="hidden" name="document_type" id="document_type_hidden" value="cedula">
                    <div class="form-row">
                        <div class="form-group">
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
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" id="id_number_label">Número de Cédula *</label>
                            <input type="text" name="id_number" id="id_number_input" class="form-control" required placeholder="Ej: 17xxxxxxxx" maxlength="13">
                            <div id="id_validation_feedback" style="font-size: 0.825rem; margin-top: 0.35rem; min-height: 1.2em;"></div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nombre Completo *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Correo Electrónico *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Teléfono / Celular *</label>
                            <input type="text" name="phone" class="form-control" required inputmode="numeric" maxlength="15" placeholder="Ej: 0991234567">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Dirección</label>
                        <input type="text" name="address" class="form-control">
                    </div>
                    <div class="form-group mt-2">
                        <label class="d-flex align-items-start" style="gap: 10px; cursor: pointer; font-size: 0.92rem; line-height: 1.5;">
                            <input type="checkbox" id="acceptTermsCheck" style="margin-top: 4px; width: 18px; height: 18px; accent-color: var(--primary, #0d6efd); cursor: pointer; flex-shrink: 0;" onchange="document.getElementById('btnContinuarPago').disabled = !this.checked;">
                            <span>Acepto que los datos proporcionados son correctos y autorizo el uso de mi información personal para el agendamiento de esta cita médica, de acuerdo con la política de privacidad y los términos y condiciones del servicio.</span>
                        </label>
                    </div>
                    <div class="mt-3 d-flex justify-between">
                        <a href="<?= $baseUrl ?>/booking/step/3" class="btn btn-secondary">Volver</a>
                        <button type="submit" id="btnContinuarPago" class="btn btn-primary" disabled>Continuar al Pago <i class="fa-solid fa-arrow-right"></i></button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card bg-surface-2">
            <div class="card-header">
                <h3>Resumen</h3>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <span class="text-muted text-sm d-block">Especialidad</span>
                    <strong><?= htmlspecialchars($specialty['name']) ?></strong>
                </div>
                <div class="mb-2">
                    <span class="text-muted text-sm d-block">Médico</span>
                    <strong><?= htmlspecialchars($doctor['name']) ?></strong>
                </div>
                <div class="mb-2">
                    <span class="text-muted text-sm d-block">Fecha</span>
                    <strong><?= date('d/m/Y', strtotime($slot['available_date'])) ?></strong>
                </div>
                <div class="mb-2">
                    <span class="text-muted text-sm d-block">Hora</span>
                    <strong><?= date('H:i', strtotime($slot['start_time'])) ?></strong>
                </div>
            </div>
        </div>
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
    // ===== Algoritmos de validación del lado cliente =====
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
        for (let i = 0; i < 8; i++) {
            suma += parseInt(ruc[i]) * coef[i];
        }
        const residuo = suma % 11;
        const resultado = residuo === 0 ? 0 : 11 - residuo;
        return resultado === parseInt(ruc[8]);
    }
    function validarModulo11Privada(ruc) {
        const coef = [4,3,2,7,6,5,4,3,2];
        let suma = 0;
        for (let i = 0; i < 9; i++) {
            suma += parseInt(ruc[i]) * coef[i];
        }
        const residuo = suma % 11;
        const resultado = residuo === 0 ? 0 : 11 - residuo;
        return resultado === parseInt(ruc[9]);
    }
    function isValidCedula(val) {
        if (!/^[0-9]{10}$/.test(val)) return false;
        const prov = parseInt(val.substring(0, 2));
        if (prov < 1 || prov > 24) return false;
        const t = parseInt(val[2]);
        if (t >= 6) return false;
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
        } else if (t === 6) {
            if (val.substring(9, 13) === '0000') return false;
            return validarModulo11Publica(val);
        } else if (t === 9) {
            if (val.substring(10, 13) === '000') return false;
            // El SRI emite RUCs válidos que rompen el módulo 11 (ej. 1793220209001)
            // Por ende, si pasa la validación estructural (13 dígitos, prov, termina en 001), 
            // lo aceptamos.
            return true;
        }
        return false;
    }
    function isValidEcuadorianDoc(val) {
        if (val.length === 10) return isValidCedula(val);
        if (val.length === 13) return isValidRuc(val);
        return false;
    }
    // ===== Selección de tipo de documento =====
    function selectDocType(type) {
        currentDocType = type;
        hiddenDocType.value = type;
        docTypeOptions.forEach(opt => {
            const v = opt.getAttribute('data-value');
            if (v === type) {
                opt.classList.add('active');
                opt.style.borderColor = 'var(--primary)';
                opt.style.background = 'var(--primary-50)';
                opt.style.color = 'var(--primary)';
            } else {
                opt.classList.remove('active');
                opt.style.borderColor = 'var(--border)';
                opt.style.background = 'transparent';
                opt.style.color = opt.classList.contains('disabled') ? 'var(--text-3)' : 'var(--text-2)';
            }
        });
        // Actualizar label y placeholder
        if (type === 'cedula') {
            idLabel.textContent = 'Número de Cédula *';
            idInput.placeholder = 'Ej: 17xxxxxxxx';
            idInput.maxLength = 10;
        } else if (type === 'ruc') {
            idLabel.textContent = 'Número de RUC *';
            idInput.placeholder = 'Ej: 17xxxxxxxxx001';
            idInput.maxLength = 13;
        } else if (type === 'pasaporte') {
            idLabel.textContent = 'Número de Pasaporte *';
            idInput.placeholder = 'Ej: AB1234567';
            idInput.maxLength = 20;
        } else if (type === 'id_extranjera') {
            idLabel.textContent = 'Número de Identificación Extranjera *';
            idInput.placeholder = 'Ej: EXT-123456';
            idInput.maxLength = 20;
        }
        validateAndFeedback();
    }
    function enableAlternativeDocs() {
        [pasaporteOpt, idExtranjeraOpt].forEach(opt => {
            opt.classList.remove('disabled');
            opt.style.opacity = '1';
            opt.style.cursor = 'pointer';
            opt.querySelector('input[type="radio"]').disabled = false;
        });
    }
    function disableAlternativeDocs() {
        [pasaporteOpt, idExtranjeraOpt].forEach(opt => {
            opt.classList.add('disabled');
            opt.style.opacity = '0.5';
            opt.style.cursor = 'not-allowed';
            opt.querySelector('input[type="radio"]').disabled = true;
        });
        // Si estaba seleccionado pasaporte o id_extranjera, volver a cedula
        if (currentDocType === 'pasaporte' || currentDocType === 'id_extranjera') {
            selectDocType('cedula');
        }
    }
    function validateAndFeedback() {
        const val = idInput.value.trim();
        if (currentDocType === 'pasaporte' || currentDocType === 'id_extranjera') {
            if (val.length > 0) {
                feedback.innerHTML = '<span style="color: var(--success);"><i class="fa-solid fa-circle-check"></i> Documento alternativo aceptado</span>';
            } else {
                feedback.innerHTML = '';
            }
            return;
        }
        if (val.length === 0) {
            feedback.innerHTML = '';
            disableAlternativeDocs();
            return;
        }
        // Si es un número y podría ser cédula o RUC
        if (/^[0-9]+$/.test(val)) {
            if (val.length === 10 && isValidCedula(val)) {
                feedback.innerHTML = '<span style="color: var(--success);"><i class="fa-solid fa-circle-check"></i> Cédula válida</span>';
                disableAlternativeDocs();
                if (currentDocType !== 'cedula') selectDocType('cedula');
                return;
            }
            if (val.length === 13 && isValidRuc(val)) {
                const t = parseInt(val[2]);
                let tipoRuc = 'Persona Natural';
                if (t === 6) tipoRuc = 'Sociedad Pública';
                else if (t === 9) tipoRuc = 'Sociedad Privada';
                feedback.innerHTML = '<span style="color: var(--success);"><i class="fa-solid fa-circle-check"></i> RUC válido (' + tipoRuc + ')</span>';
                disableAlternativeDocs();
                if (currentDocType !== 'ruc') selectDocType('ruc');
                return;
            }
            if (val.length < 10) {
                feedback.innerHTML = '<span style="color: var(--text-3);"><i class="fa-solid fa-keyboard"></i> Ingresando...</span>';
                disableAlternativeDocs();
                return;
            }
            if (val.length > 10 && val.length < 13) {
                feedback.innerHTML = '<span style="color: var(--text-3);"><i class="fa-solid fa-keyboard"></i> Ingresando RUC...</span>';
                disableAlternativeDocs();
                return;
            }
        }
        // No es cédula ni RUC válido → habilitar opciones alternativas
        feedback.innerHTML = '<span style="color: var(--warning);"><i class="fa-solid fa-triangle-exclamation"></i> No es una Cédula/RUC válida. Puede seleccionar Pasaporte o ID Extranjera.</span>';
        enableAlternativeDocs();
    }
    // Event listeners para los botones de tipo de documento
    docTypeOptions.forEach(opt => {
        opt.addEventListener('click', function(e) {
            if (this.classList.contains('disabled')) {
                e.preventDefault();
                return;
            }
            const val = this.getAttribute('data-value');
            this.querySelector('input[type="radio"]').checked = true;
            selectDocType(val);
        });
    });
    // Validación en tiempo real al escribir
    idInput.addEventListener('input', function() {
        // Si se seleccionó pasaporte o id_extranjera, no auto-detectar
        if (currentDocType === 'pasaporte' || currentDocType === 'id_extranjera') {
            validateAndFeedback();
            return;
        }
        // Auto-detectar tipo
        const val = this.value.trim();
        if (/^[0-9]*$/.test(val)) {
            if (val.length <= 10) {
                if (currentDocType !== 'cedula') {
                    hiddenDocType.value = 'cedula';
                    currentDocType = 'cedula';
                }
            } else {
                if (currentDocType !== 'ruc') {
                    hiddenDocType.value = 'ruc';
                    currentDocType = 'ruc';
                }
            }
        }
        validateAndFeedback();
    });
    // Auto-fill de datos de paciente existente
    idInput.addEventListener('blur', function() {
        const idNumber = this.value.trim();
        if (idNumber.length >= 5) {
            fetch('<?= $baseUrl ?>/api/patient/' + idNumber)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data) {
                        const patient = data.data;
                        const nameInput = document.querySelector('input[name="name"]');
                        if (nameInput && !nameInput.value) { nameInput.value = patient.name || ''; nameInput.dispatchEvent(new Event('input')); }
                        const emailInput = document.querySelector('input[name="email"]');
                        if (emailInput && !emailInput.value) emailInput.value = patient.email || '';
                        const phoneInput = document.querySelector('input[name="phone"]');
                        if (phoneInput && !phoneInput.value) { phoneInput.value = patient.phone || ''; phoneInput.dispatchEvent(new Event('input')); }
                        const addressInput = document.querySelector('input[name="address"]');
                        if (addressInput && !addressInput.value) { addressInput.value = patient.address || ''; addressInput.dispatchEvent(new Event('input')); }
                    }
                })
                .catch(error => console.error('Error fetching patient data:', error));
        }
    });
    // ===== Validación de campos específicos =====
    const nameInput = document.querySelector('input[name="name"]');
    const phoneInput = document.querySelector('input[name="phone"]');
    const addressInput = document.querySelector('input[name="address"]');
    function revalidateName() {
        if (!nameInput) return;
        // Nombre: Acepta letras y números, y capitaliza cada palabra
        nameInput.value = nameInput.value.replace(/[^a-zA-Z0-9\s.,áéíóúÁÉÍÓÚñÑ-]/g, '');
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
        // Dirección: Solo letras, números y caracteres básicos
        addressInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z0-9\s.,#-áéíóúÁÉÍÓÚñÑ]/g, '');
        });
    }
    // Wrap the original selectDocType to include name revalidation
    const originalSelectDocType = selectDocType;
    selectDocType = function(type) {
        originalSelectDocType(type);
        revalidateName();
    };
    // Ejecutar validación inicial
    validateAndFeedback();
    revalidateName();
});
</script>
