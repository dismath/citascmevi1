<div class="card">
    <div class="card-header">
        <h3><i class="fa-solid fa-users text-primary"></i> Mis Pacientes Atendidos</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if(empty($patients)): ?>
            <div class="p-4 text-center text-muted">
                <i class="fa-solid fa-users-slash" style="font-size: 2rem; display: block; margin-bottom: 1rem; color: var(--border);"></i>
                Aún no has atendido a ningún paciente.
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Paciente</th>
                            <th>Identificación</th>
                            <th>Teléfono</th>
                            <th>Última Atención</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($patients as $patient): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($patient['name']) ?></strong>
                            </td>
                            <td><?= htmlspecialchars($patient['id_number']) ?></td>
                            <td><?= htmlspecialchars($patient['phone']) ?></td>
                            <td>
                                <?php if($patient['last_visit']): ?>
                                    <?= date('d/m/Y', strtotime($patient['last_visit'])) ?>
                                <?php else: ?>
                                    <span class="text-muted">N/D</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?= $baseUrl ?>/doctor/patients/<?= $patient['id'] ?>/history" class="btn btn-sm btn-secondary">
                                    <i class="fa-solid fa-clock-rotate-left"></i> Ver Historial
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
