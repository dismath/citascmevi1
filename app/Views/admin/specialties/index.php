<div class="grid-3">
    <div class="card" style="grid-column: span 2;">
       
        <div class="card-body p-0">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Ícono</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Descripción</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($specialties)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted" style="padding: 2rem;">
                                <i class="fa-solid fa-stethoscope" style="font-size: 2rem; margin-bottom: 0.5rem; display: block; opacity: 0.4;"></i>
                                No hay especialidades registradas.
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach($specialties as $sp): ?>
                        <tr>
                            <td class="text-center text-primary" style="font-size: 1.5rem;">
                                <i class="fa-solid fa-<?= htmlspecialchars($sp['icon'] ?: 'stethoscope') ?>"></i>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($sp['name']) ?></strong>
                                <?php if(isset($sp['status']) && $sp['status'] === 'inactive'): ?>
                                    <span class="badge badge-danger ml-1" style="font-size: 0.7rem;">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if(!empty($sp['catalog_type_code']) && isset($catalogTypeMap[$sp['catalog_type_code']])): ?>
                                    <div class="mb-1"><span class="badge badge-secondary"><?= htmlspecialchars($catalogTypeMap[$sp['catalog_type_code']]) ?></span></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-sm text-muted"><?= htmlspecialchars($sp['description'] ?? '') ?></td>
                            <td class="d-flex gap-1">
                                <?php if (\App\Helpers\Auth::hasPermission('specialties_update')): ?>
                                <a href="<?= $baseUrl ?>/admin/specialties/edit/<?= \App\Helpers\HashId::encode($sp['id']) ?>" class="btn btn-sm btn-info" title="Editar"><i class="fa-solid fa-pen"></i></a>
                                <form action="<?= $baseUrl ?>/admin/specialties/toggle/<?= \App\Helpers\HashId::encode($sp['id']) ?>" method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                                    <button type="submit" class="btn btn-sm <?= (isset($sp['status']) && $sp['status'] === 'active') ? 'btn-warning' : 'btn-success' ?>" title="<?= (isset($sp['status']) && $sp['status'] === 'active') ? 'Deshabilitar' : 'Habilitar' ?>">
                                        <i class="fa-solid <?= (isset($sp['status']) && $sp['status'] === 'active') ? 'fa-ban' : 'fa-check' ?>"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <?php if (\App\Helpers\Auth::hasPermission('specialties_delete')): ?>
                                <form action="<?= $baseUrl ?>/admin/specialties/delete/<?= \App\Helpers\HashId::encode($sp['id']) ?>" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar esta especialidad?');">
                                    <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= \App\Helpers\Pagination::render(
                $currentPage ?? 1,
                $totalPages ?? 1,
                $totalRecords ?? count($specialties),
                $baseUrl . '/admin/specialties'
            ) ?>
        </div>
    </div>
    <?php if (\App\Helpers\Auth::hasPermission('specialties_create')): ?>
    <div class="card bg-surface-2 h-fit">
        <div class="card-header">
            <h3>Nueva Especialidad/Servicio</h3>
        </div>
        <div class="card-body">
            <form action="<?= $baseUrl ?>/admin/specialties/store" method="POST">
                <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                <div class="form-group">
                    <label class="form-label">Nombre *</label>
                    <input type="text" name="name" class="form-control" required placeholder="Ej: Cardiología">
                </div>
                <div class="form-group">
                    <label class="form-label">Descripción</label>
                    <textarea name="description" class="form-control" style="min-height: 80px;" placeholder="Descripción corta..."></textarea>
                </div>
                <style>
                .icon-selector { display: flex; flex-wrap: wrap; gap: 8px; max-height: 180px; overflow-y: auto; padding: 8px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); background: var(--surface); }
                .icon-selector label { margin: 0; cursor: pointer; }
                .icon-selector input[type="radio"] { display: none; }
                .icon-selector .icon-box { display: flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: var(--radius-sm); border: 2px solid transparent; transition: var(--transition); color: var(--text-secondary); font-size: 1.25rem; }
                .icon-selector input[type="radio"]:checked + .icon-box { background-color: var(--primary-50); border-color: var(--primary); color: var(--primary); box-shadow: 0 0 0 3px rgba(14,165,233,.15); }
                .icon-selector .icon-box:hover:not(.checked) { background-color: var(--surface-3); }
                </style>
                <div class="form-group">
                    <label class="form-label">Ícono Representativo *</label>
                    <div class="icon-selector">
                        <?php foreach($medicalIcons as $iconClass => $iconName): ?>
                            <label title="<?= $iconName ?>">
                                <input type="radio" name="icon" value="<?= $iconClass ?>" <?= $iconClass == 'stethoscope' ? 'checked' : '' ?>>
                                <div class="icon-box"><i class="fa-solid fa-<?= $iconClass ?>"></i></div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-group mt-3">
                    <label class="form-label">Tipo de Catálogo *</label>
                    <select name="catalog_type_code" class="form-control" required>
                        <option value="">-- Seleccionar Tipo de Catálogo --</option>
                        <?php if(!empty($catalogTypes)): ?>
                            <?php foreach($catalogTypes as $ct): ?>
                                <option value="<?= htmlspecialchars($ct['code']) ?>" <?= $ct['code'] === 'ESPEC' ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ct['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-block mt-2"><i class="fa-solid fa-plus"></i> Guardar</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
