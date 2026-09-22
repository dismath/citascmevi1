<div class="backups-module">
    <div class="row g-3 mb-4" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
        <div class="card p-3 shadow-sm border-0" style="background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: #fff;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div style="font-size: 0.85rem; opacity: 0.9; text-transform: uppercase; font-weight: 600;">Respaldo Automático</div>
                    <h3 class="fw-bold my-1 text-white" style="font-size: 1.4rem;">Activo (7:00 PM)</h3>
                    <small style="opacity: 0.85;"><i class="fa-solid fa-clock"></i> Diario a las 19:00</small>
                </div>
                <div style="font-size: 2.2rem; opacity: 0.85;">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
            </div>
        </div>
        <div class="card p-3 shadow-sm border-0" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div style="font-size: 0.85rem; opacity: 0.9; text-transform: uppercase; font-weight: 600;">Próxima Ejecución</div>
                    <h4 class="fw-bold my-1 text-white" style="font-size: 1.25rem;"><?= htmlspecialchars($stats['next_scheduled']) ?></h4>
                    <small style="opacity: 0.85;"><i class="fa-solid fa-bolt"></i> Tarea de Sistema Windows</small>
                </div>
                <div style="font-size: 2.2rem; opacity: 0.85;">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
            </div>
        </div>
        <div class="card p-3 shadow-sm border-0" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: #fff;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div style="font-size: 0.85rem; opacity: 0.9; text-transform: uppercase; font-weight: 600;">Total de Respaldos</div>
                    <h3 class="fw-bold my-1 text-white" style="font-size: 1.4rem;"><?= $stats['total_count'] ?> archivos</h3>
                    <small style="opacity: 0.85;"><i class="fa-solid fa-box-archive"></i> Máximo 30 conservados</small>
                </div>
                <div style="font-size: 2.2rem; opacity: 0.85;">
                    <i class="fa-solid fa-database"></i>
                </div>
            </div>
        </div>
        <div class="card p-3 shadow-sm border-0" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: #fff;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div style="font-size: 0.85rem; opacity: 0.9; text-transform: uppercase; font-weight: 600;">Espacio Ocupado</div>
                    <h3 class="fw-bold my-1 text-white" style="font-size: 1.4rem;"><?= $stats['total_size'] ?></h3>
                    <small style="opacity: 0.85;"><i class="fa-solid fa-file-zipper"></i> Compresión GZIP (-88%)</small>
                </div>
                <div style="font-size: 2.2rem; opacity: 0.85;">
                    <i class="fa-solid fa-hard-drive"></i>
                </div>
            </div>
        </div>
    </div>
    <?php if ($success = \App\Helpers\Session::getFlash('success')): ?>
        <div class="alert alert-success alert-dismissible mb-4 shadow-sm" style="border-left: 4px solid #16a34a; background: #f0fdf4; color: #166534; padding: 1rem 1.25rem;">
            <i class="fa-solid fa-circle-check me-2"></i> <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>
    <?php if ($error = \App\Helpers\Session::getFlash('error')): ?>
        <div class="alert alert-danger alert-dismissible mb-4 shadow-sm" style="border-left: 4px solid #dc2626; background: #fef2f2; color: #991b1b; padding: 1rem 1.25rem;">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2 py-3">
            <div>
                <h4 class="m-0 fw-bold text-dark"><i class="fa-solid fa-database text-primary me-2"></i> Historial de Respaldos de Base de Datos</h4>
                <small class="text-muted">Los respaldos se almacenan de forma protegida en el servidor y están cifrados contra accesos externos directos.</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <form action="<?= $baseUrl ?>/admin/backups/create" method="POST" id="formCreateBackup" class="m-0">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <button type="submit" class="btn btn-primary fw-bold shadow-sm d-inline-flex align-items-center gap-2" id="btnCreateBackup">
                        <i class="fa-solid fa-plus-circle"></i> Crear Respaldo Ahora
                    </button>
                </form>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-wrapper" style="overflow-x: auto;">
                <table class="table table-hover align-middle mb-0" style="width: 100%;">
                    <thead class="table-light">
                        <tr>
                            <th style="padding: 0.9rem 1.25rem;">Nombre de Archivo</th>
                            <th style="padding: 0.9rem 1rem;">Fecha y Hora</th>
                            <th style="padding: 0.9rem 1rem;">Tipo</th>
                            <th style="padding: 0.9rem 1rem;">Tamaño</th>
                            <th style="padding: 0.9rem 1rem;">Compresión</th>
                            <th style="padding: 0.9rem 1.25rem; text-align: right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($backups)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <div style="font-size: 3rem; color: #94a3b8;" class="mb-2">
                                    <i class="fa-solid fa-box-open"></i>
                                </div>
                                <h5 class="fw-bold text-dark">No hay archivos de respaldo aún</h5>
                                <p class="text-muted mb-3" style="font-size: 0.9rem;">
                                    El sistema generará el primer respaldo automático hoy a las 7:00 PM (19:00), o puede generar uno inmediatamente con el botón de arriba.
                                </p>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($backups as $b): ?>
                        <tr>
                            <td style="padding: 0.9rem 1.25rem;">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="text-primary fs-5"><i class="fa-solid fa-file-zipper"></i></span>
                                    <div>
                                        <strong class="text-dark" style="font-family: monospace; font-size: 0.92rem;"><?= htmlspecialchars($b['filename']) ?></strong>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 0.9rem 1rem;">
                                <div class="d-flex align-items-center gap-1 text-muted" style="font-size: 0.9rem;">
                                    <i class="fa-regular fa-clock text-primary"></i>
                                    <span><?= htmlspecialchars($b['date']) ?></span>
                                </div>
                            </td>
                            <td style="padding: 0.9rem 1rem;">
                                <?php if (str_contains($b['type'], '7:00 PM')): ?>
                                    <span class="badge" style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-size: 0.82rem; padding: 0.35rem 0.6rem;">
                                        <i class="fa-solid fa-robot me-1"></i> Programado (7:00 PM)
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="background: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff; font-size: 0.82rem; padding: 0.35rem 0.6rem;">
                                        <i class="fa-solid fa-user-shield me-1"></i> Manual Admin
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 0.9rem 1rem;">
                                <span class="fw-bold text-dark" style="font-size: 0.9rem;"><?= $b['size_formatted'] ?></span>
                            </td>
                            <td style="padding: 0.9rem 1rem;">
                                <span class="badge bg-success" style="font-size: 0.8rem;">
                                    <i class="fa-solid fa-check"></i> GZIP Ligero
                                </span>
                            </td>
                            <td style="padding: 0.9rem 1.25rem; text-align: right;">
                                <div class="d-flex justify-content-end align-items-center gap-2">
                                    <a href="<?= $baseUrl ?>/admin/backups/download/<?= urlencode($b['filename']) ?>" 
                                       class="btn btn-sm btn-outline-primary fw-bold d-inline-flex align-items-center gap-1"
                                       title="Descargar respaldo comprimido (.sql.gz)">
                                        <i class="fa-solid fa-download"></i> Descargar .gz
                                    </a>
                                    <a href="<?= $baseUrl ?>/admin/backups/download/<?= urlencode($b['filename']) ?>?format=sql" 
                                       class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1"
                                       title="Descargar en formato SQL plano (.sql)">
                                        <i class="fa-solid fa-file-code"></i> .sql
                                    </a>
                                    <form action="<?= $baseUrl ?>/admin/backups/delete/<?= urlencode($b['filename']) ?>" 
                                          method="POST" 
                                          class="m-0"
                                          onsubmit="return confirm('¿Está seguro de que desea eliminar permanentemente este archivo de respaldo?\n\nArchivo: <?= htmlspecialchars($b['filename']) ?>');">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar respaldo">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-light py-3">
            <div class="row align-items-center text-muted" style="font-size: 0.85rem;">
               
                <div class="col-md-6 text-md-end mt-2 mt-md-0">
                    <i class="fa-solid fa-info-circle text-info me-1"></i>
                    Los respaldos se conservan hasta 30 días para evitar sobrecargar el disco duro del servidor.
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const formCreate = document.getElementById('formCreateBackup');
    const btnCreate  = document.getElementById('btnCreateBackup');
    if (formCreate && btnCreate) {
        formCreate.addEventListener('submit', function() {
            btnCreate.disabled = true;
            btnCreate.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generando respaldo...';
        });
    }
});
</script>
