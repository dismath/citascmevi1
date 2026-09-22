<div class="card max-w-3xl mx-auto">
    <div class="card-header">
        <h3>Registrar Nuevo Usuario</h3>
    </div>
    <div class="card-body">
        <form action="<?= $baseUrl ?>/admin/users/store" method="POST">
            <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
            <div class="form-group">
                <label class="form-label">Correo Electrónico (Login) *</label>
                <input type="email" name="email" class="form-control" required>
                <small class="text-muted">Se enviará un correo a esta dirección con las credenciales de acceso temporales.</small>
            </div>
            <div class="form-row">
                <div class="form-group" style="width: 100%;">
                    <label class="form-label">Rol del Usuario *</label>
                    <select name="role_id" class="form-control" required>
                        <option value="">Seleccione...</option>
                        <?php foreach($roles as $role): ?>
                            <?php 
                            if (in_array(strtolower($role['name']), ['doctor', 'medico', 'patient', 'paciente'])) continue;
                            $rLabel = !empty($role['display_name']) ? $role['display_name'] : ucfirst($role['name']);
                            if (strtoupper($rLabel) === 'TECNICO') $rLabel = 'Técnico';
                            if (strtolower($role['name']) === 'admin') $rLabel = 'Administrador';
                            ?>
                            <option value="<?= $role['id'] ?>"><?= htmlspecialchars($rLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="alert alert-warning text-sm">
                <i class="fa-solid fa-triangle-exclamation"></i> Para crear un usuario "Médico", utilice el módulo <strong>Gestión de Médicos</strong>, ya que requiere información adicional.
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <a href="<?= $baseUrl ?>/admin/users" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left"></i> Volver
                </a>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-save"></i> Guardar Usuario
                </button>
            </div>
        </form>
    </div>
</div>
