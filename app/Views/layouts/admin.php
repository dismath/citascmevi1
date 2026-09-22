<!DOCTYPE html>
<html lang="<?= htmlspecialchars($appConfig['locale'] ?? 'es') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= isset($title) ? htmlspecialchars($title) . ' - ' : '' ?><?= htmlspecialchars($companyName ?? $appConfig['name'] ?? 'Portal Salud') ?>
    </title>
    <link rel="stylesheet" href="<?= $baseUrl ?>/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha384-iw3OoTErCYJJB9mCa8LNS2hbsQ7M3C0EpIsO/H5+EGAkPGc6rk+V8i04oW/K5xq0" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" integrity="sha384-OXVF05DQEe311p6ohU11NwlnX08FzMCsyoXzGOaL+83dKAb3qS17yZJxESl8YrJQ" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" integrity="sha384-w9ufcIOKS67vY4KePhJtmWDp4+Ai5DMaHvqqF85VvjaGYSW2AhIbqorgKYqIJopv" crossorigin="anonymous">
</head>
<body class="bg-surface-2">
    <div class="admin-layout">
        <aside class="sidebar" id="main-sidebar">
            <div class="sidebar-brand">
                <h2>
                    <?php if (!empty($companyLogo)): ?>
                        <img src="<?= $baseUrl ?>/<?= htmlspecialchars($companyLogo) ?>" alt="Logo" style="max-height: 32px; max-width: 44px; object-fit: contain; border-radius: 4px; vertical-align: middle;">
                    <?php else: ?>
                        <i class="fa-solid fa-notes-medical"></i>
                    <?php endif; ?>
                    <span><?= htmlspecialchars($companyName ?? $appConfig['name'] ?? 'Portal Salud') ?></span>
                </h2>
            </div>
            <?php
            $currentRole = \App\Helpers\Session::get('user_role') ?? '';
            $roleLabels = [
                'admin' => 'Administrador',
                'receptionist' => 'Recepcionista',
                'doctor' => 'Médico',
                'patient' => 'Paciente',
                'staff' => 'Personal Administrativo',
                'nurse' => 'Enfermera/o'
            ];
            $roleIcons = [
                'admin' => 'fa-solid fa-shield-halved',
                'receptionist' => 'fa-solid fa-headset',
                'doctor' => 'fa-solid fa-user-doctor',
                'patient' => 'fa-solid fa-user',
                'staff' => 'fa-solid fa-user-tie',
                'nurse' => 'fa-solid fa-user-nurse'
            ];
            ?>
            <div class="sidebar-role-indicator">
                <div class="role-icon-wrapper">
                    <i class="<?= $roleIcons[$currentRole] ?? 'fa-solid fa-user' ?>"></i>
                </div>
                <div class="role-info">
                    <span class="role-name"><?= htmlspecialchars(\App\Helpers\Session::get('user_name') ?? \App\Helpers\Session::get('user_email') ?? 'Usuario') ?></span>
                    <span class="role-badge"><?= $roleLabels[$currentRole] ?? ucfirst($currentRole ?: 'Usuario') ?></span>
                </div>
            </div>
            <ul class="sidebar-nav">
                <?= \App\Helpers\Menu::renderSidebar($baseUrl) ?>
            </ul>
        </aside>
        <main class="admin-main">
            <header class="admin-topbar">
                <div class="d-flex align-center gap-2">
                    <button class="btn btn-icon btn-secondary" id="sidebar-toggle"><i
                            class="fa-solid fa-bars"></i></button>
                    <h2><?= isset($title) ? htmlspecialchars($title) : 'Dashboard' ?></h2>
                </div>
                <div class="d-flex align-center gap-2">
                    <?php 
                    $__docTodayCount = 0;
                    if ($currentRole === 'doctor') {
                        try {
                            $__docDb = \App\Helpers\Database::getInstance();
                            $__docEmail = \App\Helpers\Session::get('user_email');
                            $__docRow = $__docDb->fetch("SELECT d.id FROM doctors d JOIN users u ON d.user_id = u.id WHERE u.email = ?", [$__docEmail]);
                            if ($__docRow) {
                                $__countRow = $__docDb->fetch("SELECT COUNT(*) as cnt FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = CURDATE() AND status IN ('confirmed','in_progress')", [$__docRow['id']]);
                                $__docTodayCount = $__countRow ? (int)$__countRow['cnt'] : 0;
                            }
                        } catch (\Throwable $e) {}
                    }
                    ?>
                    <?php if ($currentRole === 'doctor' && $__docTodayCount > 0): ?>
                    <div class="topbar-notif-wrapper" style="position: relative;">
                        <a href="<?= $baseUrl ?>/doctor/appointments" class="btn btn-icon btn-secondary topbar-bell" title="Citas pendientes hoy" style="position: relative; font-size: 1.1rem;">
                            <i class="fa-solid fa-bell" style="animation: bellRingTopbar 2s ease infinite;"></i>
                            <span class="topbar-notif-count"><?= $__docTodayCount ?></span>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php
                    $profileUrl = match($currentRole) {
                        'doctor'  => $baseUrl . '/doctor/profile',
                        'patient' => $baseUrl . '/patient/profile',
                        default   => $baseUrl . '/admin/profile',
                    };
                    ?>
                    <a href="<?= $profileUrl ?>" class="topbar-user-info" style="text-decoration: none; color: inherit;" title="Mi Perfil">
                        <span class="topbar-user-name"><i class="fa-regular fa-user"></i>
                            <?= htmlspecialchars(\App\Helpers\Session::get('user_name') ?? \App\Helpers\Session::get('user_email') ?? 'Usuario') ?></span>
                        <span class="topbar-role-badge topbar-role-<?= htmlspecialchars($currentRole) ?>"><?= $roleLabels[$currentRole] ?? ucfirst($currentRole ?: 'Usuario') ?></span>
                    </a>
                    <a href="<?= $baseUrl ?>/logout" class="btn btn-sm btn-danger"><i
                            class="fa-solid fa-arrow-right-from-bracket"></i> Salir</a>
                </div>
            </header>
            <div class="admin-content">
                <?php
                $success = \App\Helpers\Session::getFlash('success');
                $error = \App\Helpers\Session::getFlash('error');
                if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                <?= $content ?>
            </div>
        </main>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js" integrity="sha384-NXgwF8Kv9SSAr+jemKKcbvQsz+teULH/a5UNJvZc6kP47hZgl62M1vGnw6gHQhb1" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js" integrity="sha384-d3UHjPdzJkZuk5H3qKYMLRyWLAQBJbby2yr2Q58hXXtAGF8RSNO9jpLDlKKPv5v3" crossorigin="anonymous"></script>
    <script>const BASE_URL = '<?= $baseUrl ?>';</script>
    <script>
        // Sidebar toggle
        document.getElementById('sidebar-toggle').addEventListener('click', function () {
            document.getElementById('main-sidebar').classList.toggle('open');
        });
        // Active link highlighting
        (function() {
            const currentPath = window.location.pathname;
            const basePath = BASE_URL.replace(/^https?:\/\/[^\/]+/, '');
            const relativePath = currentPath.replace(basePath, '');
            document.querySelectorAll('.sidebar-nav a[data-path]').forEach(function(link) {
                const linkPath = link.getAttribute('data-path');
                if (relativePath === linkPath || relativePath.startsWith(linkPath + '/')) {
                    link.classList.add('active');
                }
            });
        })();
        // Close sidebar on mobile when clicking outside
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('main-sidebar');
            const toggle = document.getElementById('sidebar-toggle');
            if (window.innerWidth <= 768 && sidebar.classList.contains('open')) {
                if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
                    sidebar.classList.remove('open');
                }
            }
        });
        // Select2 se inicializa desde las vistas individuales después del AJAX
    </script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js" integrity="sha384-k5vbMeKHbxEZ0AEBTSdR7UjAgWCcUfrS8c0c5b2AfIh7olfhNkyCZYwOfzOQhauK" crossorigin="anonymous"></script>
    <script>
        $(document).ready(function() {
            if ($('.datatable').length > 0) {
                var dtLang = {
                    "sDecimal": ",",
                    "sEmptyTable": "No hay datos disponibles en la tabla",
                    "sInfo": "Mostrando _START_ a _END_ de _TOTAL_ registros",
                    "sInfoEmpty": "Mostrando 0 a 0 de 0 registros",
                    "sInfoFiltered": "(filtrado de _MAX_ registros totales)",
                    "sLengthMenu": "Mostrar _MENU_ registros",
                    "sLoadingRecords": "Cargando...",
                    "sProcessing": "Procesando...",
                    "sSearch": "Buscar:",
                    "sZeroRecords": "No se encontraron resultados",
                    "oPaginate": {
                        "sFirst": "Primero",
                        "sLast": "\u00DAltimo",
                        "sNext": "Siguiente",
                        "sPrevious": "Anterior"
                    }
                };
                $('.datatable').DataTable({
                    "language": dtLang,
                    "lengthMenu": [[10, 50, 100, -1], [10, 50, 100, "Todos"]],
                    "pageLength": 10,
                    "autoWidth": false,
                    "order": [[0, "desc"]]
                });
            }
        });
    </script>
    <script src="<?= $baseUrl ?>/js/form-validation.js"></script>
</body>
</html>