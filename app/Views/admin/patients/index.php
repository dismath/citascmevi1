<div class="card">
    
    <div class="card-filters">
        <form method="GET" action="<?= $baseUrl ?>/admin/patients" class="filters-form" id="patients-filter-form">
            <div class="filter-group" style="flex: 1 1 300px; max-width: 100%;">
                <div style="position: relative; display: flex; align-items: center;">
                    <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 1rem; color: var(--text-muted);"></i>
                    <input type="text" name="search" class="filter-input" style="padding-left: 2.5rem; border-radius: var(--radius-xl);" placeholder="Buscar por número o paciente..." value="<?= htmlspecialchars($search ?? '') ?>">
                </div>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button>
                <a href="<?= $baseUrl ?>/admin/patients" class="btn btn-secondary btn-sm"><i class="fa-solid fa-eraser"></i> Limpiar</a>
                <?php if (\App\Helpers\Auth::hasPermission('patients_create')): ?>
                <a href="<?= $baseUrl ?>/admin/patients/create" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Nuevo Paciente</a>
                <?php endif; ?>
            </div>
        </form>
        <?php
            $hasActiveFilters = !empty($search);
        ?>
        <?php if($hasActiveFilters): ?>
        <div class="filter-results-info">
            <i class="fa-solid fa-filter"></i> 
            Se encontraron <strong><?= $totalRecords ?? count($patients) ?></strong> resultado(s) con los filtros aplicados.
            <a href="<?= $baseUrl ?>/admin/patients" class="filter-clear-link">Quitar filtros</a>
        </div>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Identificación</th>
                        <th>Nombre</th>
                        <th>Contacto</th>
                        <th>Total Citas</th>
                        <th>Registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($patients)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted" style="padding: 2rem;">
                            <i class="fa-solid fa-user-slash" style="font-size: 2rem; margin-bottom: 0.5rem; display: block; opacity: 0.4;"></i>
                            No se encontraron pacientes registrados.
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach($patients as $patient): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($patient['id_number']) ?></strong></td>
                        <td>
                            <?= htmlspecialchars($patient['name']) ?>
                            <?php if($patient['gender']): ?>
                                <i class="fa-solid <?= $patient['gender'] == 'M' ? 'fa-mars text-primary' : ($patient['gender'] == 'F' ? 'fa-venus text-danger' : 'fa-genderless text-muted') ?> ms-1"></i>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($patient['phone']): ?>
                                <div class="text-sm"><i class="fa-solid fa-phone text-muted"></i> <?= htmlspecialchars($patient['phone']) ?></div>
                            <?php endif; ?>
                            <?php if($patient['email']): ?>
                                <div class="text-sm"><i class="fa-regular fa-envelope text-muted"></i> <?= htmlspecialchars($patient['email']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-info"><?= $patient['total_appointments'] ?></span></td>
                        <td class="text-sm text-muted"><?= date('d/m/Y', strtotime($patient['created_at'])) ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <?php if (\App\Helpers\Auth::hasPermission('patients_update')): ?>
                                <button type="button" class="btn btn-sm btn-info text-white" title="Ver credenciales de acceso" onclick="openCredentialsModal('patient', <?= $patient['id'] ?>, '<?= htmlspecialchars(addslashes($patient['name'] ?? '')) ?>')">
                                    <i class="fa-solid fa-key"></i>
                                </button>
                                <a href="<?= $baseUrl ?>/admin/patients/edit/<?= \App\Helpers\HashId::encode($patient['id']) ?>" class="btn btn-sm btn-secondary" title="Editar"><i class="fa-solid fa-pen"></i></a>
                                <?php endif; ?>
                                <?php if (\App\Helpers\Auth::hasPermission('patients_delete')): ?>
                                <form action="<?= $baseUrl ?>/admin/patients/delete/<?= \App\Helpers\HashId::encode($patient['id']) ?>" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar este paciente?');">
                                    <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
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
            $totalRecords ?? count($patients),
            $baseUrl . '/admin/patients',
            array_filter(['search' => $search ?? ''])
        ) ?>
    </div>
</div>
<?php require_once APP_PATH . '/Views/admin/partials/credentials_modal.php'; ?>
