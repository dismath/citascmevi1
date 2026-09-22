<div id="credentials-modal" style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(5px); align-items: center; justify-content: center; padding: 1rem;">
    <div class="card" style="max-width: 520px; width: 100%; margin: auto; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35); border: 1px solid var(--border-color, #e2e8f0); background: var(--bg-surface, #ffffff); overflow: hidden;">
        <div class="card-header" style="display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color, #e2e8f0); background: var(--bg-surface-2, #f8fafc);">
            <div style="display: flex; align-items: center; gap: 0.65rem;">
                <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(59, 130, 246, 0.12); color: var(--primary, #3b82f6); display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div>
                    <h4 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--text-main, #1e293b);">Credenciales de Acceso</h4>
                    <span style="font-size: 0.78rem; color: var(--text-muted, #64748b);">Gestión de usuario y contraseña</span>
                </div>
            </div>
            <button type="button" onclick="closeCredentialsModal()" style="background: transparent; border: none; font-size: 1.5rem; line-height: 1; color: var(--text-muted, #94a3b8); cursor: pointer; padding: 0.25rem 0.5rem; border-radius: 6px; transition: all 0.2s;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#94a3b8'">&times;</button>
        </div>
        <div class="card-body" style="padding: 1.5rem;">
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.85rem 1rem; border-radius: 10px; background: var(--bg-surface-2, #f8fafc); border: 1px solid var(--border-color, #e2e8f0); margin-bottom: 1.25rem;">
                <div>
                    <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; color: var(--text-muted, #64748b);">Nombre Completo</div>
                    <div id="cred-target-name" style="font-size: 1.05rem; font-weight: 700; color: var(--text-main, #0f172a);">Cargando...</div>
                </div>
                <span id="cred-target-badge" class="badge badge-info" style="font-size: 0.8rem; padding: 0.35rem 0.75rem; border-radius: 20px;">Médico</span>
            </div>
            <div id="cred-alert-box" style="display: none; margin-bottom: 1.25rem; padding: 0.85rem 1rem; border-radius: 10px; font-size: 0.9rem;"></div>
            <div class="form-group mb-3">
                <label class="form-label" style="font-weight: 600; font-size: 0.875rem; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.35rem;">
                    <i class="fa-solid fa-at text-primary"></i> Nombre de Usuario (Login)
                </label>
                <div style="display: flex; gap: 0.5rem;">
                    <input type="text" id="cred-input-username" class="form-control" readonly style="font-family: monospace; font-size: 0.95rem; font-weight: 700; background: var(--bg-surface-2, #f8fafc); color: var(--text-main, #0f172a); border-radius: 8px;">
                    <button type="button" class="btn btn-secondary btn-sm" id="btn-copy-username" onclick="copyCredField('cred-input-username', this)" title="Copiar nombre de usuario" style="white-space: nowrap; padding: 0 0.85rem; border-radius: 8px; font-weight: 600;">
                        <i class="fa-regular fa-copy"></i> Copiar
                    </button>
                </div>
                <small class="text-muted" style="font-size: 0.78rem;">Utilice este identificador para ingresar al sistema.</small>
            </div>
            <div class="form-group mb-3">
                <label class="form-label" style="font-weight: 600; font-size: 0.875rem; display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.35rem;">
                    <i class="fa-regular fa-envelope text-primary"></i> Correo Electrónico
                </label>
                <input type="text" id="cred-input-email" class="form-control" readonly style="font-size: 0.9rem; background: var(--bg-surface-2, #f8fafc); color: var(--text-main, #0f172a); border-radius: 8px;">
            </div>
            <div class="form-group mb-4">
                <label class="form-label" style="font-weight: 600; font-size: 0.875rem; display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.35rem;">
                    <span style="display: flex; align-items: center; gap: 0.4rem;"><i class="fa-solid fa-key text-primary"></i> Contraseña de Acceso</span>
                    <span id="cred-pass-status-badge" class="text-muted text-xs" style="font-weight: normal;">Cifrada en BD</span>
                </label>
                <div id="cred-pass-encrypted-box" style="display: flex; align-items: center; justify-content: space-between; background: var(--bg-surface-2, #f8fafc); padding: 0.65rem 1rem; border-radius: 8px; border: 1px dashed var(--border-color, #cbd5e1);">
                    <div style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-muted, #64748b); font-size: 0.85rem;">
                        <i class="fa-solid fa-lock" style="color: var(--text-muted);"></i>
                        <span style="letter-spacing: 2px; font-weight: bold;">••••••••••••</span>
                    </div>
                    <span class="text-xs text-muted" style="font-style: italic;">Protegida por cifrado irreversible</span>
                </div>
                <div id="cred-pass-revealed-box" style="display: none; margin-top: 0.25rem;">
                    <div style="display: flex; gap: 0.5rem;">
                        <div style="position: relative; flex: 1;">
                            <input type="text" id="cred-input-password" class="form-control" readonly style="font-family: monospace; font-size: 1.05rem; font-weight: 700; color: #16a34a; background: #f0fdf4; border-color: #86efac; padding-right: 2.5rem; border-radius: 8px;">
                            <button type="button" onclick="togglePasswordVisibility()" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #16a34a; cursor: pointer; padding: 4px;" title="Mostrar / Ocultar">
                                <i class="fa-solid fa-eye" id="cred-eye-icon"></i>
                            </button>
                        </div>
                        <button type="button" class="btn btn-success btn-sm" id="btn-copy-password" onclick="copyCredField('cred-input-password', this)" title="Copiar contraseña" style="white-space: nowrap; padding: 0 0.85rem; border-radius: 8px; font-weight: 600;">
                            <i class="fa-regular fa-copy"></i> Copiar
                        </button>
                    </div>
                    <small style="color: #15803d; font-size: 0.78rem; font-weight: 500; display: block; margin-top: 0.35rem;">
                        <i class="fa-solid fa-circle-check"></i> Anote o copie esta contraseña ahora. Se ha activado en la base de datos.
                    </small>
                </div>
            </div>
            <div style="display: flex; flex-direction: column; gap: 0.65rem;">
                <button type="button" id="btn-generate-cred-pwd" class="btn btn-warning w-100" onclick="requestNewPassword()" style="font-weight: 600; padding: 0.75rem 1rem; border-radius: 10px; display: flex; align-items: center; justify-content: center; gap: 0.5rem; font-size: 0.95rem; box-shadow: 0 2px 4px rgba(234, 179, 8, 0.2);">
                    <i class="fa-solid fa-arrows-rotate"></i> Generar una nueva contraseña
                </button>
                <button type="button" id="btn-copy-all-creds" class="btn btn-primary w-100" onclick="copyAllCredentials()" style="display: none; font-weight: 600; padding: 0.75rem 1rem; border-radius: 10px; align-items: center; justify-content: center; gap: 0.5rem; font-size: 0.95rem;">
                    <i class="fa-solid fa-clipboard-check"></i> Copiar Todo (Usuario y Contraseña)
                </button>
                <button type="button" class="btn btn-secondary w-100" onclick="closeCredentialsModal()" style="border-radius: 10px; font-size: 0.875rem;">
                    Cerrar ventana
                </button>
            </div>
        </div>
    </div>
</div>
<script>
let currentCredEntity = {
    type: '',
    id: 0,
    name: '',
    username: '',
    email: '',
    newPassword: ''
};
function openCredentialsModal(type, id, name) {
    currentCredEntity = {
        type: type,
        id: id,
        name: name || 'Usuario',
        username: '',
        email: '',
        newPassword: ''
    };
    // Actualizar Header
    document.getElementById('cred-target-name').innerText = currentCredEntity.name;
    const badge = document.getElementById('cred-target-badge');
    badge.innerText = (type === 'doctor') ? 'Médico' : 'Paciente';
    badge.className = 'badge ' + (type === 'doctor' ? 'badge-info' : 'badge-primary');
    // Reset UI
    document.getElementById('cred-input-username').value = 'Cargando...';
    document.getElementById('cred-input-email').value = 'Cargando...';
    document.getElementById('cred-input-password').value = '';
    document.getElementById('cred-pass-encrypted-box').style.display = 'flex';
    document.getElementById('cred-pass-revealed-box').style.display = 'none';
    document.getElementById('btn-copy-all-creds').style.display = 'none';
    document.getElementById('cred-alert-box').style.display = 'none';
    document.getElementById('cred-pass-status-badge').innerText = 'Cifrada en BD';
    document.getElementById('btn-generate-cred-pwd').disabled = false;
    document.getElementById('btn-generate-cred-pwd').innerHTML = '<i class="fa-solid fa-arrows-rotate"></i> Generar una nueva contraseña';
    // Mostrar modal
    const modal = document.getElementById('credentials-modal');
    modal.style.display = 'flex';
    // Petición AJAX para obtener credenciales actuales
    const formData = new FormData();
    formData.append('type', type);
    formData.append('id', id);
    fetch('<?= $baseUrl ?>/admin/credentials/get', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(res => {
        if (res.success && res.data) {
            currentCredEntity.username = res.data.username || '(Sin usuario)';
            currentCredEntity.email = res.data.email || '(Sin correo registrado)';
            document.getElementById('cred-input-username').value = currentCredEntity.username;
            document.getElementById('cred-input-email').value = currentCredEntity.email;
            // Si se acaba de crear una cuenta temporal para el usuario (caso sin user_id previo)
            if (res.data.is_new && res.data.temp_password) {
                currentCredEntity.newPassword = res.data.temp_password;
                showGeneratedPassword(res.data.temp_password, '¡Cuenta creada con éxito! Se ha generado la contraseña temporal.');
            }
        } else {
            showCredAlert('error', res.error || 'No se pudieron cargar las credenciales.');
        }
    })
    .catch(err => {
        console.error('Error cargando credenciales:', err);
        showCredAlert('error', 'Error al conectar con el servidor.');
    });
}
function closeCredentialsModal() {
    document.getElementById('credentials-modal').style.display = 'none';
}
function requestNewPassword() {
    const btn = document.getElementById('btn-generate-cred-pwd');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generando contraseña...';
    const formData = new FormData();
    formData.append('type', currentCredEntity.type);
    formData.append('id', currentCredEntity.id);
    formData.append('csrf_token', '<?= \App\Helpers\Session::generateCsrf() ?>');
    fetch('<?= $baseUrl ?>/admin/credentials/generate-password', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(res => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-arrows-rotate"></i> Generar otra contraseña';
        if (res.success && res.data) {
            currentCredEntity.username = res.data.username;
            currentCredEntity.newPassword = res.data.new_password;
            document.getElementById('cred-input-username').value = res.data.username;
            showGeneratedPassword(res.data.new_password, res.message || '¡Nueva contraseña generada y activada exitosamente!');
        } else {
            showCredAlert('error', res.error || 'No se pudo generar la contraseña.');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-arrows-rotate"></i> Generar una nueva contraseña';
        console.error('Error generando contraseña:', err);
        showCredAlert('error', 'Error de comunicación con el servidor.');
    });
}
function showGeneratedPassword(password, message) {
    document.getElementById('cred-pass-encrypted-box').style.display = 'none';
    const revealedBox = document.getElementById('cred-pass-revealed-box');
    revealedBox.style.display = 'block';
    const pwdInput = document.getElementById('cred-input-password');
    pwdInput.value = password;
    pwdInput.type = 'text';
    document.getElementById('cred-eye-icon').className = 'fa-solid fa-eye';
    document.getElementById('cred-pass-status-badge').innerHTML = '<span style="color: #16a34a; font-weight: 600;"><i class="fa-solid fa-check"></i> Activa en el sistema</span>';
    document.getElementById('btn-copy-all-creds').style.display = 'flex';
    showCredAlert('success', message);
}
function togglePasswordVisibility() {
    const input = document.getElementById('cred-input-password');
    const icon = document.getElementById('cred-eye-icon');
    if (input.type === 'text') {
        input.type = 'password';
        icon.className = 'fa-solid fa-eye-slash';
    } else {
        input.type = 'text';
        icon.className = 'fa-solid fa-eye';
    }
}
function showCredAlert(type, message) {
    const box = document.getElementById('cred-alert-box');
    box.style.display = 'block';
    if (type === 'success') {
        box.style.background = '#dcfce7';
        box.style.border = '1px solid #86efac';
        box.style.color = '#15803d';
        box.innerHTML = '<i class="fa-solid fa-circle-check"></i> ' + message;
    } else {
        box.style.background = '#fee2e2';
        box.style.border = '1px solid #fca5a5';
        box.style.color = '#b91c1c';
        box.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + message;
    }
}
function copyCredField(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input || !input.value) return;
    navigator.clipboard.writeText(input.value).then(() => {
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> ¡Copiado!';
        btn.classList.remove('btn-secondary');
        btn.classList.add('btn-success');
        setTimeout(() => {
            btn.innerHTML = originalHtml;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-secondary');
        }, 1800);
    }).catch(err => {
        console.error('Error al copiar:', err);
    });
}
function copyAllCredentials() {
    const username = document.getElementById('cred-input-username').value;
    const password = document.getElementById('cred-input-password').value;
    const name = currentCredEntity.name;
    const role = (currentCredEntity.type === 'doctor') ? 'Médico' : 'Paciente';
    const loginUrl = window.location.origin + '<?= $baseUrl ?>/login';
    const textToCopy = `📋 *Credenciales de Acceso*\n` +
                       `👤 Nombre: ${name} (${role})\n` +
                       `🔑 Usuario: ${username}\n` +
                       `🔒 Contraseña: ${password}\n` +
                       `🌐 Iniciar Sesión: ${loginUrl}`;
    navigator.clipboard.writeText(textToCopy).then(() => {
        const btn = document.getElementById('btn-copy-all-creds');
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> ¡Datos copiados al portapapeles!';
        setTimeout(() => {
            btn.innerHTML = orig;
        }, 2200);
    }).catch(err => {
        console.error('Error al copiar todo:', err);
    });
}
// Cerrar con tecla Escape o clic fuera del modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeCredentialsModal();
});
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('credentials-modal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeCredentialsModal();
        });
    }
});
</script>
