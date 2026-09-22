<div class="card max-w-3xl mx-auto">
    <div class="card-header">
        <h3>Editar Usuario</h3>
    </div>
    <div class="card-body">
        <form action="<?= $baseUrl ?>/admin/users/update/<?= $user['id'] ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
            <div class="form-group">
                <label class="form-label">Correo Electrónico (Login) *</label>
                <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($user['email'] ?? '') ?>">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="password" class="form-control" placeholder="Dejar en blanco para no cambiar" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label class="form-label">Rol del Usuario *</label>
                    <?php if($currentRoleName === 'doctor'): ?>
                        <input type="text" class="form-control" value="Doctor" disabled>
                        <input type="hidden" name="role_id" value="<?= $currentRoleId ?>">
                        <small class="form-text text-muted">El rol de un médico no puede ser cambiado desde aquí.</small>
                    <?php else: ?>
                        <select name="role_id" class="form-control" required>
                            <option value="">Seleccione...</option>
                            <?php foreach($roles as $role): ?>
                                <?php 
                                if (in_array(strtolower($role['name']), ['doctor', 'medico', 'patient', 'paciente'])) continue;
                                $rLabel = !empty($role['display_name']) ? $role['display_name'] : ucfirst($role['name']);
                                if (strtoupper($rLabel) === 'TECNICO') $rLabel = 'Técnico';
                                if (strtolower($role['name']) === 'admin') $rLabel = 'Administrador';
                                ?>
                                <option value="<?= $role['id'] ?>" <?= $role['id'] == $currentRoleId ? 'selected' : '' ?>><?= htmlspecialchars($rLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <a href="<?= $baseUrl ?>/admin/users" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left"></i> Volver
                </a>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-save"></i> Actualizar Usuario
                </button>
            </div>
        </form>
    </div>
</div>
