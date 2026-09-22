<?php
$isAdmin = ($activeRole['name'] === 'admin');
$activeMeta = $roleMeta[$activeRole['name']] ?? [
    'label' => ucfirst($activeRole['name']),
    'icon' => 'fa-solid fa-user',
    'desc' => 'Configuración de permisos y privilegios para el rol seleccionado.'
];
$activeLabel = !empty($activeRole['display_name']) ? $activeRole['display_name'] : $activeMeta['label'];
$activeIcon = !empty($activeRole['icon']) ? $activeRole['icon'] : $activeMeta['icon'];
$activeDesc = !empty($activeRole['description']) ? $activeRole['description'] : $activeMeta['desc'];
?>

<style>
/* Card Container */
.roles-mgmt-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
    overflow: hidden;
    margin-bottom: 2rem;
    position: relative;
}

/* Purple Gradient Banner */
.roles-header-banner {
    background: linear-gradient(135deg, #6366f1 0%, #7c3aed 100%);
    color: #ffffff;
    padding: 1.15rem 1.75rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}
.roles-header-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.25rem;
    font-weight: 700;
    letter-spacing: -0.01em;
}
.roles-header-title i {
    font-size: 1.25rem;
}

/* Intro text */
.roles-intro-text {
    padding: 1.25rem 1.75rem 0.75rem;
    color: #475569;
    font-size: 0.95rem;
    line-height: 1.55;
    border-bottom: 1px solid #f1f5f9;
}
.roles-intro-text strong {
    color: #1e293b;
}

/* Listado de Roles Selector Bar */
.role-listado-panel {
    padding: 1.1rem 1.75rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1.25rem;
}
.role-listado-left {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    flex: 1 1 360px;
}
.role-listado-left label {
    font-size: 0.92rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
}
.role-select-box {
    position: relative;
    flex: 1 1 280px;
    max-width: 420px;
}
.role-dropdown-select {
    width: 100%;
    height: 42px;
    padding: 0 2.5rem 0 1rem;
    border: 1.8px solid #cbd5e1;
    border-radius: var(--radius-xl, 24px);
    font-size: 0.92rem;
    font-weight: 600;
    color: #0f172a;
    background: #ffffff;
    cursor: pointer;
    outline: none;
    transition: all 0.2s ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}
.role-dropdown-select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}
.role-listado-right {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

/* Button to open Create Role Modal */
.btn-new-role {
    background: var(--gradient-primary, linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%));
    color: #ffffff;
    border: 1.5px solid transparent;
    border-radius: var(--radius-xl, 24px);
    height: 38px;
    padding: 0 1.35rem;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
    box-shadow: 0 4px 14px rgba(14, 165, 233, 0.3);
    white-space: nowrap;
    line-height: 1;
    box-sizing: border-box;
}
.btn-new-role:hover {
    box-shadow: 0 6px 20px rgba(14, 165, 233, 0.4);
    transform: translateY(-1px);
    color: #ffffff;
}

/* Active Role Header Box */
.role-info-card {
    margin: 1.25rem 1.75rem;
    padding: 1.15rem 1.5rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
}
.role-info-left {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    max-width: 70%;
}
.role-info-left .info-icon {
    font-size: 1.4rem;
    color: #2563eb;
    margin-top: 3px;
    width: 32px;
    text-align: center;
}
.role-info-left .role-name-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 0.2rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.role-info-left .role-description {
    font-size: 0.88rem;
    color: #64748b;
    line-height: 1.45;
}
.role-actions-right {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}
.btn-quick-action {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0 1.15rem;
    border-radius: var(--radius-xl, 24px);
    height: 38px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    border: 1.5px solid transparent;
    box-sizing: border-box;
    line-height: 1;
}
.btn-todo {
    background: #ecfdf5;
    color: #059669;
    border-color: #a7f3d0;
}
.btn-todo:hover {
    background: #10b981;
    color: #ffffff;
    border-color: #10b981;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25);
}
.btn-limpiar {
    background: #fef2f2;
    color: #dc2626;
    border-color: #fecaca;
}
.btn-limpiar:hover {
    background: #ef4444;
    color: #ffffff;
    border-color: #ef4444;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.25);
}

/* Permissions Table Wrap */
.permissions-matrix-wrap {
    margin: 0 1.75rem 1.75rem;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow-x: auto;
    background: #ffffff;
}
.perm-matrix-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 650px;
}
.perm-matrix-table thead th {
    background: #ffffff;
    color: #64748b;
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    padding: 1.1rem 1.25rem;
    border-bottom: 1px solid #e2e8f0;
}
.module-header-row {
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    border-bottom: 1px solid #e2e8f0;
}
.module-header-row td {
    padding: 0.8rem 1.25rem;
}
.module-title-box {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    font-weight: 700;
    font-size: 0.95rem;
    color: #1e293b;
}
.module-title-box i {
    font-size: 1.05rem;
}
.module-actions-box {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}
.btn-module-action {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #475569;
    padding: 0 0.85rem;
    border-radius: var(--radius-xl, 24px);
    height: 32px;
    font-size: 0.8rem;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    transition: all 0.15s ease;
    box-sizing: border-box;
    line-height: 1;
}
.btn-module-action:hover {
    background: #f1f5f9;
    color: #0f172a;
    border-color: #94a3b8;
}
.submodule-row {
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.15s ease;
}
.submodule-row:hover {
    background: #fafbfe;
}
.submodule-row td {
    padding: 0.9rem 1.25rem;
    vertical-align: middle;
}
.submodule-title-line {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: #1e293b;
    font-size: 0.92rem;
}
.submodule-chevron {
    color: #94a3b8;
    font-size: 0.85rem;
}
.submodule-desc {
    margin-left: 1.25rem;
    font-size: 0.82rem;
    color: #64748b;
    margin-top: 0.2rem;
}

/* Checkboxes */
.perm-checkbox-custom {
    width: 20px;
    height: 20px;
    accent-color: #2563eb;
    cursor: pointer;
    border-radius: 4px;
    transition: transform 0.12s ease;
    display: block;
    margin: 0 auto;
}
.perm-checkbox-custom:hover:not(:disabled) {
    transform: scale(1.18);
}
.perm-checkbox-custom:disabled {
    cursor: not-allowed;
    opacity: 0.85;
}

/* Bottom Actions */
.perm-bottom-bar {
    padding: 1.25rem 1.75rem;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}
.btn-save-perm {
    background: #2563eb;
    color: #ffffff;
    border: 1.5px solid transparent;
    height: 38px;
    padding: 0 1.5rem;
    border-radius: var(--radius-xl, 24px);
    font-size: 0.88rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
    transition: all 0.2s ease;
    box-sizing: border-box;
    line-height: 1;
}
.btn-save-perm:hover {
    background: #1d4ed8;
    box-shadow: 0 6px 18px rgba(37, 99, 235, 0.4);
    transform: translateY(-1px);
}
.btn-save-perm:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.admin-lock-banner {
    margin: 1rem 1.75rem 1.5rem;
    padding: 1rem 1.25rem;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 8px;
    color: #166534;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.admin-lock-banner i {
    font-size: 1.25rem;
    color: #15803d;
}

/* Floating Toast Alert */
.roles-toast-alert {
    position: fixed;
    top: 24px;
    right: 24px;
    z-index: 2100;
    padding: 0.85rem 1.4rem;
    border-radius: var(--radius-xl, 24px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    display: none;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.9rem;
    font-weight: 600;
    animation: slideInRightToast 0.3s ease;
}
.roles-toast-alert.success {
    background: #10b981;
    color: #ffffff;
}
.roles-toast-alert.error {
    background: #ef4444;
    color: #ffffff;
}
@keyframes slideInRightToast {
    from { opacity: 0; transform: translateX(40px); }
    to { opacity: 1; transform: translateX(0); }
}

/* -------------------------------------------------------------
   MODAL PARA CREAR NUEVO ROL (Colocada al frente, z-index 2000)
-------------------------------------------------------------- */
.modal-overlay-custom {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(5px);
    -webkit-backdrop-filter: blur(5px);
    z-index: 2000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    animation: fadeInOverlay 0.2s ease;
}
.modal-overlay-custom.active {
    display: flex;
}
.modal-card-custom {
    background: #ffffff;
    border-radius: 14px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.28);
    width: 100%;
    max-width: 520px;
    overflow: hidden;
    animation: zoomInModal 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    border: 1px solid #e2e8f0;
}
@keyframes fadeInOverlay {
    from { opacity: 0; }
    to { opacity: 1; }
}
@keyframes zoomInModal {
    from { opacity: 0; transform: scale(0.94) translateY(12px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}
.modal-custom-header {
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    color: #ffffff;
    padding: 1.15rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.modal-custom-header h4 {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.6rem;
}
.btn-close-modal-custom {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: #ffffff;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 1.1rem;
    transition: all 0.2s ease;
}
.btn-close-modal-custom:hover {
    background: rgba(255, 255, 255, 0.4);
    transform: rotate(90deg);
}
.modal-custom-body {
    padding: 1.5rem;
    max-height: calc(85vh - 140px);
    overflow-y: auto;
}
.modal-custom-footer {
    padding: 1rem 1.5rem;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.75rem;
}

/* Icon Picker Grid */
.icon-picker-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 8px;
    margin-top: 0.35rem;
}
.icon-choice-btn {
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    background: #ffffff;
    color: #475569;
    font-size: 1.15rem;
    cursor: pointer;
    transition: all 0.15s ease;
}
.icon-choice-btn:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
    color: #1e293b;
}
.icon-choice-btn.selected {
    background: #eff6ff;
    border-color: #2563eb;
    color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
}
</style>

<!-- Floating notification toast -->
<div id="rolesToast" class="roles-toast-alert success">
    <i class="fa-solid fa-circle-check" id="rolesToastIcon"></i>
    <span id="rolesToastMsg">Operación completada</span>
</div>

<div class="roles-mgmt-card">
       <!-- Explanatory Subtitle -->
    <div class="roles-intro-text">
        Defina los privilegios de <strong>Ver</strong>, <strong>Crear</strong>, <strong>Editar</strong> y <strong>Eliminar</strong> para cada rol sobre los módulos y submódulos. El rol <strong>Administrador</strong> mantiene acceso total por arquitectura de seguridad.
    </div>

    <!-- Listado Seleccionable de Roles (Reemplaza las pestañas de botones anteriores) -->
    <div class="role-listado-panel">
        <div class="role-listado-left">
            <label for="roleQuickSelect">
                <i class="fa-solid fa-users-gear text-primary" style="font-size: 1.1rem;"></i> 
                <span>Listado de Roles del Sistema:</span>
            </label>
            <div class="role-select-box">
                <select id="roleQuickSelect" class="role-dropdown-select" onchange="selectRole(this.value)">
                    <?php foreach ($roles as $r): ?>
                        <?php 
                        $rIsActive = ((int)$r['id'] === (int)$activeRole['id']);
                        $rLabel = $rolesData[$r['id']]['label'] ?? ucfirst($r['name']);
                        ?>
                        <option value="<?= $r['id'] ?>" <?= $rIsActive ? 'selected' : '' ?>>
                            <?= htmlspecialchars($rLabel) ?> (<?= htmlspecialchars($r['name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="role-listado-right">
            <button type="button" class="btn-new-role" onclick="openCreateRoleModal()" title="Crear un nuevo rol en el sistema">
                <i class="fa-solid fa-plus-circle"></i> <span>Crear Nuevo Rol</span>
            </button>
        </div>
    </div>

    <!-- Active Role Header Box -->
    <div class="role-info-card">
        <div class="role-info-left">
            <i class="<?= htmlspecialchars($activeIcon) ?> info-icon" id="activeRoleIcon"></i>
            <div>
                <div class="role-name-title">
                    <span>Rol Seleccionado:</span> 
                    <span id="activeRoleLabel" class="text-primary"><?= htmlspecialchars($activeLabel) ?></span>
                    <span id="rolePermsBadge" class="badge badge-info" style="font-size: 0.75rem; font-weight: 600; margin-left: 0.5rem;">
                        <?= count($activePermIds) ?> permisos asignados
                    </span>
                </div>
                <div class="role-description" id="activeRoleDesc"><?= htmlspecialchars($activeDesc) ?></div>
            </div>
        </div>
        <div class="role-actions-right" id="roleActionsRight">
            <div id="adminProtectedBadge" style="<?= $isAdmin ? '' : 'display: none;' ?>">
                <span class="badge" style="background: #fef3c7; color: #92400e; border: 1px solid #fde68a; padding: 0 1.15rem; border-radius: var(--radius-xl, 24px); font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; height: 38px; box-sizing: border-box; font-size: 0.85rem;">
                    <i class="fa-solid fa-crown" style="color: #f59e0b;"></i> Acceso Total Protegido
                </span>
            </div>
            <div id="roleMetaActionButtons" style="display: flex; gap: 0.5rem; align-items: center;">
                <button type="button" class="btn-quick-action" style="background: #f1f5f9; color: #334155; border-color: #cbd5e1;" onclick="openEditRoleModal()" title="Editar nombre, descripción e ícono de este rol">
                    <i class="fa-solid fa-pen-to-square"></i> Editar Datos
                </button>
                <?php 
                $isSystemProtected = in_array($activeRole['name'], ['admin', 'doctor', 'patient', 'staff', 'receptionist', 'nurse'], true);
                ?>
                <button type="button" class="btn-quick-action btn-limpiar" id="btnDeleteRoleAction" style="<?= $isSystemProtected ? 'display: none;' : '' ?>" onclick="confirmDeleteRole()" title="Eliminar este rol personalizado">
                    <i class="fa-solid fa-trash-can"></i> Eliminar Rol
                </button>
            </div>
            <div id="normalRoleButtons" style="<?= $isAdmin ? 'display: none;' : 'display: flex; gap: 0.6rem;' ?>">
                <button type="button" class="btn-quick-action btn-todo" onclick="checkAll(true)" title="Marcar todos los permisos">
                    <i class="fa-solid fa-check"></i> Todo
                </button>
                <button type="button" class="btn-quick-action btn-limpiar" onclick="checkAll(false)" title="Desmarcar todos los permisos">
                    <i class="fa-solid fa-xmark"></i> Limpiar
                </button>
            </div>
        </div>
    </div>

    <!-- Form for Permissions Matrix -->
    <form action="<?= $baseUrl ?>/admin/roles/update/<?= $activeRole['id'] ?>" method="POST" id="permissionsForm" onsubmit="handlePermissionsSubmit(event)">
        <input type="hidden" name="csrf_token" id="csrfTokenPerms" value="<?= \App\Helpers\Session::get('csrf_token') ?>">
        <input type="hidden" name="role_id" id="formRoleId" value="<?= $activeRole['id'] ?>">

        <!-- Table Container -->
        <div class="permissions-matrix-wrap">
            <table class="perm-matrix-table">
                <thead>
                    <tr>
                        <th style="width: 48%; text-align: left;">MÓDULO / SUBMÓDULO</th>
                        <th style="width: 13%; text-align: center;">VER</th>
                        <th style="width: 13%; text-align: center;">CREAR</th>
                        <th style="width: 13%; text-align: center;">EDITAR</th>
                        <th style="width: 13%; text-align: center;">ELIMINAR</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $actionKeys = [
                        'read'   => 'VER',
                        'create' => 'CREAR',
                        'update' => 'EDITAR',
                        'delete' => 'ELIMINAR'
                    ];

                    foreach ($moduleDefinitions as $modKey => $mod): 
                    ?>
                        <!-- Module Group Header Row -->
                        <tr class="module-header-row">
                            <td colspan="1">
                                <div class="module-title-box">
                                    <i class="<?= $mod['icon'] ?>" style="color: <?= $mod['icon_color'] ?? '#2563eb' ?>;"></i>
                                    <span><?= htmlspecialchars($mod['title']) ?></span>
                                </div>
                            </td>
                            <td colspan="4">
                                <div class="module-actions-box">
                                    <div class="mod-action-buttons-group" style="<?= $isAdmin ? 'display: none;' : 'display: flex; gap: 0.5rem;' ?>">
                                        <button type="button" class="btn-module-action" onclick="checkModule('<?= $modKey ?>', true)" title="Activar todas las acciones de este módulo">
                                            <i class="fa-solid fa-check"></i> Activar Módulo
                                        </button>
                                        <button type="button" class="btn-module-action" onclick="checkModule('<?= $modKey ?>', false)" title="Desactivar todas las acciones de este módulo">
                                            <i class="fa-solid fa-ban"></i> Desactivar
                                        </button>
                                    </div>
                                    <div class="mod-admin-badge" style="<?= $isAdmin ? 'display: block;' : 'display: none;' ?>">
                                        <span class="badge" style="background: #e2e8f0; color: #475569; font-size: 0.75rem; padding: 0.3rem 0.6rem; border-radius: 4px;">
                                            <i class="fa-solid fa-check-double"></i> Módulo Completo
                                        </span>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <!-- Submodule Rows -->
                        <?php foreach ($mod['submodules'] as $subKey => $sub): ?>
                            <tr class="submodule-row">
                                <td>
                                    <div class="submodule-title-line">
                                        <span class="submodule-chevron"><i class="fa-solid fa-angle-right"></i></span>
                                        <strong><?= htmlspecialchars($sub['title']) ?></strong>
                                    </div>
                                    <div class="submodule-desc"><?= htmlspecialchars($sub['desc']) ?></div>
                                </td>
                                <?php foreach ($actionKeys as $act => $actLabel): ?>
                                    <?php 
                                    $permName = "{$subKey}_{$act}";
                                    $permId = $permMap[$permName] ?? null;
                                    $isChecked = $isAdmin || ($permId && in_array($permId, $activePermIds, true));
                                    ?>
                                    <td style="text-align: center; vertical-align: middle;">
                                        <?php if ($permId): ?>
                                            <input type="checkbox" 
                                                   name="permissions[]" 
                                                   value="<?= $permId ?>" 
                                                   data-perm-id="<?= $permId ?>"
                                                   data-module="<?= $modKey ?>"
                                                   data-submodule="<?= $subKey ?>"
                                                   data-action="<?= $act ?>"
                                                   class="perm-checkbox perm-checkbox-custom" 
                                                   <?= $isChecked ? 'checked' : '' ?> 
                                                   <?= $isAdmin ? 'disabled' : '' ?>
                                                   onchange="updatePermsCounter()">
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size: 0.8rem;">-</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Bottom Form Actions -->
        <div id="adminLockBannerContainer" style="<?= $isAdmin ? '' : 'display: none;' ?>">
            <div class="admin-lock-banner">
                <i class="fa-solid fa-shield-halved"></i>
                <div>
                    <strong>Seguridad del Sistema:</strong> El rol <strong>Administrador</strong> mantiene acceso total irreversible a todos los módulos y submódulos del sistema clínico para garantizar la integridad y gobernanza técnica del portal.
                </div>
            </div>
        </div>
        <div id="permBottomBarContainer" style="<?= $isAdmin ? 'display: none;' : '' ?>">
            <div class="perm-bottom-bar">
                <div style="display: flex; align-items: center; gap: 0.5rem; color: #64748b; font-size: 0.88rem;">
                    <i class="fa-solid fa-circle-info text-primary"></i>
                    <span>Los cambios se guardan de forma segura e inmediata para todos los usuarios con este rol.</span>
                </div>
                <button type="submit" class="btn btn-save-perm" id="btnSavePermissions">
                    <i class="fa-solid fa-floppy-disk"></i> <span>Guardar Cambios de Permisos</span>
                </button>
            </div>
        </div>
    </form>
</div>

<!-- =============================================================
     MODAL PARA CREAR NUEVO ROL (Colocada al frente, z-index 2000)
============================================================== -->
<div class="modal-overlay-custom" id="modalCreateRoleOverlay" onclick="if(event.target === this) closeCreateRoleModal()">
    <div class="modal-card-custom" role="dialog" aria-modal="true">
        <div class="modal-custom-header">
            <h4><i class="fa-solid fa-shield-plus"></i> Crear Nuevo Rol</h4>
            <button type="button" class="btn-close-modal-custom" onclick="closeCreateRoleModal()" title="Cerrar ventana">&times;</button>
        </div>
        <form id="createRoleForm" onsubmit="handleCreateRoleSubmit(event)">
            <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::get('csrf_token') ?>">
            <input type="hidden" name="is_ajax" value="1">
            <input type="hidden" name="icon" id="new_role_icon_input" value="fa-solid fa-user-gear">

            <div class="modal-custom-body">
                <div id="modalCreateRoleAlert" style="display: none; padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.88rem; margin-bottom: 1rem;"></div>

                <div class="form-group mb-3">
                    <label class="form-label" style="font-weight: 600; color: #1e293b;">
                        Nombre Visible del Rol <span class="text-danger">*</span>
                    </label>
                    <input type="text" 
                           name="display_name" 
                           id="new_role_display_name" 
                           class="form-control" 
                           required 
                           placeholder="Ej: Farmacéutico, Bioanalista, Auditor" 
                           oninput="autoGenerateSlug(this.value)">
                    <small class="text-muted" style="font-size: 0.78rem;">
                        Nombre oficial con el que se mostrará en las listas y perfiles.
                    </small>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label" style="font-weight: 600; color: #1e293b;">
                        Código / Identificador del Sistema <span class="text-danger">*</span>
                    </label>
                    <div style="position: relative;">
                        <input type="text" 
                               name="name" 
                               id="new_role_name" 
                               class="form-control" 
                               required 
                               placeholder="ej: farmacia, auditor_medico"
                               style="font-family: monospace; font-weight: 600; color: #2563eb;">
                    </div>
                    <small class="text-muted" style="font-size: 0.78rem;">
                        Código único del sistema en minúsculas y sin espacios.
                    </small>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label" style="font-weight: 600; color: #1e293b;">
                        Ícono Representativo
                    </label>
                    <div class="icon-picker-grid" id="iconPickerGrid">
                        <?php
                        $availableIcons = [
                            'fa-solid fa-user-gear',
                            'fa-solid fa-pills',
                            'fa-solid fa-flask',
                            'fa-solid fa-coins',
                            'fa-solid fa-stethoscope',
                            'fa-solid fa-user-nurse',
                            'fa-solid fa-headset',
                            'fa-solid fa-hospital-user',
                            'fa-solid fa-truck-medical',
                            'fa-solid fa-heart-pulse',
                            'fa-solid fa-shield-halved',
                            'fa-solid fa-clipboard-check',
                            'fa-solid fa-user-tie',
                            'fa-solid fa-notes-medical',
                            'fa-solid fa-syringe',
                            'fa-solid fa-briefcase-medical',
                            'fa-solid fa-microscope',
                            'fa-solid fa-receipt'
                        ];
                        foreach ($availableIcons as $idx => $ic):
                        ?>
                            <button type="button" 
                                    class="icon-choice-btn <?= $idx === 0 ? 'selected' : '' ?>" 
                                    data-icon="<?= $ic ?>"
                                    onclick="pickIcon('<?= $ic ?>', this)"
                                    title="<?= htmlspecialchars($ic) ?>">
                                <i class="<?= $ic ?>"></i>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group mb-0">
                    <label class="form-label" style="font-weight: 600; color: #1e293b;">
                        Descripción del Alcance
                    </label>
                    <textarea name="description" 
                              id="new_role_desc" 
                              class="form-control" 
                              rows="2" 
                              placeholder="Describa brevemente las responsabilidades y módulos a los que tendrá acceso este rol..."></textarea>
                </div>
            </div>
            <div class="modal-custom-footer">
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeCreateRoleModal()">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary btn-sm" id="btnSubmitNewRole">
                    <i class="fa-solid fa-check"></i> <span>Crear Rol</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- =============================================================
     MODAL PARA EDITAR INFORMACIÓN DEL ROL ACTIVO
============================================================== -->
<div class="modal-overlay-custom" id="modalEditRoleOverlay" onclick="if(event.target === this) closeEditRoleModal()">
    <div class="modal-card-custom" role="dialog" aria-modal="true">
        <div class="modal-custom-header" style="background: linear-gradient(135deg, #0284c7 0%, #2563eb 100%);">
            <h4><i class="fa-solid fa-pen-to-square"></i> Editar Información del Rol</h4>
            <button type="button" class="btn-close-modal-custom" onclick="closeEditRoleModal()" title="Cerrar ventana">&times;</button>
        </div>
        <form id="editRoleForm" onsubmit="handleEditRoleSubmit(event)">
            <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::get('csrf_token') ?>">
            <input type="hidden" name="is_ajax" value="1">
            <input type="hidden" name="icon" id="edit_role_icon_input" value="fa-solid fa-user-gear">
            <input type="hidden" id="edit_role_id_input" value="">

            <div class="modal-custom-body">
                <div id="modalEditRoleAlert" style="display: none; padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.88rem; margin-bottom: 1rem;"></div>

                <div class="form-group mb-3">
                    <label class="form-label" style="font-weight: 600; color: #1e293b;">
                        Nombre Visible del Rol <span class="text-danger">*</span>
                    </label>
                    <input type="text" 
                           name="display_name" 
                           id="edit_role_display_name" 
                           class="form-control" 
                           required 
                           placeholder="Ej: Farmacéutico, Bioanalista, Auditor">
                </div>

                <div class="form-group mb-3">
                    <label class="form-label" style="font-weight: 600; color: #1e293b;">
                        Ícono Representativo
                    </label>
                    <div class="icon-picker-grid" id="editIconPickerGrid">
                        <?php foreach ($availableIcons as $ic): ?>
                            <button type="button" 
                                    class="icon-choice-btn" 
                                    data-icon="<?= $ic ?>"
                                    onclick="pickEditIcon('<?= $ic ?>', this)"
                                    title="<?= htmlspecialchars($ic) ?>">
                                <i class="<?= $ic ?>"></i>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group mb-0">
                    <label class="form-label" style="font-weight: 600; color: #1e293b;">
                        Descripción del Alcance
                    </label>
                    <textarea name="description" 
                              id="edit_role_desc" 
                              class="form-control" 
                              rows="2" 
                              placeholder="Describa brevemente las responsabilidades del rol..."></textarea>
                </div>
            </div>
            <div class="modal-custom-footer">
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeEditRoleModal()">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary btn-sm" id="btnSubmitEditRole">
                    <i class="fa-solid fa-floppy-disk"></i> <span>Guardar Cambios</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
/**
 * Catálogo completo de roles y permisos precargados
 */
const ROLES_DATA = <?= json_encode($rolesData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
let currentRoleId = <?= (int)$activeRole['id'] ?>;
const BASE_APP_URL = '<?= $baseUrl ?>';
const PROTECTED_ROLES = ['admin', 'doctor', 'patient', 'staff', 'receptionist', 'nurse'];

/**
 * Muestra notificación flotante sin recargar la página
 */
function showToast(message, type = 'success') {
    const toast = document.getElementById('rolesToast');
    const msg = document.getElementById('rolesToastMsg');
    const icon = document.getElementById('rolesToastIcon');
    if (!toast) return;

    toast.className = 'roles-toast-alert ' + type;
    msg.textContent = message;
    icon.className = type === 'success' ? 'fa-solid fa-circle-check' : 'fa-solid fa-circle-exclamation';

    toast.style.display = 'flex';
    clearTimeout(window.__toastTimer);
    window.__toastTimer = setTimeout(() => {
        toast.style.display = 'none';
    }, 4500);
}

/**
 * Selecciona un rol de la lista y actualiza la matriz de permisos de forma instantánea
 */
function selectRole(roleId) {
    roleId = parseInt(roleId, 10);
    const role = ROLES_DATA[roleId];
    if (!role) return;

    currentRoleId = roleId;

    // 1. Sincronizar el selector de la lista
    const quickSelect = document.getElementById('roleQuickSelect');
    if (quickSelect) {
        quickSelect.value = roleId;
    }

    // 2. Actualizar datos en la tarjeta del rol activo
    document.getElementById('activeRoleLabel').textContent = role.label;
    document.getElementById('activeRoleDesc').textContent = role.desc;
    const iconElem = document.getElementById('activeRoleIcon');
    if (iconElem) {
        iconElem.className = (role.icon || 'fa-solid fa-user') + ' info-icon';
    }

    // 3. Manejo de Roles Protegidos vs Roles Dinámicos
    const isProtected = PROTECTED_ROLES.includes(role.name);
    const deleteBtn = document.getElementById('btnDeleteRoleAction');
    if (deleteBtn) {
        deleteBtn.style.display = isProtected ? 'none' : 'inline-flex';
    }

    // 4. Manejo de Administrador vs Roles Estándar
    const isAdmin = Boolean(role.is_admin);
    const adminBadge = document.getElementById('adminProtectedBadge');
    const normalButtons = document.getElementById('normalRoleButtons');
    const adminLockBanner = document.getElementById('adminLockBannerContainer');
    const permBottomBar = document.getElementById('permBottomBarContainer');

    if (adminBadge) adminBadge.style.display = isAdmin ? 'block' : 'none';
    if (normalButtons) normalButtons.style.display = isAdmin ? 'none' : 'flex';
    if (adminLockBanner) adminLockBanner.style.display = isAdmin ? 'block' : 'none';
    if (permBottomBar) permBottomBar.style.display = isAdmin ? 'none' : 'block';

    document.querySelectorAll('.mod-action-buttons-group').forEach(el => {
        el.style.display = isAdmin ? 'none' : 'flex';
    });
    document.querySelectorAll('.mod-admin-badge').forEach(el => {
        el.style.display = isAdmin ? 'block' : 'none';
    });

    // 5. Actualizar checkboxes de permisos
    const assignedPerms = new Set(role.permissions.map(p => parseInt(p, 10)));
    const checkboxes = document.querySelectorAll('.perm-checkbox');
    checkboxes.forEach(cb => {
        const pId = parseInt(cb.getAttribute('data-perm-id'), 10);
        cb.disabled = isAdmin;
        cb.checked = isAdmin || assignedPerms.has(pId);
    });

    // 6. Actualizar contador
    updatePermsCounter();

    // 7. Actualizar formulario
    const form = document.getElementById('permissionsForm');
    if (form) {
        form.action = BASE_APP_URL + '/admin/roles/update/' + roleId;
    }
    const formRoleIdInput = document.getElementById('formRoleId');
    if (formRoleIdInput) {
        formRoleIdInput.value = roleId;
    }

    // 8. Actualizar URL de forma silenciosa sin recargar la página anterior
    const newUrl = BASE_APP_URL + '/admin/roles?role_id=' + roleId;
    window.history.pushState({ role_id: roleId }, '', newUrl);
}

/**
 * Actualiza el contador de permisos marcados
 */
function updatePermsCounter() {
    const badge = document.getElementById('rolePermsBadge');
    if (!badge) return;
    const checkedCount = document.querySelectorAll('.perm-checkbox:checked').length;
    badge.textContent = checkedCount + ' permisos asignados';
}

/**
 * Marcar o desmarcar todos los checkboxes activos
 */
function checkAll(state) {
    const checkboxes = document.querySelectorAll('.perm-checkbox:not(:disabled)');
    checkboxes.forEach(cb => {
        cb.checked = state;
    });
    updatePermsCounter();
}

/**
 * Marcar o desmarcar checkboxes de un módulo específico
 */
function checkModule(moduleKey, state) {
    const selector = '.perm-checkbox[data-module="' + moduleKey + '"]:not(:disabled)';
    const checkboxes = document.querySelectorAll(selector);
    checkboxes.forEach(cb => {
        cb.checked = state;
    });
    updatePermsCounter();
}

/**
 * Guarda los permisos mediante AJAX sin recargar la página
 */
function handlePermissionsSubmit(e) {
    e.preventDefault();
    const role = ROLES_DATA[currentRoleId];
    if (role && role.is_admin) {
        showToast('El rol Administrador tiene acceso total permanente.', 'error');
        return;
    }

    const saveBtn = document.getElementById('btnSavePermissions');
    const originalBtnHtml = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <span>Guardando...</span>';

    const checkedPerms = [];
    document.querySelectorAll('.perm-checkbox:checked').forEach(cb => {
        checkedPerms.push(cb.value);
    });

    const csrfToken = document.getElementById('csrfTokenPerms').value;
    const formData = new URLSearchParams();
    formData.append('csrf_token', csrfToken);
    formData.append('role_id', currentRoleId);
    checkedPerms.forEach(val => {
        formData.append('permissions[]', val);
    });

    fetch(BASE_APP_URL + '/admin/roles/save-permissions-ajax', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData.toString()
    })
    .then(res => res.json())
    .then(data => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnHtml;

        if (data.success) {
            if (ROLES_DATA[currentRoleId]) {
                ROLES_DATA[currentRoleId].permissions = checkedPerms.map(x => parseInt(x, 10));
            }
            showToast(data.message || 'Permisos guardados correctamente.', 'success');
        } else {
            showToast(data.message || 'Error al guardar los permisos.', 'error');
        }
    })
    .catch(err => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnHtml;
        console.error(err);
        showToast('Error de comunicación con el servidor.', 'error');
    });
}

/* -------------------------------------------------------------
   LÓGICA DE LA MODAL CREAR NUEVO ROL
-------------------------------------------------------------- */
function openCreateRoleModal() {
    const overlay = document.getElementById('modalCreateRoleOverlay');
    const form = document.getElementById('createRoleForm');
    const alertBox = document.getElementById('modalCreateRoleAlert');
    if (form) form.reset();
    if (alertBox) alertBox.style.display = 'none';

    // Seleccionar primer ícono por defecto
    const firstIconBtn = document.querySelector('#iconPickerGrid .icon-choice-btn');
    if (firstIconBtn) {
        pickIcon('fa-solid fa-user-gear', firstIconBtn);
    }

    if (overlay) {
        overlay.classList.add('active');
        setTimeout(() => {
            const nameInput = document.getElementById('new_role_display_name');
            if (nameInput) nameInput.focus();
        }, 150);
    }
}

function closeCreateRoleModal() {
    const overlay = document.getElementById('modalCreateRoleOverlay');
    if (overlay) overlay.classList.remove('active');
}

/* -------------------------------------------------------------
   LÓGICA DE LA MODAL EDITAR ROL EXISTENTE
-------------------------------------------------------------- */
function openEditRoleModal() {
    const role = ROLES_DATA[currentRoleId];
    if (!role) return;

    const overlay = document.getElementById('modalEditRoleOverlay');
    const alertBox = document.getElementById('modalEditRoleAlert');
    if (alertBox) alertBox.style.display = 'none';

    document.getElementById('edit_role_id_input').value = role.id;
    document.getElementById('edit_role_display_name').value = role.label || '';
    document.getElementById('edit_role_desc').value = role.desc || '';
    document.getElementById('edit_role_icon_input').value = role.icon || 'fa-solid fa-user-gear';

    // Marcar ícono activo en la grilla
    document.querySelectorAll('#editIconPickerGrid .icon-choice-btn').forEach(btn => {
        if (btn.getAttribute('data-icon') === role.icon) {
            btn.classList.add('selected');
        } else {
            btn.classList.remove('selected');
        }
    });

    if (overlay) {
        overlay.classList.add('active');
        setTimeout(() => {
            const nameInput = document.getElementById('edit_role_display_name');
            if (nameInput) nameInput.focus();
        }, 150);
    }
}

function closeEditRoleModal() {
    const overlay = document.getElementById('modalEditRoleOverlay');
    if (overlay) overlay.classList.remove('active');
}

function pickEditIcon(iconClass, btn) {
    document.querySelectorAll('#editIconPickerGrid .icon-choice-btn').forEach(b => b.classList.remove('selected'));
    if (btn) btn.classList.add('selected');
    const iconInput = document.getElementById('edit_role_icon_input');
    if (iconInput) iconInput.value = iconClass;
}

function handleEditRoleSubmit(e) {
    e.preventDefault();
    const roleId = document.getElementById('edit_role_id_input').value;
    const submitBtn = document.getElementById('btnSubmitEditRole');
    const alertBox = document.getElementById('modalEditRoleAlert');
    const originalHtml = submitBtn.innerHTML;

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando...';
    alertBox.style.display = 'none';

    const formData = new FormData(e.target);

    fetch(BASE_APP_URL + '/admin/roles/update-info/' + roleId, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHtml;

        if (data.success && data.role) {
            const updated = data.role;
            if (ROLES_DATA[roleId]) {
                ROLES_DATA[roleId].label = updated.label;
                ROLES_DATA[roleId].desc = updated.desc;
                ROLES_DATA[roleId].icon = updated.icon;
            }

            // Actualizar texto en selector dropdown
            const opt = document.querySelector('#roleQuickSelect option[value="' + roleId + '"]');
            if (opt) {
                opt.textContent = updated.label + ' (' + updated.name + ')';
            }

            // Actualizar vista activa
            selectRole(roleId);
            closeEditRoleModal();
            showToast(data.message || '¡Rol actualizado correctamente!', 'success');
        } else {
            alertBox.style.display = 'block';
            alertBox.className = 'alert alert-error mb-3';
            alertBox.style.background = '#fef2f2';
            alertBox.style.color = '#dc2626';
            alertBox.style.border = '1px solid #fecaca';
            alertBox.innerHTML = '<i class="fa-solid fa-circle-exclamation me-1"></i> ' + (data.message || 'Error al actualizar el rol.');
        }
    })
    .catch(err => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHtml;
        console.error(err);
        alertBox.style.display = 'block';
        alertBox.className = 'alert alert-error mb-3';
        alertBox.style.background = '#fef2f2';
        alertBox.style.color = '#dc2626';
        alertBox.style.border = '1px solid #fecaca';
        alertBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i> Error en la comunicación con el servidor.';
    });
}

/* -------------------------------------------------------------
   ELIMINACIÓN DE ROL PERSONALIZADO
-------------------------------------------------------------- */
function confirmDeleteRole() {
    const role = ROLES_DATA[currentRoleId];
    if (!role) return;

    if (PROTECTED_ROLES.includes(role.name)) {
        showToast('Los roles predeterminados del sistema no pueden ser eliminados.', 'error');
        return;
    }

    if (!confirm('¿Está seguro de que desea eliminar permanentemente el rol "' + role.label + '"?\n\nEsta acción revocará todos sus permisos y no se puede deshacer.')) {
        return;
    }

    deleteRoleAjax(currentRoleId);
}

function deleteRoleAjax(roleId) {
    const csrfToken = document.getElementById('csrfTokenPerms').value;
    const formData = new URLSearchParams();
    formData.append('csrf_token', csrfToken);
    formData.append('is_ajax', '1');

    fetch(BASE_APP_URL + '/admin/roles/delete/' + roleId, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        body: formData.toString()
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Eliminar de ROLES_DATA
            delete ROLES_DATA[roleId];

            // Eliminar de dropdown
            const opt = document.querySelector('#roleQuickSelect option[value="' + roleId + '"]');
            if (opt) opt.remove();

            showToast(data.message || 'Rol eliminado exitosamente.', 'success');

            // Seleccionar primer rol disponible (o staff)
            const remainingKeys = Object.keys(ROLES_DATA);
            if (remainingKeys.length > 0) {
                let targetId = remainingKeys[0];
                for (const k of remainingKeys) {
                    if (ROLES_DATA[k].name === 'staff') {
                        targetId = k;
                        break;
                    }
                }
                selectRole(parseInt(targetId, 10));
            }
        } else {
            showToast(data.message || 'No se pudo eliminar el rol.', 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Error de comunicación con el servidor.', 'error');
    });
}

/**
 * Cierre con tecla Escape
 */
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCreateRoleModal();
        closeEditRoleModal();
    }
});

/**
 * Selecciona un ícono en la cuadrícula de la modal crear
 */
function pickIcon(iconClass, btn) {
    document.querySelectorAll('#iconPickerGrid .icon-choice-btn').forEach(b => b.classList.remove('selected'));
    if (btn) btn.classList.add('selected');
    const iconInput = document.getElementById('new_role_icon_input');
    if (iconInput) iconInput.value = iconClass;
}

/**
 * Genera el identificador técnico (slug) en tiempo real
 */
function autoGenerateSlug(text) {
    const slugInput = document.getElementById('new_role_name');
    if (!slugInput) return;
    const clean = text.toLowerCase()
                      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                      .replace(/[^a-z0-9]+/g, '_')
                      .replace(/^_+|_+$/g, '');
    slugInput.value = clean;
}

/**
 * Procesa la creación del nuevo rol vía AJAX
 */
function handleCreateRoleSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const submitBtn = document.getElementById('btnSubmitNewRole');
    const alertBox = document.getElementById('modalCreateRoleAlert');
    const originalHtml = submitBtn.innerHTML;

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando...';
    alertBox.style.display = 'none';

    const formData = new FormData(form);

    fetch(BASE_APP_URL + '/admin/roles/store', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHtml;

        if (data.success && data.role) {
            const newRole = data.role;
            const newId = parseInt(newRole.id, 10);

            // 1. Guardar en memoria ROLES_DATA
            ROLES_DATA[newId] = newRole;

            // 2. Insertar nueva opción en el selector dropdown de la lista
            const quickSelect = document.getElementById('roleQuickSelect');
            if (quickSelect) {
                const opt = document.createElement('option');
                opt.value = newId;
                opt.textContent = newRole.label + ' (' + newRole.name + ')';
                quickSelect.appendChild(opt);
                quickSelect.value = newId;
            }

            // 3. Cerrar la modal
            closeCreateRoleModal();

            // 4. Seleccionar automáticamente el nuevo rol en el listado para asignarle permisos
            selectRole(newId);

            // 5. Notificar al usuario
            showToast('¡Rol "' + newRole.label + '" creado exitosamente! Configure sus permisos a continuación.', 'success');
        } else {
            alertBox.style.display = 'block';
            alertBox.className = 'alert alert-error mb-3';
            alertBox.style.background = '#fef2f2';
            alertBox.style.color = '#dc2626';
            alertBox.style.border = '1px solid #fecaca';
            alertBox.innerHTML = '<i class="fa-solid fa-circle-exclamation me-1"></i> ' + (data.message || 'Error al crear el rol.');
        }
    })
    .catch(err => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHtml;
        console.error(err);
        alertBox.style.display = 'block';
        alertBox.className = 'alert alert-error mb-3';
        alertBox.style.background = '#fef2f2';
        alertBox.style.color = '#dc2626';
        alertBox.style.border = '1px solid #fecaca';
        alertBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i> Error en la comunicación con el servidor.';
    });
}
</script>
