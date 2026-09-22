<div class="card max-w-4xl mx-auto p-0">
    <div class="border-bottom px-3 pt-3" style="background: var(--surface-1);">
        <ul class="d-flex gap-3" style="list-style: none; padding: 0; margin: 0; position: relative; bottom: -1px;">
            <li>
                <button type="button" class="tab-btn active" onclick="switchTab('general')" style="background: none; border: 1px solid transparent; border-bottom: none; padding: 0.75rem 1.5rem; cursor: pointer; border-radius: var(--radius-md) var(--radius-md) 0 0; color: var(--text-2); font-weight: 500;">
                    <i class="fa-solid fa-building-columns"></i> General y Pagos
                </button>
            </li>
            <li>
                <button type="button" class="tab-btn" onclick="switchTab('smtp')" style="background: none; border: 1px solid transparent; border-bottom: none; padding: 0.75rem 1.5rem; cursor: pointer; border-radius: var(--radius-md) var(--radius-md) 0 0; color: var(--text-2); font-weight: 500;">
                    <i class="fa-solid fa-envelope"></i> Servidor de Correo
                </button>
            </li>
            <li>
                <button type="button" class="tab-btn" onclick="switchTab('catalogs')" style="background: none; border: 1px solid transparent; border-bottom: none; padding: 0.75rem 1.5rem; cursor: pointer; border-radius: var(--radius-md) var(--radius-md) 0 0; color: var(--text-2); font-weight: 500;">
                    <i class="fa-solid fa-tags"></i> Tipos de Catálogo
                </button>
            </li>
            <li>
                <button type="button" class="tab-btn" onclick="switchTab('security')" style="background: none; border: 1px solid transparent; border-bottom: none; padding: 0.75rem 1.5rem; cursor: pointer; border-radius: var(--radius-md) var(--radius-md) 0 0; color: var(--text-2); font-weight: 500;">
                    <i class="fa-solid fa-shield-halved"></i> Seguridad
                </button>
            </li>
            <li>
                <button type="button" class="tab-btn" onclick="switchTab('empresa')" style="background: none; border: 1px solid transparent; border-bottom: none; padding: 0.75rem 1.5rem; cursor: pointer; border-radius: var(--radius-md) var(--radius-md) 0 0; color: var(--text-2); font-weight: 500;">
                    <i class="fa-solid fa-building"></i> Empresa
                </button>
            </li>
        </ul>
    </div>
    <style>
        .tab-btn.active {
            background: #fff !important;
            border-color: var(--border) !important;
            border-bottom-color: #fff !important;
            color: var(--primary) !important;
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        /* Modals */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: #fff; padding: 1.5rem; border-radius: var(--radius-md); width: 100%; max-width: 400px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
    </style>
    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            const targetContent = document.getElementById('tab-' + tabId);
            if (targetContent) targetContent.classList.add('active');
            
            const targetBtn = document.querySelector(".tab-btn[onclick*=\"'" + tabId + "'\"]");
            if (targetBtn) {
                targetBtn.classList.add('active');
            } else if (window.event && window.event.currentTarget) {
                window.event.currentTarget.classList.add('active');
            }

            if (history.replaceState) {
                history.replaceState(null, '', '#' + tabId);
            }
        }

        window.addEventListener('DOMContentLoaded', () => {
            const hash = window.location.hash.replace('#', '');
            if (hash && document.getElementById('tab-' + hash)) {
                switchTab(hash);
            }
        });
    </script>
    <div class="card-body">
        <div id="tab-general" class="tab-content active">
            <div class="alert alert-info mb-3">
                Estos datos se mostrarán a los pacientes en el paso final del agendamiento para que realicen el pago de su consulta o examen.
            </div>
            <form action="<?= $baseUrl ?>/admin/settings/update" method="POST">
                <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                <input type="hidden" name="form_section" value="general">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Institución Financiera</label>
                        <input type="text" name="bank_name" class="form-control" value="<?= htmlspecialchars($settings['bank_name'] ?? 'Banco Pichincha') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tipo de Cuenta</label>
                        <select name="bank_account_type" class="form-control">
                            <option value="Corriente" <?= ($settings['bank_account_type'] ?? '') == 'Corriente' ? 'selected' : '' ?>>Corriente</option>
                            <option value="Ahorros" <?= ($settings['bank_account_type'] ?? '') == 'Ahorros' ? 'selected' : '' ?>>Ahorros</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Número de Cuenta</label>
                        <input type="text" name="bank_account_number" class="form-control" value="<?= htmlspecialchars($settings['bank_account_number'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Beneficiario / Titular</label>
                        <input type="text" name="bank_account_owner" class="form-control" value="<?= htmlspecialchars($settings['bank_account_owner'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">RUC o Cédula</label>
                        <input type="text" name="bank_id_number" class="form-control" value="<?= htmlspecialchars($settings['bank_id_number'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Correo para Comprobantes</label>
                        <input type="email" name="bank_email" class="form-control" value="<?= htmlspecialchars($settings['bank_email'] ?? '') ?>">
                    </div>
                </div>
                <h4 class="mt-4 mb-3 border-bottom pb-1"><i class="fa-solid fa-calendar-days text-primary"></i> Reagendamiento de Citas</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Máximo de Reagendamientos por Cita</label>
                        <input type="number" name="max_reschedules" class="form-control" min="0" value="<?= htmlspecialchars($settings['max_reschedules'] ?? '1') ?>">
                        <small class="text-muted">Número máximo de veces que se puede reagendar una misma cita (0 para deshabilitar reagendamientos).</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Anticipación Mínima (Horas)</label>
                        <input type="number" name="reschedule_hours_before" class="form-control" min="0" value="<?= htmlspecialchars($settings['reschedule_hours_before'] ?? '24') ?>">
                        <small class="text-muted">Horas previas a la cita en las que todavía se permite el reagendamiento.</small>
                    </div>
                </div>
                <h4 class="mt-4 mb-3 border-bottom pb-1"><i class="fa-solid fa-gear text-primary"></i> Ajustes Generales</h4>
                <div class="form-group d-flex align-center">
                    <label class="form-label mb-0 d-flex align-center gap-1" style="cursor:pointer; font-weight: normal;">
                        <input type="checkbox" name="show_fee" value="1" <?= (!isset($settings['show_fee']) || $settings['show_fee'] == '1') ? 'checked' : '' ?> style="width: 18px; height: 18px;">
                        <strong>Mostrar Tarifas:</strong> Activar para mostrar los precios de consulta a los pacientes en el agendamiento web.
                    </label>
                </div>
                <div class="form-group d-flex align-center mt-3">
                    <label class="form-label mb-0 d-flex align-center gap-1" style="cursor:pointer; font-weight: normal;">
                        <input type="checkbox" name="allow_weekends" value="1" <?= (isset($settings['allow_weekends']) && $settings['allow_weekends'] == '1') ? 'checked' : '' ?> style="width: 18px; height: 18px;">
                        <strong>Permitir Fines de Semana:</strong> Activar para habilitar el agendamiento de citas los días Sábado y Domingo.
                    </label>
                </div>
                <div class="text-right mt-4">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Guardar Cambios</button>
                </div>
            </form>
        </div>
        <div id="tab-smtp" class="tab-content">
            <div class="alert alert-warning mb-3">
                <i class="fa-solid fa-triangle-exclamation"></i> Configure los datos SMTP para que el sistema pueda enviar notificaciones y comprobantes por correo electrónico.
            </div>
            <form action="<?= $baseUrl ?>/admin/settings/update" method="POST">
                <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                <input type="hidden" name="form_section" value="smtp">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Servidor SMTP (Host)</label>
                        <input type="text" name="smtp_host" class="form-control" value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>" placeholder="Ej: smtp.gmail.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Puerto SMTP</label>
                        <input type="text" name="smtp_port" class="form-control" value="<?= htmlspecialchars($settings['smtp_port'] ?? '587') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Usuario SMTP</label>
                        <input type="text" name="smtp_user" class="form-control" value="<?= htmlspecialchars($settings['smtp_user'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contraseña SMTP</label>
                        <input type="password" name="smtp_pass" class="form-control" value="<?= htmlspecialchars($settings['smtp_pass'] ?? '') ?>" autocomplete="new-password">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Cifrado (Seguridad)</label>
                        <select name="smtp_secure" class="form-control">
                            <option value="tls" <?= ($settings['smtp_secure'] ?? '') == 'tls' ? 'selected' : '' ?>>TLS — Puerto 587 (Recomendado)</option>
                            <option value="ssl" <?= ($settings['smtp_secure'] ?? '') == 'ssl' ? 'selected' : '' ?>>SSL — Puerto 465</option>
                            <option value="" <?= ($settings['smtp_secure'] ?? '') == '' ? 'selected' : '' ?>>Ninguno — Puerto 25 (no recomendado)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Remitente (From Name)</label>
                        <input type="text" name="smtp_from_name" class="form-control" value="<?= htmlspecialchars($settings['smtp_from_name'] ?? 'Portal Cmevi Pro') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Correo Remitente (From Email)</label>
                    <input type="email" name="smtp_from_email" class="form-control" value="<?= htmlspecialchars($settings['smtp_from_email'] ?? '') ?>" placeholder="Ej: no-reply@cmevi.com">
                </div>

                <!-- Guía rápida para mail.cmevi.com -->
                <div style="background: #f0f9ff; border-left: 4px solid #0ea5e9; border-radius: 8px; padding: 1rem 1.25rem; margin: 0.75rem 0;">
                    <p style="margin: 0 0 0.6rem; font-weight: 600; color: #0369a1; font-size: 0.9rem;"><i class="fa-solid fa-circle-info"></i> Configuración correcta para <strong>mail.cmevi.com</strong> (Exim/cPanel):</p>
                    <table style="font-size: 0.83rem; border-collapse: collapse; width: 100%;">
                        <tr>
                            <td style="padding: 3px 14px 3px 0; color: #0f766e; font-weight: 700; white-space: nowrap;">✅ Opción A (recomendada):</td>
                            <td style="padding: 3px 0; color: #1e293b;">Host: <code>mail.cmevi.com</code> &nbsp;| Puerto: <code>587</code> &nbsp;| Cifrado: <strong>TLS</strong></td>
                        </tr>
                        <tr>
                            <td style="padding: 3px 14px 3px 0; color: #0f766e; font-weight: 700; white-space: nowrap;">✅ Opción B (SSL):</td>
                            <td style="padding: 3px 0; color: #1e293b;">Host: <code>mail.cmevi.com</code> &nbsp;| Puerto: <code>465</code> &nbsp;| Cifrado: <strong>SSL</strong></td>
                        </tr>
                        <tr>
                            <td style="padding: 3px 14px 3px 0; color: #dc2626; font-weight: 700; white-space: nowrap;">❌ Puerto 25:</td>
                            <td style="padding: 3px 0; color: #64748b;">Bloqueado por el ISP. No usar.</td>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding: 8px 0 0; color: #475569; font-size: 0.8rem;">
                                <i class="fa-solid fa-key" style="color: #f59e0b;"></i> El <strong>Usuario SMTP</strong> debe ser la dirección completa de correo (ej: <code>notificaciones@cmevi.com</code>), no solo el nombre de usuario.
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- ═══════════════════════════════════════════════════════════ -->
                <!-- MODO DE ENVÍO: Síncrono o Asíncrono (en cola)              -->
                <!-- ═══════════════════════════════════════════════════════════ -->
                <div style="border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.1rem 1.25rem; margin: 1.1rem 0; background: #fafbfc;">
                    <p style="font-weight: 700; color: #1e293b; margin: 0 0 0.5rem; font-size: 0.95rem;">
                        <i class="fa-solid fa-paper-plane" style="color: #6366f1;"></i> Modo de Envío de Correos
                    </p>
                    <p style="font-size: 0.82rem; color: #64748b; margin: 0 0 0.9rem; line-height: 1.5;">
                        Controla cómo el sistema envía los correos de notificaciones (citas, confirmaciones, cancelaciones, etc.).
                    </p>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 600;">Modo de Envío</label>
                        <select name="mail_send_mode" class="form-control" style="max-width: 420px;">
                            <option value="sync" <?= ($settings['mail_send_mode'] ?? 'sync') === 'sync' ? 'selected' : '' ?>>
                                ⚡ Síncrono — Envío inmediato (recomendado)
                            </option>
                            <option value="queue" <?= ($settings['mail_send_mode'] ?? 'sync') === 'queue' ? 'selected' : '' ?>>
                                🕐 Asíncrono — En cola (requiere worker PHP activo)
                            </option>
                        </select>
                    </div>

                    <?php $currentMode = $settings['mail_send_mode'] ?? 'sync'; ?>

                    <!-- Descripción modo síncrono -->
                    <div id="mode-info-sync" style="margin-top: 0.75rem; background: #f0fdf4; border-left: 3px solid #22c55e; border-radius: 6px; padding: 0.7rem 1rem; font-size: 0.82rem; <?= $currentMode === 'queue' ? 'display:none;' : '' ?>">
                        <strong style="color: #15803d;">⚡ Síncrono (activo)</strong><br>
                        El correo se envía <strong>en el momento exacto</strong> en que el sistema guarda el cambio (confirmación, cancelación, etc.).<br>
                        <span style="color: #166534;">✔ Más confiable — los correos siempre llegan.</span><br>
                        <span style="color: #92400e;">⚠ Si el servidor SMTP es lento, la página puede tardar unos segundos más en responder.</span>
                    </div>

                    <!-- Descripción modo asíncrono -->
                    <div id="mode-info-queue" style="margin-top: 0.75rem; background: #fff7ed; border-left: 3px solid #f59e0b; border-radius: 6px; padding: 0.7rem 1rem; font-size: 0.82rem; <?= $currentMode !== 'queue' ? 'display:none;' : '' ?>">
                        <strong style="color: #b45309;">🕐 Asíncrono en Cola (activo)</strong><br>
                        El correo se <strong>encola en background_jobs</strong> y un proceso PHP separado lo procesa en segundo plano.<br>
                        <span style="color: #15803d;">✔ La página responde al instante, sin esperar al servidor SMTP.</span><br>
                        <span style="color: #dc2626;">⚠ Requiere que el worker.php se ejecute correctamente. Si el worker falla, los correos no se envían.</span><br>
                        <span style="color: #dc2626;">⚠ En XAMPP/Windows, el worker puede no iniciarse automáticamente desde Apache.</span>
                    </div>

                    <script>
                        document.querySelector('select[name="mail_send_mode"]').addEventListener('change', function() {
                            document.getElementById('mode-info-sync').style.display  = this.value === 'sync'  ? '' : 'none';
                            document.getElementById('mode-info-queue').style.display = this.value === 'queue' ? '' : 'none';
                        });
                    </script>
                </div>

                <div class="d-flex mt-4" style="gap: 0.75rem;">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Guardar SMTP</button>
                    <a href="<?= $baseUrl ?>/admin/settings/test-email" class="btn btn-secondary" style="text-decoration: none;">
                        <i class="fa-solid fa-paper-plane"></i> Enviar Correo de Prueba
                    </a>
                </div>
            </form>
        </div>
        <div id="tab-catalogs" class="tab-content">
            <div class="grid-2">
                <div>
                    <h4 class="mb-3">Tipos Existentes</h4>
                    <table class="w-full">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Color</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($catalogTypes)): ?>
                                <tr><td colspan="4" class="text-center text-muted">No hay tipos registrados</td></tr>
                            <?php endif; ?>
                            <?php foreach($catalogTypes as $ct): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($ct['code']) ?></strong></td>
                                <td><?= htmlspecialchars($ct['name']) ?></td>
                                <td><span class="badge badge-<?= $ct['color'] ?>"><?= $ct['color'] ?></span></td>
                                <td class="d-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-info" onclick="editCatalogType('<?= htmlspecialchars($ct['code']) ?>', '<?= htmlspecialchars($ct['name']) ?>', '<?= htmlspecialchars($ct['color']) ?>')" title="Editar">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <form action="<?= $baseUrl ?>/admin/settings/catalog-type/delete/<?= $ct['code'] ?>" method="POST" onsubmit="return confirm('¿Eliminar este tipo?')">
                                        <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="bg-surface-2 p-3 rounded-md">
                    <h4 class="mb-3" id="catalog-form-title">Nuevo Tipo de Catálogo</h4>
                    <form id="catalog-form" action="<?= $baseUrl ?>/admin/settings/catalog-type/store" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                        <div class="form-group">
                            <label class="form-label">Código (Letras/Números, sin espacios) *</label>
                            <input type="text" name="code" id="cat-code" class="form-control" required placeholder="Ej: LAB, IMAGEN">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nombre para Mostrar *</label>
                            <input type="text" name="name" id="cat-name" class="form-control" required placeholder="Ej: Laboratorio Clínico">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Color del Badge *</label>
                            <select name="color" id="cat-color" class="form-control">
                                <option value="primary">Azul (Primary)</option>
                                <option value="info">Celeste (Info)</option>
                                <option value="success">Verde (Success)</option>
                                <option value="warning">Amarillo (Warning)</option>
                                <option value="danger">Rojo (Danger)</option>
                                <option value="secondary">Gris (Secondary)</option>
                            </select>
                        </div>
                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" id="cat-submit-btn" class="btn btn-primary" style="flex: 1;"><i class="fa-solid fa-plus"></i> Añadir Tipo</button>
                            <button type="button" id="cat-cancel-btn" class="btn btn-secondary" style="flex: 1; display: none;" onclick="cancelEditCatalogType()">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div id="tab-security" class="tab-content">
            <div class="alert alert-info mb-3">
                <i class="fa-solid fa-circle-info"></i> Configure las reglas de validación y seguridad que se aplican a los formularios del sistema.
            </div>
            <form action="<?= $baseUrl ?>/admin/settings/update" method="POST">
                <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                <input type="hidden" name="form_section" value="security">
                <h4 class="mb-3 border-bottom pb-1"><i class="fa-solid fa-id-card text-primary"></i> Validación de Documentos</h4>
                <div class="form-group">
                    <div class="d-flex align-center gap-2" style="padding: 1rem; background: var(--surface-2); border-radius: var(--radius-md); border: 1px solid var(--border);">
                        <label class="form-label mb-0 d-flex align-center gap-2" style="cursor: pointer; font-weight: normal; flex: 1;">
                            <input type="checkbox" name="validate_ecuadorian_id" value="1"
                                <?= (!isset($settings['validate_ecuadorian_id']) || $settings['validate_ecuadorian_id'] == '1') ? 'checked' : '' ?>
                                style="width: 20px; height: 20px; accent-color: var(--primary);">
                            <div>
                                <strong style="display: block; margin-bottom: 0.25rem;">Validar Cédula y RUC Ecuatoriano</strong>
                                <span class="text-muted" style="font-size: 0.875rem;">Cuando está activado, el sistema verificará algorítmicamente que los números de <strong>Cédula</strong> (Módulo 10, 10 dígitos) y <strong>RUC</strong> (Módulo 10/11, 13 dígitos) ingresados en formularios de pacientes, médicos y reservas sean válidos según el formato ecuatoriano. Se soportan los tres tipos de RUC: Persona Natural, Sociedad Pública y Sociedad Privada.</span>
                            </div>
                        </label>
                    </div>
                </div>
                <div class="alert alert-warning mt-3 mb-4" style="font-size: 0.875rem;">
                    <i class="fa-solid fa-triangle-exclamation"></i> <strong>Nota:</strong> Si desactiva esta validación, el sistema aceptará cualquier número de identificación sin verificar su autenticidad. Las opciones de Pasaporte e Identificación Extranjera estarán siempre disponibles para pacientes con documentos no ecuatorianos.
                </div>
                <h4 class="mb-3 border-bottom pb-1 mt-4"><i class="fa-solid fa-shield-halved text-primary"></i> Autenticación en Dos Pasos (2FA) por Correo</h4>
                <div class="form-group mb-3">
                    <div class="d-flex align-center gap-2" style="padding: 1rem; background: var(--surface-2); border-radius: var(--radius-md); border: 1px solid var(--border);">
                        <label class="form-label mb-0 d-flex align-center gap-2" style="cursor: pointer; font-weight: normal; flex: 1;">
                            <input type="checkbox" name="two_factor_enabled" id="chk-2fa-master" value="1"
                                <?= (!isset($settings['two_factor_enabled']) || $settings['two_factor_enabled'] == '1') ? 'checked' : '' ?>
                                onchange="toggle2FASuboptions(this.checked)"
                                style="width: 20px; height: 20px; accent-color: var(--primary);">
                            <div>
                                <strong style="display: block; margin-bottom: 0.25rem;">Activar Autenticación en Dos Pasos (2FA)</strong>
                                <span class="text-muted" style="font-size: 0.875rem;">
                                    Al activar esta opción, los usuarios seleccionados deberán ingresar un código de seguridad de 6 dígitos que recibirán en su correo electrónico registrado para poder iniciar sesión.
                                </span>
                            </div>
                        </label>
                    </div>
                </div>
                <div id="container-2fa-roles" class="mb-4 ps-4" style="border-left: 3px solid var(--primary); margin-left: 1rem; padding-left: 1.25rem; <?= (isset($settings['two_factor_enabled']) && $settings['two_factor_enabled'] == '0') ? 'display: none;' : '' ?>">
                    <p class="mb-2" style="font-size: 0.875rem; font-weight: 600; color: var(--text-2);">Perfiles requeridos para 2FA:</p>
                    <div class="d-flex flex-column gap-2">
                        <label class="d-flex align-center gap-2" style="cursor: pointer; font-size: 0.9rem;">
                            <input type="checkbox" name="two_factor_admin" value="1"
                                <?= (!isset($settings['two_factor_admin']) || $settings['two_factor_admin'] == '1') ? 'checked' : '' ?>
                                style="width: 17px; height: 17px; accent-color: var(--primary);">
                            <span><strong>Administradores y Recepcionistas</strong> (Recomendado)</span>
                        </label>
                        <label class="d-flex align-center gap-2" style="cursor: pointer; font-size: 0.9rem;">
                            <input type="checkbox" name="two_factor_doctor" value="1"
                                <?= (!isset($settings['two_factor_doctor']) || $settings['two_factor_doctor'] == '1') ? 'checked' : '' ?>
                                style="width: 17px; height: 17px; accent-color: var(--primary);">
                            <span><strong>Médicos Especialistas</strong> (Recomendado)</span>
                        </label>
                        <label class="d-flex align-center gap-2" style="cursor: pointer; font-size: 0.9rem;">
                            <input type="checkbox" name="two_factor_patient" value="1"
                                <?= (isset($settings['two_factor_patient']) && $settings['two_factor_patient'] == '1') ? 'checked' : '' ?>
                                style="width: 17px; height: 17px; accent-color: var(--primary);">
                            <span><strong>Pacientes</strong> (Opcional - Requiere que el paciente tenga correo registrado)</span>
                        </label>
                    </div>
                </div>
                <h4 class="mb-3 border-bottom pb-1 mt-4"><i class="fa-solid fa-bell text-primary"></i> Notificaciones de Seguridad y Alertas de Acceso</h4>
                <div class="form-group mb-4">
                    <div class="d-flex align-center gap-2" style="padding: 1rem; background: var(--surface-2); border-radius: var(--radius-md); border: 1px solid var(--border);">
                        <label class="form-label mb-0 d-flex align-center gap-2" style="cursor: pointer; font-weight: normal; flex: 1;">
                            <input type="checkbox" name="login_alert_email" value="1"
                                <?= (!isset($settings['login_alert_email']) || $settings['login_alert_email'] == '1') ? 'checked' : '' ?>
                                style="width: 20px; height: 20px; accent-color: var(--primary);">
                            <div>
                                <strong style="display: block; margin-bottom: 0.25rem;">Enviar Alerta por Correo ante Nuevos Inicios de Sesión</strong>
                                <span class="text-muted" style="font-size: 0.875rem;">
                                    Envía automáticamente una notificación por correo electrónico con detalles de seguridad (Fecha, Hora, Dirección IP y Dispositivo) cada vez que se detecta un inicio de sesión exitoso.
                                </span>
                            </div>
                        </label>
                    </div>
                </div>
                <script>
                function toggle2FASuboptions(isEnabled) {
                    const el = document.getElementById('container-2fa-roles');
                    if (el) {
                        el.style.display = isEnabled ? 'block' : 'none';
                    }
                }
                </script>
                <div class="text-right mt-4">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Guardar Configuración de Seguridad</button>
                </div>
            </form>
        </div>
        <div id="tab-empresa" class="tab-content">
            <div class="alert alert-info mb-3">
                <i class="fa-solid fa-building"></i> Ingrese la información y el logotipo de la empresa. Estos datos se utilizarán en el encabezado del sistema, documentos, reportes y recibos generados.
            </div>
            <form action="<?= $baseUrl ?>/admin/settings/update" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                <input type="hidden" name="form_section" value="empresa">

                <!-- Sección de Logotipo Institucional -->
                <div class="card p-3 mb-4" style="background: var(--surface-1, #f8fafc); border: 1px solid var(--border, #e2e8f0); border-radius: var(--radius-md);">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <label class="form-label mb-0" style="font-weight: 600; font-size: 1rem; color: var(--text-1, #1e293b);">
                            <i class="fa-solid fa-image text-primary"></i> Logotipo de la Empresa
                        </label>
                        <span class="badge" style="background: #e0f2fe; color: #0369a1; font-weight: 500; font-size: 0.75rem; padding: 4px 10px; border-radius: 9999px; display: inline-flex; align-items: center; gap: 4px;">
                            <i class="fa-solid fa-shield-halved"></i> Compresión & Seguridad Criptográfica
                        </span>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; align-items: start;">
                        <!-- Columna: Logo Actual -->
                        <div>
                            <div style="font-size: 0.8rem; font-weight: 600; color: var(--text-2, #64748b); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                Logo Actual en Sistema
                            </div>
                            <?php if (!empty($settings['company_logo'])): ?>
                                <div id="current-logo-container" style="background: #ffffff; border: 1px solid var(--border, #cbd5e1); border-radius: 8px; padding: 1.25rem; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                                    <div style="min-height: 100px; display: flex; align-items: center; justify-content: center; background-image: radial-gradient(#e2e8f0 1px, transparent 1px); background-size: 12px 12px; border-radius: 6px; padding: 0.5rem;">
                                        <img src="<?= $baseUrl ?>/<?= htmlspecialchars($settings['company_logo']) ?>" 
                                             alt="Logotipo actual" 
                                             id="current-logo-img"
                                             style="max-height: 90px; max-width: 100%; object-fit: contain; transition: opacity 0.2s ease;">
                                    </div>
                                    <div class="mt-2 text-muted" style="font-size: 0.78rem; word-break: break-all;">
                                        <i class="fa-solid fa-circle-check text-success"></i> <?= htmlspecialchars(basename($settings['company_logo'])) ?>
                                    </div>
                                    <div class="mt-2 pt-2 border-top">
                                        <label style="display: inline-flex; align-items: center; gap: 0.4rem; cursor: pointer; color: #dc2626; font-size: 0.85rem; font-weight: 500; margin: 0;">
                                            <input type="checkbox" name="remove_company_logo" value="1" id="remove_company_logo" onchange="toggleRemoveLogo(this.checked)">
                                            <i class="fa-solid fa-trash-can"></i> Eliminar logo y usar icono del sistema
                                        </label>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div id="current-logo-container" style="background: #ffffff; border: 2px dashed var(--border, #cbd5e1); border-radius: 8px; padding: 1.75rem 1rem; text-align: center; color: #94a3b8;">
                                    <i class="fa-solid fa-hospital fa-2x mb-2" style="color: #cbd5e1;"></i>
                                    <p style="margin: 0; font-size: 0.88rem; font-weight: 500; color: #64748b;">No hay logotipo cargado</p>
                                    <small style="font-size: 0.75rem; color: #94a3b8;">Actualmente se muestra el icono predeterminado</small>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Columna: Cargar o Cambiar Logo -->
                        <div>
                            <div style="font-size: 0.8rem; font-weight: 600; color: var(--text-2, #64748b); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                <?= !empty($settings['company_logo']) ? 'Reemplazar Logotipo' : 'Subir Nuevo Logotipo' ?>
                            </div>
                            
                            <div id="drop-zone" style="border: 2px dashed #3b82f6; background: #eff6ff; border-radius: 8px; padding: 1.25rem; text-align: center; position: relative; transition: all 0.2s ease;">
                                <input type="file" name="company_logo" id="company_logo_input" 
                                       accept="image/png, image/jpeg, image/webp" 
                                       onchange="handleLogoSelect(this)"
                                       style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 5;">
                                
                                <div id="upload-prompt">
                                    <i class="fa-solid fa-cloud-arrow-up fa-2x mb-2" style="color: #3b82f6;"></i>
                                    <p style="margin: 0; font-weight: 600; color: #1e40af; font-size: 0.9rem;">
                                        Haga clic o arrastre el archivo de imagen
                                    </p>
                                    <p style="margin: 4px 0 0 0; font-size: 0.78rem; color: #64748b;">
                                        Formatos válidos: <strong>PNG, JPG, WEBP</strong> (Máximo 5 MB)
                                    </p>
                                </div>

                                <!-- Vista previa de imagen seleccionada -->
                                <div id="new-logo-preview-wrap" style="display: none; margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px dashed #bfdbfe; position: relative; z-index: 10;">
                                    <span class="badge" style="background: #22c55e; color: #fff; font-size: 0.72rem; padding: 3px 8px; border-radius: 9999px; display: inline-block; margin-bottom: 6px;">
                                        <i class="fa-solid fa-eye"></i> Nueva imagen seleccionada
                                    </span>
                                    <div style="background: #ffffff; border: 1px solid #93c5fd; border-radius: 6px; padding: 8px; display: inline-block; max-width: 100%;">
                                        <img id="new-logo-preview" src="#" alt="Vista previa" style="max-height: 85px; max-width: 100%; object-fit: contain; display: block; margin: 0 auto;">
                                    </div>
                                    <div id="new-logo-info" style="font-size: 0.76rem; color: #1e3a8a; margin-top: 6px; font-weight: 500;"></div>
                                    <button type="button" class="btn btn-sm btn-outline-danger mt-2" onclick="cancelLogoUpload(event)" style="font-size: 0.75rem; padding: 2px 10px; height: 26px; border-radius: 4px;">
                                        <i class="fa-solid fa-xmark"></i> Cancelar cambio
                                    </button>
                                </div>
                            </div>

                            <div class="mt-2" style="font-size: 0.78rem; color: #64748b; line-height: 1.4;">
                                <i class="fa-solid fa-shield-halved text-primary"></i> <strong>Seguridad y Optimización:</strong> La imagen se comprime automáticamente (máx 600x300 px), optimizando el peso, y se renombra con un <strong>token criptográfico seguro</strong> para evitar sobreescrituras e inyección de archivos.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Nombre de la Empresa</label>
                        <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($settings['company_name'] ?? '') ?>" placeholder="Ej: Clínica San José">
                    </div>
                    <div class="form-group">
                        <label class="form-label">RUC de la Empresa</label>
                        <input type="text" name="company_ruc" class="form-control" value="<?= htmlspecialchars($settings['company_ruc'] ?? '') ?>" placeholder="Ej: 1790000000001">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="company_phone" class="form-control" value="<?= htmlspecialchars($settings['company_phone'] ?? '') ?>" placeholder="Ej: 02-222-3333">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Correo Electrónico</label>
                        <input type="email" name="company_email" class="form-control" value="<?= htmlspecialchars($settings['company_email'] ?? '') ?>" placeholder="Ej: contacto@clinica.com">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Sitio Web</label>
                        <input type="url" name="company_website" class="form-control" value="<?= htmlspecialchars($settings['company_website'] ?? '') ?>" placeholder="Ej: https://www.clinica.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Dirección Completa</label>
                        <input type="text" name="company_address" class="form-control" value="<?= htmlspecialchars($settings['company_address'] ?? '') ?>" placeholder="Ej: Av. Principal 123 y Secundaria, Quito, Ecuador">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <i class="fa-solid fa-star text-warning"></i> URL de Encuesta de Satisfacción
                    </label>
                    <input type="url" name="survey_url" class="form-control"
                           value="<?= htmlspecialchars($settings['survey_url'] ?? '') ?>"
                           placeholder="Ej: https://forms.google.com/encuesta-satisfaccion">
                    <small class="text-muted">
                        Este enlace se incluirá como botón en el correo automático que se envía al paciente al completar una cita.
                        Si se deja en blanco, el botón de encuesta no aparecerá en el correo.
                    </small>
                </div>
                <div class="text-right mt-4">
                    <button type="submit" class="btn btn-primary" style="height: 38px; display: inline-flex; align-items: center; gap: 0.5rem;"><i class="fa-solid fa-save"></i> Guardar Información de Empresa</button>
                </div>
            </form>
        </div>
        <script>
            function handleLogoSelect(input) {
                if (!input.files || !input.files[0]) return;
                const file = input.files[0];
                
                // Validar tamaño máx 5MB
                if (file.size > 5 * 1024 * 1024) {
                    alert('El archivo seleccionado excede el tamaño máximo permitido de 5 MB.');
                    input.value = '';
                    return;
                }

                // Validar tipo MIME básico en cliente
                const validTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];
                if (!validTypes.includes(file.type)) {
                    alert('Formato de imagen no soportado. Por favor seleccione un archivo PNG, JPG o WEBP.');
                    input.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('new-logo-preview').src = e.target.result;
                    document.getElementById('new-logo-preview-wrap').style.display = 'block';
                    const sizeKb = (file.size / 1024).toFixed(1);
                    document.getElementById('new-logo-info').textContent = file.name + ' (' + sizeKb + ' KB)';
                    
                    // Si estaba marcado para eliminar el logo actual, desmarcarlo
                    const removeCheckbox = document.getElementById('remove_company_logo');
                    if (removeCheckbox) {
                        removeCheckbox.checked = false;
                        toggleRemoveLogo(false);
                    }
                };
                reader.readAsDataURL(file);
            }

            function cancelLogoUpload(e) {
                if (e) e.stopPropagation();
                const input = document.getElementById('company_logo_input');
                if (input) input.value = '';
                const wrap = document.getElementById('new-logo-preview-wrap');
                if (wrap) wrap.style.display = 'none';
                const preview = document.getElementById('new-logo-preview');
                if (preview) preview.src = '#';
            }

            function toggleRemoveLogo(isChecked) {
                const currentImg = document.getElementById('current-logo-img');
                if (currentImg) {
                    currentImg.style.opacity = isChecked ? '0.2' : '1';
                    currentImg.style.filter = isChecked ? 'grayscale(100%)' : 'none';
                }
                if (isChecked) {
                    cancelLogoUpload();
                }
            }
        </script>
        <script>
            const settingsBaseUrl = '<?= $baseUrl ?>';
            function editCatalogType(code, name, color) {
                document.getElementById('catalog-form-title').innerText = 'Editar Tipo de Catálogo';
                const form = document.getElementById('catalog-form');
                form.action = settingsBaseUrl + '/admin/settings/catalog-type/update/' + code;
                const codeInput = document.getElementById('cat-code');
                codeInput.value = code;
                codeInput.readOnly = true;
                document.getElementById('cat-name').value = name;
                document.getElementById('cat-color').value = color;
                document.getElementById('cat-submit-btn').innerHTML = '<i class="fa-solid fa-save"></i> Guardar Cambios';
                document.getElementById('cat-cancel-btn').style.display = 'block';
            }
            function cancelEditCatalogType() {
                document.getElementById('catalog-form-title').innerText = 'Nuevo Tipo de Catálogo';
                const form = document.getElementById('catalog-form');
                form.action = settingsBaseUrl + '/admin/settings/catalog-type/store';
                const codeInput = document.getElementById('cat-code');
                codeInput.value = '';
                codeInput.readOnly = false;
                document.getElementById('cat-name').value = '';
                document.getElementById('cat-color').value = 'primary';
                document.getElementById('cat-submit-btn').innerHTML = '<i class="fa-solid fa-plus"></i> Añadir Tipo';
                document.getElementById('cat-cancel-btn').style.display = 'none';
            }
        </script>
    </div>
</div>
