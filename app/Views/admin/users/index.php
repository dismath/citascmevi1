<div class="card">
    <div class="card-header">
        <h3>Usuarios del Sistema</h3>
        <?php if (\App\Helpers\Auth::hasPermission('users_create')): ?>
        <a href="<?= $baseUrl ?>/admin/users/create" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Nuevo Usuario</a>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Email / Usuario</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Creación</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted" style="padding: 2.5rem 1rem;">
                            <i class="fa-solid fa-users-slash" style="font-size: 2rem; display: block; margin-bottom: 0.5rem; opacity: 0.5;"></i>
                            No se encontraron usuarios registrados.
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach($users as $user): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td>
                            <?php if (!empty($user['email'])): ?>
                                <strong><?= htmlspecialchars($user['email']) ?></strong>
                                <?php if (!empty($user['nombre_usuario'])): ?>
                                    <div class="text-sm text-muted"><?= htmlspecialchars($user['nombre_usuario']) ?></div>
                                <?php endif; ?>
                            <?php elseif (!empty($user['nombre_usuario'])): ?>
                                <strong><?= htmlspecialchars($user['nombre_usuario']) ?></strong>
                                <div class="text-sm text-muted">Sin correo</div>
                            <?php else: ?>
                                <span class="text-muted">Sin identificar</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($user['roles'])): ?>
                                <?php foreach(explode(',', $user['roles']) as $role): ?>
                                    <span class="badge badge-info"><?= htmlspecialchars(ucfirst(trim($role))) ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-muted">Sin Rol</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= ($user['status'] ?? '') === 'active' ? 'badge-success' : 'badge-danger' ?>">
                                <?= ($user['status'] ?? '') === 'active' ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td class="text-sm text-muted"><?= !empty($user['created_at']) ? date('d/m/Y', strtotime($user['created_at'])) : '—' ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <?php if (\App\Helpers\Auth::hasPermission('users_update')): ?>
                                    <?php if ($user['id'] != \App\Helpers\Session::userId()): ?>
                                        <form action="<?= $baseUrl ?>/admin/users/toggle/<?= \App\Helpers\HashId::encode($user['id']) ?>" method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                                            <button type="submit" class="btn btn-sm <?= $user['status'] === 'active' ? 'btn-danger' : 'btn-success' ?>" title="<?= $user['status'] === 'active' ? 'Desactivar' : 'Activar' ?>">
                                                <i class="fa-solid <?= $user['status'] === 'active' ? 'fa-ban' : 'fa-check' ?>"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <a href="<?= $baseUrl ?>/admin/users/edit/<?= \App\Helpers\HashId::encode($user['id']) ?>" class="btn btn-sm btn-secondary" title="Editar"><i class="fa-solid fa-pen"></i></a>
                                <?php endif; ?>
                                <?php if (\App\Helpers\Auth::hasPermission('users_delete') && $user['id'] != \App\Helpers\Session::userId()): ?>
                                    <form action="<?= $baseUrl ?>/admin/users/delete/<?= \App\Helpers\HashId::encode($user['id']) ?>" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar este usuario?');">
                                        <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= \App\Helpers\Pagination::render(
            $currentPage ?? 1,
            $totalPages ?? 1,
            $totalRecords ?? count($users),
            $baseUrl . '/admin/users'
        ) ?>
    </div>
</div>
