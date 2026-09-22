<style>
.order-create-grid {
    display: grid;
    grid-template-columns: 360px 1fr;
    gap: 2rem;
    align-items: start;
}
.order-info-card {
    background: var(--surface-2, #f8f9fa);
    border: 1px solid var(--border, #dee2e6);
    border-radius: 12px;
    padding: 1.5rem;
}
.order-info-card h4 {
    color: var(--primary, #4361ee);
    margin-bottom: 0.5rem;
}
.order-info-card p {
    margin: 0.4rem 0;
    font-size: 0.95rem;
}
.order-num-badge {
    display: inline-block;
    background: linear-gradient(135deg, #4361ee, #7b2d8b);
    color: #fff;
    font-size: 1.1em;
    font-weight: 700;
    padding: 0.3em 0.9em;
    border-radius: 6px;
    letter-spacing: 1px;
}
.order-form-panel {
    background: #fff;
    border: 1px solid var(--border, #dee2e6);
    border-radius: 12px;
    padding: 1.5rem;
}
.items-table {
    border: 1px solid var(--border, #dee2e6);
    border-radius: 8px;
    overflow: hidden;
    width: 100%;
}
.items-table thead { background: var(--surface-2, #f8f9fa); }
.items-table th, .items-table td { padding: 0.75rem 1rem; text-align: left; }
.items-table tfoot { background: var(--surface-2, #f8f9fa); font-weight: 700; }
.total-amount { color: #2d8a4e; font-size: 1.2em; }
@media (max-width: 768px) {
    .order-create-grid { grid-template-columns: 1fr; }
}
</style>
<div class="card">
    <div class="card-header d-flex justify-between align-center">
        <h2><i class="fa-solid fa-file-invoice-dollar"></i> Generar Orden / Prefactura</h2>
        <a href="<?= $baseUrl ?>/admin/appointments" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Volver a Citas
        </a>
    </div>
    <div class="card-body">
        <div class="order-create-grid">
            <div>
                <div class="order-info-card">
                    <h4><i class="fa-solid fa-user-injured"></i> Datos del Paciente</h4>
                    <hr style="margin: 0.5rem 0;">
                    <p><strong>Nombre:</strong> <?= htmlspecialchars($appointment['patient_name']) ?></p>
                    <p><strong>CI / RUC:</strong> <?= htmlspecialchars($appointment['patient_id_number']) ?></p>
                    <p><strong>Teléfono:</strong> <?= htmlspecialchars($appointment['patient_phone'] ?? '—') ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($appointment['patient_email'] ?? '—') ?></p>
                </div>
                <div class="order-info-card" style="margin-top: 1.5rem;">
                    <h4><i class="fa-regular fa-calendar-check"></i> Datos de la Cita</h4>
                    <hr style="margin: 0.5rem 0;">
                    <p><strong>Nro. de Orden:</strong><br>
                        <span class="order-num-badge">#<?= str_pad($nextOrderId, 6, '0', STR_PAD_LEFT) ?></span>
                    </p>
                    <p style="margin-top: 0.6rem;"><strong>Médico:</strong> <?= htmlspecialchars($appointment['doctor_name']) ?></p>
                    <p><strong>Especialidad:</strong> <?= htmlspecialchars($appointment['specialty_name']) ?></p>
                    <p><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($appointment['appointment_date'])) ?></p>
                    <p><strong>Hora:</strong> <?= date('H:i', strtotime($appointment['appointment_date'])) ?></p>
                </div>
            </div>
            <div class="order-form-panel">
                <h4 style="margin-bottom: 1rem;"><i class="fa-solid fa-list-check"></i> Detalle de Servicios</h4>
                <form action="<?= $baseUrl ?>/admin/orders/store" method="POST" id="orderForm" onsubmit="return handleOrderSubmit(this)">
                    <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                    <input type="hidden" name="appointment_id" value="<?= $appointment['id'] ?>">
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label">Buscar y agregar servicio</label>
                        <div class="d-flex gap-2">
                            <select id="catalogSelect" class="form-control select2" style="flex: 1;">
                                <option value="">Cargando servicios disponibles...</option>
                            </select>
                            <button type="button" class="btn btn-secondary" onclick="addOrderItem()">
                                <i class="fa-solid fa-plus"></i> Agregar
                            </button>
                        </div>
                    </div>
                    <div style="overflow-x: auto; margin-bottom: 1rem;">
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th style="width:45%">Servicio</th>
                                    <th style="width:12%">Cant.</th>
                                    <th style="width:18%">P. Unitario</th>
                                    <th style="width:18%">Subtotal</th>
                                    <th style="width:7%"></th>
                                </tr>
                            </thead>
                            <tbody id="orderItemsBody">
                                <tr id="emptyRow">
                                    <td colspan="5" class="text-center text-muted" style="padding: 2rem;">
                                        <i class="fa-solid fa-cart-shopping" style="font-size: 2rem; opacity: 0.3;"></i><br>
                                        Aún no hay servicios agregados.
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" style="text-align: right; padding-right: 1rem;">TOTAL:</th>
                                    <th id="orderTotal" class="total-amount" colspan="2">$0.00</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div style="text-align: right;">
                        <a href="<?= $baseUrl ?>/admin/appointments" class="btn btn-secondary" style="margin-right: 0.5rem;">Cancelar</a>
                        <button type="submit" class="btn btn-primary" id="btnSaveOrder" disabled>
                            <i class="fa-solid fa-file-pdf"></i> Generar Orden y Enviar PDF
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
let currentCatalogItems = [];
let addedItems = [];
document.addEventListener('DOMContentLoaded', function () {
    loadCatalogItems(<?= (int)$appointment['id'] ?>);
});
function loadCatalogItems(appointmentId) {
    const select = document.getElementById('catalogSelect');
    // Destruir Select2 si ya está activo antes de modificar opciones
    if (typeof $ !== 'undefined' && $.fn && $.fn.select2 && $('#catalogSelect').data('select2')) {
        $('#catalogSelect').select2('destroy');
    }
    fetch('<?= $baseUrl ?>/admin/orders/catalog-items/' + appointmentId, { credentials: 'same-origin' })
        .then(res => {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(data => {
            currentCatalogItems = data.items || [];
            if (currentCatalogItems.length === 0) {
                select.innerHTML = '<option value="">No hay servicios configurados para esta especialidad.</option>';
            } else {
                select.innerHTML = '<option value="">-- Seleccione un servicio --</option>';
                currentCatalogItems.forEach(item => {
                    const opt = document.createElement('option');
                    opt.value = item.id;
                    const categoryPrefix = item.category ? item.category.toUpperCase() + '--->' : '';
                    opt.textContent = categoryPrefix + item.text + '   $' + parseFloat(item.price).toFixed(2);
                    select.appendChild(opt);
                });
            }
            // Inicializar Select2 después de cargar opciones
            if (typeof $ !== 'undefined' && $.fn && $.fn.select2) {
                $('#catalogSelect').select2({
                    width: '100%',
                    placeholder: '-- Buscar servicio --',
                    allowClear: true
                });
            }
        })
        .catch((err) => {
            console.error('Error cargando catálogo:', err);
            select.innerHTML = '<option value="">Error al cargar servicios</option>';
        });
}
function addOrderItem() {
    const select = document.getElementById('catalogSelect');
    const catalogId = select.value;
    if (!catalogId) return;
    if (addedItems.find(i => i.id == catalogId)) {
        alert('Este servicio ya está en la lista. Cambie la cantidad si necesita más de uno.');
        return;
    }
    const item = currentCatalogItems.find(i => i.id == catalogId);
    if (item) {
        addedItems.push({ id: item.id, name: item.text, price: parseFloat(item.price), qty: 1 });
        renderOrderItems();
        if (typeof $ !== 'undefined' && $.fn && $.fn.select2) {
            $('#catalogSelect').val('').trigger('change');
        } else {
            select.value = '';
        }
    }
}
function removeOrderItem(catalogId) {
    addedItems = addedItems.filter(i => i.id != catalogId);
    renderOrderItems();
}
function updateQty(catalogId, qty) {
    const item = addedItems.find(i => i.id == catalogId);
    if (item) {
        item.qty = Math.max(1, parseInt(qty) || 1);
        renderOrderItems();
    }
}
function renderOrderItems() {
    const tbody = document.getElementById('orderItemsBody');
    tbody.innerHTML = '';
    let total = 0;
    if (addedItems.length === 0) {
        tbody.innerHTML = `<tr id="emptyRow"><td colspan="5" class="text-center text-muted" style="padding:2rem;">
            <i class="fa-solid fa-cart-shopping" style="font-size:2rem;opacity:0.3;"></i><br>
            Aún no hay servicios agregados.</td></tr>`;
        document.getElementById('orderTotal').textContent = '$0.00';
        document.getElementById('btnSaveOrder').disabled = true;
        return;
    }
    addedItems.forEach((item, index) => {
        const subtotal = item.price * item.qty;
        total += subtotal;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                ${item.name}
                <input type="hidden" name="items[${index}][id]" value="${item.id}">
            </td>
            <td>
                <input type="number" name="items[${index}][qty]" value="${item.qty}" min="1"
                    class="form-control" style="width:60px; padding:0.25rem; text-align:center;"
                    onchange="updateQty(${item.id}, this.value)">
            </td>
            <td>$${item.price.toFixed(2)}</td>
            <td><strong>$${subtotal.toFixed(2)}</strong></td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeOrderItem(${item.id})" title="Eliminar">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
    document.getElementById('orderTotal').textContent = '$' + total.toFixed(2);
    document.getElementById('btnSaveOrder').disabled = false;
}
function handleOrderSubmit(form) {
    const btn = document.getElementById('btnSaveOrder');
    if (btn.dataset.submitted === 'true') {
        return false; // Block duplicate submission
    }
    btn.dataset.submitted = 'true';
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generando...';
    return true;
}
</script>
