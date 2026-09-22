<div class="card">
 
    <div class="card-filters">
        <form method="GET" action="<?= $baseUrl ?>/admin/doctors" class="filters-form" id="doctors-filter-form">
            <div class="filter-group" style="flex: 1 1 300px; max-width: 100%;">
                <div style="position: relative; display: flex; align-items: center; height: 100%;">
                    <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 1rem; color: var(--text-muted);"></i>
                    <input type="text" name="search" class="filter-input" style="padding-left: 2.5rem; border-radius: var(--radius-xl); height: 100%;" placeholder="Buscar por número o médico..." value="<?= htmlspecialchars($search ?? '') ?>">
                </div>
            </div>
            <div class="filter-group">
                <select name="specialty_id" class="filter-select" style="border-radius: var(--radius-xl);">
                    <option value="">Todas las especialidades</option>
                    <?php foreach($specialties as $spec): ?>
                        <option value="<?= $spec['id'] ?>" <?= ($specialty_id ?? '') == $spec['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($spec['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button>
                <a href="<?= $baseUrl ?>/admin/doctors" class="btn btn-secondary btn-sm"><i class="fa-solid fa-eraser"></i> Limpiar</a>
                <?php if (\App\Helpers\Auth::hasPermission('doctors_create')): ?>
                <a href="<?= $baseUrl ?>/admin/doctors/create" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Nuevo Médico</a>
                <?php endif; ?>
            </div>
        </form>
        <?php
            $hasActiveFilters = !empty($search) || !empty($specialty_id);
        ?>
        <?php if($hasActiveFilters): ?>
        <div class="filter-results-info">
            <i class="fa-solid fa-filter"></i> 
            Se encontraron <strong><?= $totalRecords ?? count($doctors) ?></strong> resultado(s) con los filtros aplicados.
            <a href="<?= $baseUrl ?>/admin/doctors" class="filter-clear-link">Quitar filtros</a>
        </div>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Profesional</th>
                        <th>Especialidad</th>
                        <th>Contacto</th>
                        <th>Tarifa</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($doctors)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted" style="padding: 2rem;">
                            <i class="fa-solid fa-user-doctor" style="font-size: 2rem; margin-bottom: 0.5rem; display: block; opacity: 0.4;"></i>
                            No se encontraron médicos registrados.
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach($doctors as $doc): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-center gap-1">
                                <div class="btn-icon bg-surface-3 text-primary d-flex align-center justify-center rounded-full">
                                    <i class="fa-solid fa-user-doctor"></i>
                                </div>
                                <div>
                                    <strong><?= htmlspecialchars($doc['name'] ?? '') ?></strong>
                                    <div class="text-sm text-muted">CI: <?= htmlspecialchars($doc['id_number'] ?? '') ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-info"><?= htmlspecialchars($doc['specialty_name'] ?? '') ?></span>
                            <?php if(!empty($doc['medical_license'])): ?>
                                <div class="text-sm text-muted mt-1">Lic: <?= htmlspecialchars($doc['medical_license']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if(!empty($doc['email'])): ?>
                                <div class="text-sm"><i class="fa-regular fa-envelope"></i> <?= htmlspecialchars($doc['email']) ?></div>
                            <?php else: ?>
                                <div class="text-sm text-muted"><i class="fa-regular fa-envelope"></i> Sin correo</div>
                            <?php endif; ?>
                            <?php if(!empty($doc['phone'])): ?>
                                <div class="text-sm"><i class="fa-solid fa-phone"></i> <?= htmlspecialchars($doc['phone']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong>$<?= number_format((float)($doc['consultation_fee'] ?? 0), 2) ?></strong>
                            <div class="text-sm <?= !empty($doc['show_fee']) ? 'text-success' : 'text-danger' ?>">
                                <?= !empty($doc['show_fee']) ? 'Visible' : 'Oculta' ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge <?= ($doc['status'] ?? '') === 'active' ? 'badge-success' : 'badge-danger' ?>">
                                <?= ($doc['status'] ?? '') === 'active' ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <?php if (\App\Helpers\Auth::hasPermission('doctors_update')): ?>
                                <button type="button" class="btn btn-sm btn-info text-white" title="Ver credenciales de acceso" onclick="openCredentialsModal('doctor', <?= $doc['id'] ?>, '<?= htmlspecialchars(addslashes($doc['name'] ?? '')) ?>')">
                                    <i class="fa-solid fa-key"></i>
                                </button>
                                <a href="<?= $baseUrl ?>/admin/doctors/edit/<?= \App\Helpers\HashId::encode($doc['id']) ?>" class="btn btn-sm btn-secondary" title="Editar"><i class="fa-solid fa-pen"></i></a>
                                <form action="<?= $baseUrl ?>/admin/doctors/toggle/<?= \App\Helpers\HashId::encode($doc['id']) ?>" method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                                    <button type="submit" class="btn btn-sm btn-<?= $doc['status'] === 'active' ? 'warning' : 'success' ?>" title="<?= $doc['status'] === 'active' ? 'Desactivar' : 'Activar' ?>">
                                        <i class="fa-solid fa-power-off"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <?php if (\App\Helpers\Auth::hasPermission('doctors_delete')): ?>
                                <form action="<?= $baseUrl ?>/admin/doctors/delete/<?= \App\Helpers\HashId::encode($doc['id']) ?>" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar permanentemente a este médico y todos sus registros asociados? Esta acción no se puede deshacer.');">
                                    <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Eliminar definitivamente"><i class="fa-solid fa-trash"></i></button>
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
            $totalRecords ?? count($doctors),
            $baseUrl . '/admin/doctors',
            array_filter([
                'search' => $search ?? '',
                'specialty_id' => $specialty_id ?? ''
            ])
        ) ?>
    </div>
</div>
<?php require_once APP_PATH . '/Views/admin/partials/credentials_modal.php'; ?>
