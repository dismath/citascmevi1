<div class="card max-w-3xl mx-auto">
    <div class="card-header">
        <h3>Editar Cita #<?= $appointment['id'] ?></h3>
    </div>
    <div class="card-body">
        <form action="<?= $baseUrl ?>/admin/appointments/update/<?= $appointment['id'] ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Paciente</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($appointment['patient_name']) ?> (<?= htmlspecialchars($appointment['patient_id_number']) ?>)" disabled>
                </div>
                <div class="form-group">
                    <label class="form-label">Médico</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($appointment['doctor_name']) ?> - <?= htmlspecialchars($appointment['specialty_name']) ?>" disabled>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Fecha y Hora</label>
                    <input type="text" class="form-control" value="<?= date('d/m/Y H:i', strtotime($appointment['appointment_date'])) ?>" disabled>
                </div>
                <div class="form-group">
                    <label class="form-label">Estado *</label>
                    <select name="status" class="form-control" required>
                        <option value="pending" <?= $appointment['status'] == 'pending' ? 'selected' : '' ?>>Pendiente</option>
                        <option value="confirmed" <?= $appointment['status'] == 'confirmed' ? 'selected' : '' ?>>Confirmada</option>
                        <option value="completed" <?= $appointment['status'] == 'completed' ? 'selected' : '' ?>>Completada</option>
                        <option value="cancelled" <?= $appointment['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelada</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Notas / Observaciones</label>
                <textarea name="notes" class="form-control" style="min-height: 100px;"><?= htmlspecialchars($appointment['notes'] ?? '') ?></textarea>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <a href="<?= $baseUrl ?>/admin/appointments" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left"></i> Volver
                </a>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-save"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>
