<div class="schedule-tabs">
    <button class="schedule-tab <?= (!isset($_GET['tab']) || $_GET['tab'] == 'catalog') ? 'active' : '' ?>" data-tab="catalog">
        <i class="fa-solid fa-layer-group"></i>
        <span>Catálogo de Servicios</span>
    </button>
    <button class="schedule-tab <?= (isset($_GET['tab']) && $_GET['tab'] == 'manage') ? 'active' : '' ?>" data-tab="manage">
        <i class="fa-solid fa-folder-tree"></i>
        <span>Gestión Jerárquica</span>
    </button>
</div>
<div class="schedule-panel <?= (!isset($_GET['tab']) || $_GET['tab'] == 'catalog') ? 'active' : '' ?>" id="panel-catalog">
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-4" style="flex-wrap: wrap; gap: 1rem;">
                <div class="d-flex align-items-center gap-2">
                    <label for="typeFilter" class="mb-0 text-muted" style="white-space: nowrap; font-size: 0.9rem;"><i class="fa-solid fa-filter"></i> Filtrar:</label>
                    <select id="typeFilter" class="form-control form-control-sm" style="min-width: 200px;" onchange="filterByType(this.value)">
                        <option value="all">Todas</option>
                        <?php foreach($types as $type): ?>
                        <option value="<?= htmlspecialchars($type['code']) ?>"><?= htmlspecialchars($type['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <span id="filterCounter" class="text-muted" style="font-size: 0.9rem;">
                    Mostrando <strong><?= count($catalogs) ?></strong> servicios
                </span>
                <div style="position: relative; min-width: 250px;">
                    <i class="fa-solid fa-search" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.85rem;"></i>
                    <input type="text" id="serviceSearch" class="form-control form-control-sm" placeholder="Buscar examen o servicio..." style="padding-left: 2.2rem; font-size: 0.9rem;" oninput="searchServices(this.value)">
                </div>
            </div>
            <div class="table-wrapper">
                <table id="servicesTable">
                    <thead>
                        <tr><th>Tipo</th><th>Categoría</th><th>Examen / Servicio</th><th>Precio</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                        <?php if(empty($catalogs)): ?>
                        <tr class="empty-row"><td colspan="5" class="text-center text-muted">No hay servicios registrados.</td></tr>
                        <?php endif; ?>
                        <tr id="noServicesRow" style="display: none;">
                            <td colspan="5" class="text-center text-muted" style="padding: 2rem;">
                                <i class="fa-solid fa-folder-open" style="font-size: 2rem; margin-bottom: 0.5rem; display: block; opacity: 0.4;"></i>
                                No se encontraron servicios que coincidan con la búsqueda.
                            </td>
                        </tr>
                        <?php foreach($catalogs as $cat): ?>
                        <tr data-type="<?= htmlspecialchars($cat['catalog_type']) ?>">
                            <td><span class="badge badge-<?= $cat['color'] ?: 'primary' ?>"><?= htmlspecialchars($cat['type_name'] ?? $cat['catalog_type']) ?></span></td>
                            <td class="text-sm text-muted"><?= htmlspecialchars($cat['category']) ?></td>
                            <td><strong><?= htmlspecialchars($cat['name']) ?></strong></td>
                            <td>$<?= number_format($cat['price'], 2) ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <?php if (\App\Helpers\Auth::hasPermission('services_update')): ?>
                                    <button type="button" class="btn btn-sm btn-secondary"
                                        onclick="openEditModal('<?= \App\Helpers\HashId::encode($cat['id']) ?>', '<?= addslashes($cat['catalog_type']) ?>', '<?= addslashes($cat['category']) ?>', '<?= addslashes($cat['name']) ?>', '<?= $cat['price'] ?>')"
                                        title="Editar"><i class="fa-solid fa-pen"></i></button>
                                    <?php endif; ?>
                                    <?php if (\App\Helpers\Auth::hasPermission('services_delete')): ?>
                                    <form action="<?= $baseUrl ?>/admin/services/delete/<?= \App\Helpers\HashId::encode($cat['id']) ?>" method="POST" onsubmit="return confirm('¿Eliminar?')">
                                        <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                                        <button type="submit" class="btn btn-sm btn-danger"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div id="servicesPagination"></div>
        </div>
    </div>
</div>
<div class="schedule-panel <?= (isset($_GET['tab']) && $_GET['tab'] == 'manage') ? 'active' : '' ?>" id="panel-manage">
    <div class="card">
        <div class="card-body">
            <div class="alert alert-info mb-3">
                <i class="fa-solid fa-circle-info"></i> Gestione aquí las categorías y los ítems con sus respectivos precios para cada tipo de catálogo (Ej: Laboratorio -> Hematología -> Biometría Hemática).
            </div>
            <div class="accordion">
                <?php foreach($types as $type): ?>
                <div class="accordion-item">
                    <div class="accordion-header" onclick="toggleAccordion('acc-<?= $type['code'] ?>')">
                        <span><span class="badge badge-<?= $type['color'] ?>"><?= htmlspecialchars($type['name']) ?></span> (<?= $type['code'] ?>)</span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </div>
                    <div class="accordion-content" id="acc-<?= $type['code'] ?>">
                        <?php if (\App\Helpers\Auth::hasPermission('services_create')): ?>
                        <div class="mb-3 text-right">
                            <button class="btn btn-sm btn-primary" onclick="openCategoryModal('<?= $type['code'] ?>', '<?= htmlspecialchars($type['name']) ?>')">
                                <i class="fa-solid fa-folder-plus"></i> Nueva Categoría
                            </button>
                        </div>
                        <?php endif; ?>
                        <?php 
                        $hasCategories = false;
                        foreach($catalogCategories as $cat): 
                            if ($cat['catalog_type'] !== $type['code']) continue;
                            $hasCategories = true;
                        ?>
                            <div class="border rounded-md p-3 mb-3 bg-surface-1">
                                <div class="d-flex justify-content-between align-items-center mb-2 border-bottom pb-2">
                                    <h5 class="mb-0 text-primary"><i class="fa-solid fa-folder-open"></i> <?= htmlspecialchars($cat['name']) ?></h5>
                                    <div class="d-flex gap-2">
                                        <?php if (\App\Helpers\Auth::hasPermission('services_create')): ?>
                                        <button class="btn btn-sm btn-success" onclick="openItemModal('<?= $type['code'] ?>', '<?= htmlspecialchars($cat['name']) ?>')">
                                            <i class="fa-solid fa-plus"></i> Añadir Ítem
                                        </button>
                                        <?php endif; ?>
                                        <?php if (\App\Helpers\Auth::hasPermission('services_delete')): ?>
                                        <form action="<?= $baseUrl ?>/admin/services/category/delete/<?= \App\Helpers\HashId::encode($cat['id']) ?>" method="POST" onsubmit="return confirm('¿Seguro que desea eliminar esta categoría?')">
                                            <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Eliminar Categoría"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr>
                                            <th class="text-left py-1">Nombre del Ítem / Examen</th>
                                            <th class="text-right py-1">Precio ($)</th>
                                            <th class="text-center py-1 w-16">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $hasItems = false;
                                        foreach($catalogItems as $item): 
                                            if ($item['catalog_type'] !== $type['code'] || $item['category'] !== $cat['name']) continue;
                                            $hasItems = true;
                                        ?>
                                        <tr class="border-top">
                                            <td class="py-1"><?= htmlspecialchars($item['name']) ?></td>
                                            <td class="text-right font-weight-bold py-1">$<?= number_format($item['price'], 2) ?></td>
                                            <td class="text-center py-1">
                                                <?php if (\App\Helpers\Auth::hasPermission('services_delete')): ?>
                                                <form action="<?= $baseUrl ?>/admin/services/catalog-item/delete/<?= \App\Helpers\HashId::encode($item['id']) ?>" method="POST" onsubmit="return confirm('¿Eliminar este ítem?')">
                                                    <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                                                    <button type="submit" class="text-danger" style="background:none; border:none; cursor:pointer;" title="Eliminar Ítem"><i class="fa-solid fa-times"></i></button>
                                                </form>
                                                <?php else: ?>
                                                —
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if(!$hasItems): ?>
                                            <tr><td colspan="3" class="text-center py-2 text-muted">No hay ítems registrados en esta categoría.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endforeach; ?>
                        <?php if(!$hasCategories): ?>
                            <p class="text-center text-muted">No hay categorías para este tipo de catálogo.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h4 class="mb-0"><i class="fa-solid fa-pen"></i> Editar Servicio</h4>
            <button type="button" onclick="closeEditModal()" class="btn btn-sm btn-secondary">✕</button>
        </div>
        <form id="editForm" method="POST">
            <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
            <div class="form-group">
                <label class="form-label">Tipo de Catálogo *</label>
                <select name="catalog_type" id="e_type" class="form-control" required>
                    <option value="">Seleccione...</option>
                    <?php foreach($types as $type): ?>
                        <option value="<?= htmlspecialchars($type['code']) ?>"><?= htmlspecialchars($type['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Categoría *</label>
                <select name="category" id="e_cat" class="form-control" required>
                    <option value="">Seleccione tipo primero...</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Nombre *</label>
                <input type="text" name="name" id="e_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Precio ($) *</label>
                <input type="number" step="0.01" name="price" id="e_price" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-full"><i class="fa-solid fa-save"></i> Guardar</button>
        </form>
    </div>
</div>
<div id="modalCategory" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h4 class="mb-0">Nueva Categoría</h4>
            <button class="btn btn-sm btn-secondary" onclick="closeModals()"><i class="fa-solid fa-times"></i></button>
        </div>
        <form action="<?= $baseUrl ?>/admin/services/category/store" method="POST">
            <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
            <input type="hidden" name="catalog_type" id="modalCatType">
            <div class="form-group">
                <label class="form-label">Tipo de Catálogo</label>
                <input type="text" id="modalCatTypeName" class="form-control" readonly disabled>
            </div>
            <div class="form-group">
                <label class="form-label">Nombre de Categoría *</label>
                <input type="text" name="name" class="form-control" required placeholder="Ej: Hematología">
            </div>
            <button type="submit" class="btn btn-primary w-full"><i class="fa-solid fa-save"></i> Guardar Categoría</button>
        </form>
    </div>
</div>
<div id="modalItem" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h4 class="mb-0">Nuevo Ítem / Examen</h4>
            <button class="btn btn-sm btn-secondary" onclick="closeModals()"><i class="fa-solid fa-times"></i></button>
        </div>
        <form action="<?= $baseUrl ?>/admin/services/catalog-item/store" method="POST">
            <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
            <input type="hidden" name="catalog_type_hidden" id="modalItemType">
            <input type="hidden" name="category" id="modalItemCategory">
            <div class="form-group">
                <label class="form-label">Categoría</label>
                <input type="text" id="modalItemCatName" class="form-control" readonly disabled>
            </div>
            <div class="form-group">
                <label class="form-label">Nombre del Ítem / Examen *</label>
                <input type="text" name="name" class="form-control" required placeholder="Ej: Biometría Hemática">
            </div>
            <div class="form-group">
                <label class="form-label">Precio ($) *</label>
                <input type="number" name="price" step="0.01" min="0" class="form-control" required placeholder="0.00">
            </div>
            <button type="submit" class="btn btn-primary w-full"><i class="fa-solid fa-save"></i> Guardar Ítem</button>
        </form>
    </div>
</div>
<style>
/* ---- Tabs ---- */
.schedule-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    background: var(--surface);
    padding: 0.4rem;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-light);
    box-shadow: var(--shadow-sm);
}
.schedule-tab {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    padding: 0.85rem 1.25rem;
    border: none;
    background: transparent;
    border-radius: var(--radius);
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-muted);
    cursor: pointer;
    transition: var(--transition);
}
.schedule-tab:hover {
    color: var(--primary);
    background: var(--primary-50);
}
.schedule-tab.active {
    background: var(--gradient-primary);
    color: #fff;
    box-shadow: 0 4px 14px rgba(14,165,233,.3);
}
.schedule-panel {
    display: none;
    animation: slideUp 0.35s ease;
}
.schedule-panel.active {
    display: block;
}
/* ---- Filter Pills ---- */
.service-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1.25rem;
    padding-bottom: 1.25rem;
    border-bottom: 1px solid var(--border-light);
}
.filter-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 1.1rem;
    border-radius: 50px;
    border: 1.5px solid var(--border);
    background: var(--surface);
    color: var(--text-secondary);
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.25s ease;
    white-space: nowrap;
}
.filter-pill:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--primary-50);
}
.filter-pill.active {
    background: var(--gradient-primary);
    color: #fff;
    border-color: transparent;
    box-shadow: 0 3px 10px rgba(14,165,233,.3);
}
.filter-pill.active:hover {
    opacity: 0.9;
}
#servicesTable tbody tr {
    transition: opacity 0.2s ease;
}
#servicesTable tbody tr.hidden-row {
    display: none;
}
/* ---- Accordion Styles ---- */
.accordion-item { border: 1px solid var(--border-light); margin-bottom: 0.5rem; border-radius: var(--radius-sm); }
.accordion-header { padding: 0.75rem 1rem; background: var(--surface-2); cursor: pointer; font-weight: bold; display: flex; justify-content: space-between; align-items: center; border-radius: var(--radius-sm); }
.accordion-content { padding: 1rem; display: none; background: #fff; }
.accordion-content.active { display: block; }
/* ---- Modals ---- */
.modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
.modal.active { display: flex; }
.modal-content { background: #fff; padding: 1.5rem; border-radius: var(--radius-md); width: 100%; max-width: 400px; }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
</style>
<script>
// ---- Tab Switching ----
document.querySelectorAll('.schedule-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.schedule-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.schedule-panel').forEach(p => p.classList.remove('active'));
        this.classList.add('active');
        document.getElementById('panel-' + this.dataset.tab).classList.add('active');
        // Update URL to preserve tab
        if(history.pushState) {
            var newurl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?tab=' + this.dataset.tab;
            window.history.pushState({path:newurl},'',newurl);
        }
    });
});
// ---- General Table Filtering & Pagination ----
let currentFilter = 'all';
let currentSearch = '';
let currentServicePage = 1;
const servicesPerPage = 10;
function filterByType(typeCode) {
    currentFilter = typeCode;
    currentServicePage = 1;
    applyFilters();
}
function searchServices(query) {
    currentSearch = query.toLowerCase().trim();
    currentServicePage = 1;
    applyFilters();
}
function goToServicePage(page) {
    currentServicePage = page;
    applyFilters();
}
function applyFilters() {
    const rows = Array.from(document.querySelectorAll('#servicesTable tbody tr:not(#noServicesRow):not(.empty-row)'));
    const matchedRows = [];
    rows.forEach(row => {
        const rowType = row.getAttribute('data-type');
        const rowText = row.textContent.toLowerCase();
        const matchesType = (currentFilter === 'all' || rowType === currentFilter);
        const matchesSearch = (currentSearch === '' || rowText.includes(currentSearch));
        if (matchesType && matchesSearch) {
            matchedRows.push(row);
        } else {
            row.style.display = 'none';
        }
    });
    const totalRecords = matchedRows.length;
    const totalPages = Math.ceil(totalRecords / servicesPerPage) || 1;
    if (currentServicePage > totalPages) currentServicePage = totalPages;
    if (currentServicePage < 1) currentServicePage = 1;
    const startIdx = (currentServicePage - 1) * servicesPerPage;
    const endIdx = startIdx + servicesPerPage;
    matchedRows.forEach((row, idx) => {
        if (idx >= startIdx && idx < endIdx) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
    const noRow = document.getElementById('noServicesRow');
    if (noRow) {
        noRow.style.display = totalRecords === 0 ? '' : 'none';
    }
    const counterEl = document.getElementById('filterCounter');
    if (counterEl) {
        counterEl.innerHTML = 'Mostrando <strong>' + totalRecords + '</strong> servicio' + (totalRecords !== 1 ? 's' : '');
    }
    renderServicesPagination(currentServicePage, totalPages, totalRecords);
}
function renderServicesPagination(currentPage, totalPages, totalRecords) {
    const container = document.getElementById('servicesPagination');
    if (!container) return;
    if (totalRecords === 0 || totalPages <= 1) {
        container.innerHTML = totalRecords > 0 
            ? '<div class="pagination-info text-sm text-muted" style="padding: 0.75rem 1rem; border-top: 1px solid var(--border-light);">Total: <strong>' + totalRecords + '</strong> servicio(s)</div>'
            : '';
        return;
    }
    let html = '<div class="pagination-wrapper">';
    html += '<span class="text-sm text-muted">Total: <strong>' + totalRecords + '</strong> servicio(s) &mdash; Página <strong>' + currentPage + '</strong> de <strong>' + totalPages + '</strong></span>';
    html += '<nav class="pagination">';
    // Anterior
    if (currentPage <= 1) {
        html += '<span class="page-btn disabled"><i class="fa-solid fa-chevron-left"></i></span>';
    } else {
        html += '<button type="button" class="page-btn" onclick="goToServicePage(' + (currentPage - 1) + ')"><i class="fa-solid fa-chevron-left"></i></button>';
    }
    const delta = 2;
    let start = Math.max(1, currentPage - delta);
    let end = Math.min(totalPages, currentPage + delta);
    if (currentPage - delta < 1) end = Math.min(totalPages, end + (delta - (currentPage - 1)));
    if (currentPage + delta > totalPages) start = Math.max(1, start - (delta - (totalPages - currentPage)));
    if (start > 1) {
        html += '<button type="button" class="page-btn" onclick="goToServicePage(1)">1</button>';
        if (start > 2) html += '<span class="page-btn disabled">&hellip;</span>';
    }
    for (let i = start; i <= end; i++) {
        if (i === currentPage) {
            html += '<span class="page-btn active">' + i + '</span>';
        } else {
            html += '<button type="button" class="page-btn" onclick="goToServicePage(' + i + ')">' + i + '</button>';
        }
    }
    if (end < totalPages) {
        if (end < totalPages - 1) html += '<span class="page-btn disabled">&hellip;</span>';
        html += '<button type="button" class="page-btn" onclick="goToServicePage(' + totalPages + ')">' + totalPages + '</button>';
    }
    // Siguiente
    if (currentPage >= totalPages) {
        html += '<span class="page-btn disabled"><i class="fa-solid fa-chevron-right"></i></span>';
    } else {
        html += '<button type="button" class="page-btn" onclick="goToServicePage(' + (currentPage + 1) + ')"><i class="fa-solid fa-chevron-right"></i></button>';
    }
    html += '</nav></div>';
    container.innerHTML = html;
}
document.addEventListener('DOMContentLoaded', function() {
    applyFilters();
});
// ---- Modals & Accordion ----
// Categories Data for Dynamic Dropdown
const allCategories = <?= json_encode($catalogCategories) ?>;
document.getElementById('e_type').addEventListener('change', function() {
    updateCategoryDropdown(this.value, '');
});
function updateCategoryDropdown(typeCode, selectedCat) {
    const catSelect = document.getElementById('e_cat');
    catSelect.innerHTML = '<option value="">Seleccione...</option>';
    if(!typeCode) return;
    const filteredCats = allCategories.filter(c => c.catalog_type === typeCode);
    filteredCats.forEach(cat => {
        const option = document.createElement('option');
        option.value = cat.name;
        option.textContent = cat.name;
        if(cat.name === selectedCat) {
            option.selected = true;
        }
        catSelect.appendChild(option);
    });
}
function openEditModal(id, type, cat, name, price) {
    document.getElementById('editForm').action = '<?= $baseUrl ?>/admin/services/update/' + id;
    document.getElementById('e_type').value = type;
    updateCategoryDropdown(type, cat);
    document.getElementById('e_name').value = name;
    document.getElementById('e_price').value = price;
    document.getElementById('editModal').classList.add('active');
}
function toggleAccordion(id) {
    const content = document.getElementById(id);
    const header = content.previousElementSibling;
    const icon = header.querySelector('i');
    if (content.classList.contains('active')) {
        content.classList.remove('active');
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
    } else {
        content.classList.add('active');
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
    }
}
function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
}
function openCategoryModal(typeCode, typeName) {
    document.getElementById('modalCatType').value = typeCode;
    document.getElementById('modalCatTypeName').value = typeName;
    document.getElementById('modalCategory').classList.add('active');
}
function openItemModal(typeCode, categoryName) {
    document.getElementById('modalItemType').value = typeCode;
    document.getElementById('modalItemCategory').value = categoryName;
    document.getElementById('modalItemCatName').value = categoryName;
    document.getElementById('modalItem').classList.add('active');
}
function closeModals() {
    document.getElementById('modalCategory').classList.remove('active');
    document.getElementById('modalItem').classList.remove('active');
    closeEditModal();
}
</script>
