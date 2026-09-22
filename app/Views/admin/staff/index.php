<div class="card">
    <div class="card-header">
        <div>
            <p class="text-sm text-muted" style="margin: 0;">Gestión de recepcionistas, secretarias, cajeros, administradores y personal de apoyo clínico.</p>
        </div>
    </div>
    <div class="card-filters">
        <form method="GET" action="<?= $baseUrl ?>/admin/staff" class="filters-form" id="staff-filter-form">
            <div class="filter-group" style="flex: 1 1 280px; max-width: 100%;">
                <div style="position: relative; display: flex; align-items: center; height: 100%;">
                    <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 1rem; color: var(--text-muted);"></i>
                    <input type="text" name="search" class="filter-input" style="padding-left: 2.5rem; border-radius: var(--radius-xl); height: 100%;" placeholder="Buscar por nombre, cédula, cargo..." value="<?= htmlspecialchars($search ?? '') ?>">
                </div>
            </div>
            <div class="filter-group">
                <select name="department" class="filter-select" style="border-radius: var(--radius-xl);">
                    <option value="">Todos los departamentos</option>
                    <?php foreach($departments as $depKey => $depName): ?>
                        <option value="<?= htmlspecialchars($depKey) ?>" <?= ($department ?? '') === $depKey ? 'selected' : '' ?>>
                            <?= htmlspecialchars($depName) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <select name="status" class="filter-select" style="border-radius: var(--radius-xl);">
                    <option value="">Todos los estados</option>
                    <option value="active" <?= ($status ?? '') === 'active' ? 'selected' : '' ?>>Activos</option>
                    <option value="inactive" <?= ($status ?? '') === 'inactive' ? 'selected' : '' ?>>Inactivos</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filtrar</button>
                <a href="<?= $baseUrl ?>/admin/staff" class="btn btn-secondary btn-sm"><i class="fa-solid fa-eraser"></i> Limpiar</a>
                <?php if (\App\Helpers\Auth::hasPermission('staff_create')): ?>
                <a href="<?= $baseUrl ?>/admin/staff/create" class="btn btn-primary btn-sm"><i class="fa-solid fa-user-plus"></i> Nuevo Usuario</a>
                <?php endif; ?>
            </div>
        </form>
        <?php
            $hasActiveFilters = !empty($search) || !empty($department) || !empty($status);
        ?>
        <?php if($hasActiveFilters): ?>
        <div class="filter-results-info">
            <i class="fa-solid fa-filter"></i> 
            Se encontraron <strong><?= $totalRecords ?? count($staffList) ?></strong> colaborador(es) con los filtros aplicados.
            <a href="<?= $baseUrl ?>/admin/staff" class="filter-clear-link">Quitar filtros</a>
        </div>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Colaborador</th>
                        <th>Identificación</th>
                        <th>Área / Cargo</th>
                        <th>Contacto</th>
                        <th>Acceso Sistema</th>
                        <th>Estado</th>
                        <th style="text-align: right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($staffList)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted" style="padding: 2.5rem 1rem;">
                                <i class="fa-solid fa-users-slash" style="font-size: 2rem; display: block; margin-bottom: 0.5rem; opacity: 0.5;"></i>
                                No se encontraron colaboradores registrados.
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach($staffList as $st): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-center gap-2">
                                <div style="width: 38px; height: 38px; border-radius: 50%; background: var(--primary-light, #e0e7ff); color: var(--primary, #4338ca); display: flex; align-items: center; justify-content: center; font-weight: bold;">
                                    <?= strtoupper(substr($st['name'] ?? 'P', 0, 1)) ?>
                                </div>
                                <div>
                                    <strong><?= htmlspecialchars($st['name']) ?></strong>
                                    <?php if(!empty($st['hire_date'])): ?>
                                        <div class="text-xs text-muted">Ingreso: <?= htmlspecialchars($st['hire_date']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <code><?= htmlspecialchars($st['id_number']) ?></code>
                        </td>
                        <td>
                            <div><strong><?= htmlspecialchars($st['position']) ?></strong></div>
                            <span class="badge badge-secondary" style="font-size: 0.75rem;"><?= htmlspecialchars($st['department']) ?></span>
                        </td>
                        <td>
                            <?php if(!empty($st['email'])): ?>
                                <div class="text-sm"><i class="fa-regular fa-envelope text-muted"></i> <?= htmlspecialchars($st['email']) ?></div>
                            <?php endif; ?>
                            <?php if(!empty($st['phone'])): ?>
                                <div class="text-sm"><i class="fa-solid fa-phone text-muted"></i> <?= htmlspecialchars($st['phone']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if(!empty($st['user_id'])): ?>
                                <span class="badge badge-info" title="Usuario ID: <?= $st['user_id'] ?>">
                                    <i class="fa-solid fa-circle-check"></i> Con Acceso
                                </span>
                                <?php if(!empty($st['roles'])): ?>
                                    <div class="text-xs text-muted" style="margin-top: 2px;">Rol: <?= htmlspecialchars($st['roles']) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge badge-secondary text-muted">Sin cuenta</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($st['status'] === 'active'): ?>
                                <span class="badge badge-success"><i class="fa-solid fa-check"></i> Activo</span>
                            <?php elseif($st['status'] === 'inactive'): ?>
                                <span class="badge badge-warning"><i class="fa-solid fa-pause"></i> Inactivo</span>
                            <?php else: ?>
                                <span class="badge badge-danger"><i class="fa-solid fa-ban"></i> Terminado</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <div class="d-flex align-center gap-1 justify-end">
                                <?php if (\App\Helpers\Auth::hasPermission('staff_update')): ?>
                                <a href="<?= $baseUrl ?>/admin/staff/edit/<?= \App\Helpers\HashId::encode($st['id']) ?>" class="btn btn-sm btn-secondary" title="Editar">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                                <form action="<?= $baseUrl ?>/admin/staff/toggle/<?= \App\Helpers\HashId::encode($st['id']) ?>" method="POST" class="d-inline">
                                    <?= \App\Helpers\Session::csrfInput() ?>
                                    <button type="submit" class="btn btn-sm <?= $st['status'] === 'active' ? 'btn-warning' : 'btn-success' ?>" title="<?= $st['status'] === 'active' ? 'Desactivar' : 'Activar' ?>">
                                        <i class="fa-solid <?= $st['status'] === 'active' ? 'fa-pause' : 'fa-play' ?>"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <?php if (\App\Helpers\Auth::hasPermission('staff_delete')): ?>
                                <form action="<?= $baseUrl ?>/admin/staff/delete/<?= \App\Helpers\HashId::encode($st['id']) ?>" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar a este colaborador?');">
                                    <?= \App\Helpers\Session::csrfInput() ?>
                                    <button type="submit" class="btn btn-sm btn-danger" title="Eliminar">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= \App\Helpers\Pagination::render(
            $currentPage ?? 1,
            $totalPages ?? 1,
            $totalRecords ?? count($staffList),
            $baseUrl . '/admin/staff',
            array_filter([
                'search'     => $search ?? '',
                'department' => $department ?? '',
                'status'     => $status ?? ''
            ])
        ) ?>
    </div>
</div>
