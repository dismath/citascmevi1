<div class="card">
    <div class="card-header d-flex justify-between align-center">
        <h2><i class="fa-solid fa-file-invoice-dollar"></i> Detalle de la Orden #<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></h2>
        <div>
            <a href="<?= $baseUrl ?>/admin/orders" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Volver</a>
            <a href="<?= $baseUrl ?>/admin/orders/download/<?= $order['id'] ?>" target="_blank" class="btn btn-info"><i class="fa-solid fa-download"></i> Descargar PDF</a>
        </div>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div class="card bg-light">
                <div class="card-body">
                    <h4>Datos del Paciente</h4>
                    <hr>
                    <p><strong>Paciente:</strong> <?= htmlspecialchars($order['patient_name']) ?></p>
                    <p><strong>CI:</strong> <?= htmlspecialchars($order['patient_id_number']) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($order['patient_email']) ?></p>
                </div>
            </div>
            <div class="card bg-light">
                <div class="card-body">
                    <h4>Detalles de la Orden</h4>
                    <hr>
                    <p><strong>Cita Relacionada:</strong> <?= date('d/m/Y H:i', strtotime($order['appointment_date'])) ?></p>
                    <p><strong>Especialidad:</strong> <?= htmlspecialchars($order['specialty_name']) ?></p>
                    <p><strong>Fecha Generación:</strong> <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></p>
                    <p><strong>Estado:</strong>
                        <?php if($order['status'] == 'paid'): ?>
                            <span class="badge bg-success">Pagada</span>
                        <?php elseif($order['status'] == 'cancelled'): ?>
                            <span class="badge bg-danger">Cancelada</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Pendiente</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
        <h4 class="mt-4">Servicios Incluidos</h4>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Servicio</th>
                        <th class="text-center">Cantidad</th>
                        <th class="text-right">Precio Unitario</th>
                        <th class="text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($items as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['item_name']) ?></td>
                            <td class="text-center"><?= $item['quantity'] ?></td>
                            <td class="text-right">$<?= number_format($item['price'], 2) ?></td>
                            <td class="text-right">$<?= number_format($item['subtotal'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" class="text-right">TOTAL:</th>
                        <th class="text-right"><strong>$<?= number_format($order['total'], 2) ?></strong></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
