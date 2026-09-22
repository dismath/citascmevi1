<div class="card">
    <div class="card-body">
        <?php $success = \App\Helpers\Session::getFlash('success'); if ($success): ?>
            <div class="alert alert-success mb-3"><?= $success ?></div>
        <?php endif; ?>
        <?php $error = \App\Helpers\Session::getFlash('error'); if ($error): ?>
            <div class="alert alert-danger mb-3"><?= $error ?></div>
        <?php endif; ?>
        <div class="d-flex justify-between align-center mb-3">
            <div style="flex: 1; max-width: 300px;">
                <div class="search-box" style="position: relative;">
                    <i class="fa-solid fa-search" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                    <input type="text" id="orderSearch" class="form-control" placeholder="Buscar por número o paciente..." style="padding-left: 35px; width: 100%;">
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="datatable">
                <thead>
                    <tr>
                        <th>Nro. Orden</th>
                        <th>Fecha</th>
                        <th>Paciente</th>
                        <th>Total</th>
                        <th>Estado Facturación</th>
                        <th>Email</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                        <?php foreach($orders as $order): ?>
                            <tr>
                                <td><strong>#<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></strong></td>
                                <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                                <td><?= htmlspecialchars($order['patient_name']) ?></td>
                                <td>$<?= number_format($order['total'], 2) ?></td>
                                <td>
                                    <?php
                                    $orderStatus = strtolower(trim($order['status']));
                                    $orderStatusColors = [
                                        'pending' => 'warning text-dark',
                                        'paid' => 'success',
                                        'cancelled' => 'danger'
                                    ];
                                    $badgeClass = 'badge bg-' . ($orderStatusColors[$orderStatus] ?? 'secondary');
                                    $orderStatusOrder = ['pending' => 0, 'paid' => 1, 'cancelled' => 99];
                                    $currentOrderLevel = $orderStatusOrder[$orderStatus] ?? 0;
                                    $isOrderDisabled = in_array($orderStatus, ['paid', 'cancelled']) ? 'disabled' : '';
                                    ?>
                                    <form action="<?= $baseUrl ?>/admin/orders/status/<?= \App\Helpers\HashId::encode($order['id']) ?>" method="POST" class="d-inline" style="margin-bottom: 0;">
                                        <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                                        <select name="status" class="form-control form-control-sm <?= $badgeClass ?>" style="width: auto; display: inline-block; padding: 0.1rem 0.5rem; height: auto; border: none; font-weight: bold; cursor: <?= $isOrderDisabled ? 'not-allowed' : 'pointer' ?>;" onchange="this.form.submit()" <?= $isOrderDisabled ?>>
                                            <option value="pending" <?= $orderStatus == 'pending' ? 'selected' : '' ?> <?= $currentOrderLevel > 0 ? 'disabled' : '' ?>>Pendiente</option>
                                            <option value="paid" <?= $orderStatus == 'paid' ? 'selected' : '' ?> <?= $currentOrderLevel > 1 ? 'disabled' : '' ?>>Pagada</option>
                                            <option value="cancelled" <?= $orderStatus == 'cancelled' ? 'selected' : '' ?>>Anulada</option>
                                        </select>
                                    </form>
                                </td>
                                <td>
                                    <?php if ($order['email_sent'] == 1): ?>
                                        <span class="text-success" title="Enviado correctamente"><i class="fa-solid fa-circle-check"></i></span>
                                    <?php elseif (!empty($order['patient_email'])): ?>
                                        <span class="text-danger" title="Error al enviar"><i class="fa-solid fa-circle-xmark"></i></span>
                                    <?php else: ?>
                                        <span class="text-muted" title="Sin correo"><i class="fa-solid fa-envelope"></i></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <?php if (\App\Helpers\Auth::hasPermission('appointments_update') && in_array($order['app_status'] ?? '', ['pending', 'confirmed'])): ?>
                                            <?php if ($maxReschedules === 0 || ($order['reschedule_count'] ?? 0) < $maxReschedules): ?>
                                                <a href="<?= $baseUrl ?>/admin/appointments/reschedule/<?= \App\Helpers\HashId::encode($order['appointment_id']) ?>?return=orders" class="btn btn-sm btn-warning" title="Reagendar"><i class="fa-regular fa-calendar-days"></i></a>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-secondary" disabled title="Límite de reagendamientos alcanzado"><i class="fa-regular fa-calendar-days"></i></button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if($order['attachment_url']): ?>
                                            <a href="<?= $baseUrl ?>/admin/appointments/attachment/<?= \App\Helpers\HashId::encode($order['appointment_id']) ?>" target="_blank" class="btn btn-sm btn-info" title="Ver Comprobante">
                                                <i class="fas fa-file-invoice"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?= $baseUrl ?>/admin/orders/show/<?= \App\Helpers\HashId::encode($order['id']) ?>" class="btn btn-sm btn-secondary" title="Ver Detalle">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <?php if (\App\Helpers\Auth::hasPermission('orders_update')): ?>
                                        <a href="<?= $baseUrl ?>/admin/orders/edit/<?= \App\Helpers\HashId::encode($order['id']) ?>" class="btn btn-sm btn-primary" title="Editar Estado">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                        <?php endif; ?>
                                        <a href="<?= $baseUrl ?>/admin/orders/download/<?= \App\Helpers\HashId::encode($order['id']) ?>" target="_blank" class="btn btn-sm btn-info" title="Descargar PDF">
                                            <i class="fa-solid fa-download"></i>
                                        </a>
                                        <?php if (\App\Helpers\Auth::hasPermission('orders_delete')): ?>
                                        <form action="<?= $baseUrl ?>/admin/orders/delete/<?= \App\Helpers\HashId::encode($order['id']) ?>" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar esta orden de forma permanente?');">
                                            <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
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
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('orderSearch');
    const tableBody = document.querySelector('.table tbody');
    const rows = tableBody.querySelectorAll('tr:not(.empty-row)');
    // Si la tabla no tiene datos reales (solo el mensaje de vacío) no hacemos nada
    if (rows.length === 0 || (rows.length === 1 && rows[0].cells.length === 1)) {
        return;
    }
    searchInput.addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        rows.forEach(row => {
            const textContent = row.textContent.toLowerCase();
            if (textContent.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
});
</script>
