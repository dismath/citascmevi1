<div class="card max-w-2xl mx-auto">
    <div class="card-header">
        <h3>Editar Especialidad / Servicio</h3>
    </div>
    <div class="card-body">
        <form action="<?= $baseUrl ?>/admin/specialties/update/<?= $specialty['id'] ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
            <div class="form-group">
                <label class="form-label">Nombre *</label>
                <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($specialty['name']) ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Descripción</label>
                <textarea name="description" class="form-control" style="min-height: 80px;"><?= htmlspecialchars($specialty['description'] ?? '') ?></textarea>
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
                    <?php 
                    $currentIcon = $specialty['icon'] ?? 'stethoscope';
                    foreach($medicalIcons as $iconClass => $iconName): 
                    ?>
                        <label title="<?= $iconName ?>">
                            <input type="radio" name="icon" value="<?= $iconClass ?>" <?= $iconClass == $currentIcon ? 'checked' : '' ?>>
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
                            <option value="<?= htmlspecialchars($ct['code']) ?>" <?= (isset($specialty['catalog_type_code']) && $specialty['catalog_type_code'] === $ct['code']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ct['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <a href="<?= $baseUrl ?>/admin/specialties" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left"></i> Volver
                </a>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-save"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>
