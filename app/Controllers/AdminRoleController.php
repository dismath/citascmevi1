<?php
namespace App\Controllers;

use App\Helpers\View;
use App\Helpers\Session;
use App\Helpers\Auth;
use App\Helpers\Database;
use App\Models\BaseModel;

class AdminRoleController
{
    private BaseModel $baseModel;
    public const PROTECTED_ROLES = ['admin', 'doctor', 'patient', 'staff', 'receptionist', 'nurse'];

    public function __construct()
    {
        Auth::require();
        if (!Auth::hasRole('admin') && !Auth::hasPermission('roles_read')) {
            http_response_code(403);
            echo '<h1>403 - Acceso Denegado</h1>';
            echo '<p>No dispone de los privilegios necesarios para acceder a la gestión de Roles y Permisos (Permiso requerido: roles_read).</p>';
            exit;
        }

        $this->baseModel = new class extends BaseModel {
            protected string $table = 'roles';
        };
    }

    /**
     * Definición de la jerarquía clínica de módulos, submódulos y acciones
     */
    private function getModuleDefinitions(): array
    {
        return [
            'dashboard' => [
                'title' => 'MÓDULO: Panel de Control y Métricas',
                'icon' => 'fa-solid fa-chart-line',
                'icon_color' => '#3b82f6',
                'submodules' => [
                    'dashboard' => [
                        'title' => 'Dashboard y Estadísticas',
                        'desc' => 'Visualización de métricas globales, citas de hoy, gráficos e indicadores clave.',
                        'actions' => ['read']
                    ]
                ]
            ],
            'citas' => [
                'title' => 'MÓDULO: Gestión de Citas y Turnos',
                'icon' => 'fa-solid fa-calendar-check',
                'icon_color' => '#2563eb',
                'submodules' => [
                    'appointments' => [
                        'title' => 'Citas Médicas',
                        'desc' => 'Agendamiento, confirmación, recepción en sala y seguimiento.',
                        'actions' => ['read', 'create', 'update', 'delete']
                    ],
                    'schedules' => [
                        'title' => 'Horarios Laborales y Turnos',
                        'desc' => 'Planificación de turnos médicos, jornadas laborales y bloqueos.',
                        'actions' => ['read', 'create', 'update', 'delete']
                    ]
                ]
            ],
            'pacientes' => [
                'title' => 'MÓDULO: Pacientes y Fichas Clínicas',
                'icon' => 'fa-solid fa-hospital-user',
                'icon_color' => '#059669',
                'submodules' => [
                    'patients' => [
                        'title' => 'Directorio de Pacientes',
                        'desc' => 'Registro, datos personales, expedientes y contacto.',
                        'actions' => ['read', 'create', 'update', 'delete']
                    ],
                    'medical_records' => [
                        'title' => 'Historias Clínicas y Evoluciones',
                        'desc' => 'Evoluciones clínicas, antecedentes, diagnósticos y recetas.',
                        'actions' => ['read', 'create', 'update', 'delete']
                    ]
                ]
            ],
            'medicos' => [
                'title' => 'MÓDULO: Cuerpo Médico y Especialidades',
                'icon' => 'fa-solid fa-user-doctor',
                'icon_color' => '#0284c7',
                'submodules' => [
                    'doctors' => [
                        'title' => 'Médicos y Especialistas',
                        'desc' => 'Directorio médico, perfiles profesionales y aranceles.',
                        'actions' => ['read', 'create', 'update', 'delete']
                    ],
                    'specialties' => [
                        'title' => 'Especialidades Médicas',
                        'desc' => 'Catálogo de especialidades clínicas y asignación médica.',
                        'actions' => ['read', 'create', 'update', 'delete']
                    ]
                ]
            ],
            'ordenes' => [
                'title' => 'MÓDULO: Órdenes y Servicios Clínicos',
                'icon' => 'fa-solid fa-flask-vial',
                'icon_color' => '#7c3aed',
                'submodules' => [
                    'orders' => [
                        'title' => 'Órdenes de Laboratorio e Imagen',
                        'desc' => 'Solicitudes de exámenes, resultados clínicos y comprobantes.',
                        'actions' => ['read', 'create', 'update', 'delete']
                    ],
                    'services' => [
                        'title' => 'Catálogo de Servicios y Tarifario',
                        'desc' => 'Servicios diagnósticos, pruebas de laboratorio y tarifas vigentes.',
                        'actions' => ['read', 'create', 'update', 'delete']
                    ]
                ]
            ],
            'personal' => [
                'title' => 'MÓDULO: Gestión de Personal y Colaboradores',
                'icon' => 'fa-solid fa-users-gear',
                'icon_color' => '#d97706',
                'submodules' => [
                    'staff' => [
                        'title' => 'Personal Administrativo y Asistencial',
                        'desc' => 'Directorio de colaboradores, recepcionistas, enfermeros y contratos.',
                        'actions' => ['read', 'create', 'update', 'delete']
                    ]
                ]
            ],
            'seguridad' => [
                'title' => 'MÓDULO: Administración del Sistema y Seguridad',
                'icon' => 'fa-solid fa-shield-halved',
                'icon_color' => '#dc2626',
                'submodules' => [
                    'users' => [
                        'title' => 'Usuarios del Sistema',
                        'desc' => 'Cuentas de acceso, credenciales y estados de cuenta.',
                        'actions' => ['read', 'create', 'update', 'delete']
                    ],
                    'roles' => [
                        'title' => 'Roles y Permisos',
                        'desc' => 'Control de accesos y matriz de privilegios del sistema.',
                        'actions' => ['read', 'create', 'update', 'delete']
                    ],
                    'settings_general' => [
                        'title' => 'Configuración: General y Pagos',
                        'desc' => 'Parámetros globales del sistema, agendamiento de citas y cuentas bancarias de cobro.',
                        'actions' => ['read', 'update']
                    ],
                    'settings_email' => [
                        'title' => 'Configuración: Servidor de Correos',
                        'desc' => 'Configuración de servidor SMTP, correo remitente y notificaciones del sistema.',
                        'actions' => ['read', 'update']
                    ],
                    'settings_security' => [
                        'title' => 'Configuración: Seguridad y 2FA',
                        'desc' => 'Políticas de doble factor de autenticación (2FA), alertas de acceso y validación de cédula.',
                        'actions' => ['read', 'update']
                    ],
                    'settings_catalogs' => [
                        'title' => 'Configuración: Tipos de Catálogos',
                        'desc' => 'Gestión de tipos de catálogos y clasificadores dinámicos del sistema.',
                        'actions' => ['read', 'create', 'update', 'delete']
                    ],
                    'settings_company' => [
                        'title' => 'Configuración: Datos de la Clínica',
                        'desc' => 'Información de la institución de salud, logotipo oficial, RUC, membrete y datos de contacto.',
                        'actions' => ['read', 'update']
                    ],
                    'backups' => [
                        'title' => 'Respaldos de Base de Datos',
                        'desc' => 'Generación de respaldos manuales, descarga de archivos .sql y políticas de backup.',
                        'actions' => ['read', 'create', 'delete']
                    ]
                ]
            ]
        ];
    }

    /**
     * Metadatos visuales y descriptivos por cada rol
     */
    private function getRoleMetadata(): array
    {
        return [
            'staff' => [
                'label' => 'Personal Administrativo (Staff)',
                'icon' => 'fa-solid fa-user-tie',
                'desc' => 'Supervisión y control administrativo, reportes, personal y soporte operativo de la clínica.',
                'order' => 1
            ],
            'doctor' => [
                'label' => 'Médicos',
                'icon' => 'fa-solid fa-user-doctor',
                'desc' => 'Atención de consultas médicas, emisión de recetas, historias clínicas y evolución de pacientes.',
                'order' => 2
            ],
            'receptionist' => [
                'label' => 'Recepcionista',
                'icon' => 'fa-solid fa-headset',
                'desc' => 'Recepción de pacientes, confirmación de citas, sala de espera y cobro de aranceles.',
                'order' => 3
            ],
            'nurse' => [
                'label' => 'Enfermera/o',
                'icon' => 'fa-solid fa-user-nurse',
                'desc' => 'Triaje de enfermería, toma de signos vitales, asistencia clínica y preparación de pacientes.',
                'order' => 4
            ],
            'patient' => [
                'label' => 'Pacientes',
                'icon' => 'fa-solid fa-hospital-user',
                'desc' => 'Portal de pacientes: reserva y reprogramación de citas médicas y consulta de antecedentes.',
                'order' => 5
            ],
            'admin' => [
                'label' => 'Administrador',
                'icon' => 'fa-solid fa-crown',
                'desc' => 'Acceso total y sin restricciones por arquitectura de seguridad del sistema.',
                'order' => 6
            ]
        ];
    }

    /**
     * Asegura de manera idempotente que todos los permisos existan en la tabla permissions
    /**
     * Asegura de forma idempotente que la tabla roles tenga columnas display_name, description, icon
     */
    private function ensureRoleColumnsExist(Database $db): void
    {
        try {
            $cols = array_column($db->fetchAll("DESCRIBE roles"), 'Field');
            if (!in_array('display_name', $cols)) {
                $db->execute("ALTER TABLE roles ADD COLUMN display_name VARCHAR(100) NULL AFTER name");
            }
            if (!in_array('description', $cols)) {
                $db->execute("ALTER TABLE roles ADD COLUMN description VARCHAR(255) NULL AFTER display_name");
            }
            if (!in_array('icon', $cols)) {
                $db->execute("ALTER TABLE roles ADD COLUMN icon VARCHAR(50) NULL AFTER description");
            }

            // Poblamos metadatos de roles base si están vacíos
            $meta = $this->getRoleMetadata();
            foreach ($meta as $roleKey => $m) {
                $db->execute("UPDATE roles SET display_name = COALESCE(NULLIF(display_name, ''), ?), description = COALESCE(NULLIF(description, ''), ?), icon = COALESCE(NULLIF(icon, ''), ?) WHERE name = ?", [
                    $m['label'],
                    $m['desc'],
                    $m['icon'],
                    $roleKey
                ]);
            }
        } catch (\Throwable $e) {
            error_log('[AdminRoleController] ensureRoleColumnsExist: ' . $e->getMessage());
        }
    }

    /**
     * Asegura de manera idempotente que todos los permisos existan en la tabla permissions
     */
    private function ensurePermissionsExist(Database $db, array $moduleDefs): void
    {
        foreach ($moduleDefs as $mod) {
            foreach ($mod['submodules'] as $subKey => $sub) {
                foreach ($sub['actions'] as $act) {
                    $name = "{$subKey}_{$act}";
                    try {
                        $db->execute("INSERT IGNORE INTO permissions (name) VALUES (?)", [$name]);
                    } catch (\Throwable $e) {
                        // ignore duplicate key or constraint
                    }
                }
            }
        }

        // Permisos de compatibilidad
        $legacyPerms = ['settings_read', 'settings_create', 'settings_update', 'settings_delete'];
        foreach ($legacyPerms as $lp) {
            try {
                $db->execute("INSERT IGNORE INTO permissions (name) VALUES (?)", [$lp]);
            } catch (\Throwable $e) {}
        }
    }

    public function index()
    {
        $db = Database::getInstance();
        $moduleDefinitions = $this->getModuleDefinitions();
        $roleMeta = $this->getRoleMetadata();

        // 1. Asegurar catálogo de roles y permisos
        $this->ensureRoleColumnsExist($db);
        $this->ensurePermissionsExist($db, $moduleDefinitions);

        // 2. Cargar todos los permisos existentes en mapa [nombre => id]
        $allPerms = $db->fetchAll("SELECT id, name FROM permissions");
        $permMap = [];
        foreach ($allPerms as $p) {
            $permMap[$p['name']] = (int)$p['id'];
        }

        // 3. Cargar todos los roles de la base de datos
        $rolesRaw = $db->fetchAll("SELECT * FROM roles ORDER BY id ASC");
        
        // Ordenar roles según prioridad establecida
        usort($rolesRaw, function($a, $b) use ($roleMeta) {
            $orderA = $roleMeta[$a['name']]['order'] ?? 99;
            $orderB = $roleMeta[$b['name']]['order'] ?? 99;
            if ($orderA === $orderB) {
                return strcmp($a['name'], $b['name']);
            }
            return $orderA <=> $orderB;
        });
        $roles = $rolesRaw;

        // 4. Mapear permisos de todos los roles para permitir cambio reactivo en el frontend
        $allRolePermsRaw = $db->fetchAll("SELECT role_id, permission_id FROM role_permissions");
        $rolePermsMap = [];
        foreach ($roles as $r) {
            $rolePermsMap[$r['id']] = [];
        }
        foreach ($allRolePermsRaw as $rp) {
            if (isset($rolePermsMap[$rp['role_id']])) {
                $rolePermsMap[$rp['role_id']][] = (int)$rp['permission_id'];
            }
        }

        // Construir estructura reactiva para frontend
        $rolesData = [];
        $allPermIdsList = array_values($permMap);
        foreach ($roles as $r) {
            $rName = $r['name'];
            $m = $roleMeta[$rName] ?? [];
            $label = !empty($r['display_name']) ? $r['display_name'] : ($m['label'] ?? ucfirst($rName));
            $icon = !empty($r['icon']) ? $r['icon'] : ($m['icon'] ?? 'fa-solid fa-user-gear');
            $desc = !empty($r['description']) ? $r['description'] : ($m['desc'] ?? 'Rol de usuario configurado.');
            $isAdminRole = ($rName === 'admin');
            
            $rolesData[$r['id']] = [
                'id' => (int)$r['id'],
                'name' => $rName,
                'label' => $label,
                'icon' => $icon,
                'desc' => $desc,
                'is_admin' => $isAdminRole,
                'permissions' => $isAdminRole ? $allPermIdsList : ($rolePermsMap[$r['id']] ?? [])
            ];
        }

        // 5. Determinar rol activo inicial
        $requestedRoleId = isset($_GET['role_id']) ? (int)$_GET['role_id'] : null;
        $activeRole = null;
        if ($requestedRoleId) {
            foreach ($roles as $r) {
                if ((int)$r['id'] === $requestedRoleId) {
                    $activeRole = $r;
                    break;
                }
            }
        }

        // Si no se encuentra o no se especificó, seleccionar por defecto 'staff' o el primero de la lista
        if (!$activeRole) {
            foreach ($roles as $r) {
                if ($r['name'] === 'staff') {
                    $activeRole = $r;
                    break;
                }
            }
            if (!$activeRole && !empty($roles)) {
                $activeRole = $roles[0];
            }
        }

        // 6. Cargar permisos asociados al rol activo
        $activePermIds = [];
        if ($activeRole) {
            $activePermIds = $rolesData[$activeRole['id']]['permissions'] ?? [];
        }

        View::render('admin.roles.index', [
            'title' => 'Asignación de Permisos por Rol',
            'roles' => $roles,
            'activeRole' => $activeRole,
            'roleMeta' => $roleMeta,
            'rolesData' => $rolesData,
            'moduleDefinitions' => $moduleDefinitions,
            'permMap' => $permMap,
            'activePermIds' => $activePermIds
        ], 'admin');
    }

    /**
     * Crear un nuevo rol en la base de datos (con soporte modal/AJAX)
     */
    public function store()
    {
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_POST['is_ajax']) && $_POST['is_ajax'] == '1')
               || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
                exit;
            }
            View::redirect('/admin/roles');
        }

        if (!Auth::hasRole('admin') && !Auth::hasPermission('roles_create')) {
            $msg = 'No dispone de permisos para registrar nuevos roles (Permiso requerido: roles_create).';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            Session::flash('error', $msg);
            View::redirect('/admin/roles');
        }

        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            $msg = 'Token de seguridad inválido. Por favor recargue la página.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            Session::flash('error', $msg);
            View::redirect('/admin/roles');
        }

        $rawName = trim($_POST['name'] ?? '');
        $displayName = trim($_POST['display_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? 'fa-solid fa-user-gear');

        // Formatear slug del nombre técnico
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9_]+/', '_', $rawName));
        $slug = trim($slug, '_');

        if (empty($slug) || strlen($slug) < 2) {
            $msg = 'El código identificador del rol debe tener al menos 2 caracteres válidos.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            Session::flash('error', $msg);
            View::redirect('/admin/roles');
        }

        if (empty($displayName)) {
            $displayName = ucfirst(str_replace('_', ' ', $slug));
        }

        if (empty($icon)) {
            $icon = 'fa-solid fa-user-gear';
        }

        $db = Database::getInstance();
        $this->ensureRoleColumnsExist($db);

        // Verificar si el rol ya existe
        $existing = $db->fetch("SELECT id FROM roles WHERE name = ?", [$slug]);
        if ($existing) {
            $msg = "El rol '{$slug}' ya existe en el sistema. Por favor elija otro nombre.";
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            Session::flash('error', $msg);
            View::redirect('/admin/roles');
        }

        try {
            $db->execute(
                "INSERT INTO roles (name, display_name, description, icon) VALUES (?, ?, ?, ?)",
                [$slug, $displayName, $description, $icon]
            );
            $newRoleId = (int)$db->lastInsertId();

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => "¡Rol '{$displayName}' creado exitosamente!",
                    'role' => [
                        'id' => $newRoleId,
                        'name' => $slug,
                        'label' => $displayName,
                        'icon' => $icon,
                        'desc' => $description ?: 'Rol de usuario configurado.',
                        'is_admin' => false,
                        'permissions' => []
                    ]
                ]);
                exit;
            }

            Session::flash('success', "¡Rol '{$displayName}' creado con éxito!");
            View::redirect('/admin/roles?role_id=' . $newRoleId);
        } catch (\Throwable $e) {
            error_log('[AdminRoleController] Error creando rol: ' . $e->getMessage());
            $msg = 'Ocurrió un error al registrar el nuevo rol en la base de datos.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            Session::flash('error', $msg);
            View::redirect('/admin/roles');
        }
    }

    /**
     * Endpoint AJAX para consultar permisos de un rol
     */
    public function getPermissionsAjax()
    {
        header('Content-Type: application/json');
        $roleId = isset($_GET['role_id']) ? (int)$_GET['role_id'] : 0;
        if ($roleId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de rol inválido.']);
            exit;
        }

        $db = Database::getInstance();
        $role = $db->fetch("SELECT * FROM roles WHERE id = ?", [$roleId]);
        if (!$role) {
            echo json_encode(['success' => false, 'message' => 'Rol no encontrado.']);
            exit;
        }

        $isAdmin = ($role['name'] === 'admin');
        if ($isAdmin) {
            $allPerms = $db->fetchAll("SELECT id FROM permissions");
            $permIds = array_map('intval', array_column($allPerms, 'id'));
        } else {
            $rolePermsRaw = $db->fetchAll("SELECT permission_id FROM role_permissions WHERE role_id = ?", [$roleId]);
            $permIds = array_map('intval', array_column($rolePermsRaw, 'permission_id'));
        }

        $meta = $this->getRoleMetadata();
        $m = $meta[$role['name']] ?? [];
        $label = !empty($role['display_name']) ? $role['display_name'] : ($m['label'] ?? ucfirst($role['name']));
        $icon = !empty($role['icon']) ? $role['icon'] : ($m['icon'] ?? 'fa-solid fa-user-gear');
        $desc = !empty($role['description']) ? $role['description'] : ($m['desc'] ?? 'Rol de usuario configurado.');

        echo json_encode([
            'success' => true,
            'role' => [
                'id' => (int)$role['id'],
                'name' => $role['name'],
                'label' => $label,
                'icon' => $icon,
                'desc' => $desc,
                'is_admin' => $isAdmin,
                'permissions' => $permIds
            ]
        ]);
        exit;
    }

    /**
     * Guardar permisos vía AJAX sin recargar la página
     */
    public function savePermissionsAjax()
    {
        header('Content-Type: application/json');
        if (!Auth::hasRole('admin') && !Auth::hasPermission('roles_update')) {
            echo json_encode(['success' => false, 'message' => 'No dispone de permisos para modificar roles (Permiso requerido: roles_update).']);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
            exit;
        }

        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido. Por favor recargue la página.']);
            exit;
        }

        $roleId = isset($_POST['role_id']) ? (int)$_POST['role_id'] : 0;
        if ($roleId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de rol inválido.']);
            exit;
        }

        $db = Database::getInstance();
        $role = $db->fetch("SELECT * FROM roles WHERE id = ?", [$roleId]);
        if (!$role) {
            echo json_encode(['success' => false, 'message' => 'Rol no encontrado en el sistema.']);
            exit;
        }

        if ($role['name'] === 'admin') {
            echo json_encode(['success' => false, 'message' => 'El rol Administrador mantiene acceso total permanente y no requiere modificación.']);
            exit;
        }

        $permissions = $_POST['permissions'] ?? [];
        $meta = $this->getRoleMetadata();
        $roleLabel = !empty($role['display_name']) ? $role['display_name'] : ($meta[$role['name']]['label'] ?? ucfirst($role['name']));

        $db->beginTransaction();
        try {
            $db->execute("DELETE FROM role_permissions WHERE role_id = ?", [$roleId]);

            $savedCount = 0;
            if (is_array($permissions) && !empty($permissions)) {
                foreach ($permissions as $permId) {
                    $pId = (int)$permId;
                    if ($pId > 0) {
                        $db->execute("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [$roleId, $pId]);
                        $savedCount++;
                    }
                }
            }

            $db->commit();
            echo json_encode([
                'success' => true,
                'message' => "Permisos actualizados correctamente para el rol {$roleLabel}.",
                'saved_count' => $savedCount,
                'role_id' => $roleId
            ]);
            exit;
        } catch (\Throwable $e) {
            $db->rollback();
            error_log('[AdminRoleController] savePermissionsAjax error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error al guardar los permisos en la base de datos.']);
            exit;
        }
    }

    public function edit(int $id)
    {
        View::redirect('/admin/roles?role_id=' . $id);
    }

    public function update(int $id)
    {
        if (!Auth::hasRole('admin') && !Auth::hasPermission('roles_update')) {
            Session::flash('error', 'No dispone de permisos para modificar roles (Permiso requerido: roles_update).');
            View::redirect('/admin/roles?role_id=' . $id);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            View::redirect('/admin/roles?role_id=' . $id);
        }

        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Token de seguridad inválido. Por favor intente de nuevo.');
            View::redirect('/admin/roles?role_id=' . $id);
        }

        $db = Database::getInstance();
        $role = $db->fetch("SELECT * FROM roles WHERE id = ?", [$id]);

        if (!$role) {
            Session::flash('error', 'Rol no encontrado en el sistema.');
            View::redirect('/admin/roles');
        }

        // El rol admin tiene acceso total por arquitectura y no puede ser alterado
        if ($role['name'] === 'admin') {
            Session::flash('info', 'El rol Administrador mantiene acceso total permanente y no requiere modificación.');
            View::redirect('/admin/roles?role_id=' . $id);
        }

        $permissions = $_POST['permissions'] ?? [];
        $roleMeta = $this->getRoleMetadata();
        $roleLabel = !empty($role['display_name']) ? $role['display_name'] : ($roleMeta[$role['name']]['label'] ?? ucfirst($role['name']));

        $db->beginTransaction();
        try {
            // Eliminar permisos anteriores
            $db->execute("DELETE FROM role_permissions WHERE role_id = ?", [$id]);

            // Asignar los nuevos permisos seleccionados
            if (is_array($permissions) && !empty($permissions)) {
                foreach ($permissions as $permId) {
                    $pId = (int)$permId;
                    if ($pId > 0) {
                        $db->execute("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [$id, $pId]);
                    }
                }
            }

            $db->commit();
            Session::flash('success', "Permisos actualizados correctamente para el rol {$roleLabel}.");
        } catch (\Throwable $e) {
            $db->rollback();
            error_log('[AdminRoleController] Error actualizando permisos: ' . $e->getMessage());
            Session::flash('error', 'Error al guardar los permisos en la base de datos.');
        }

        View::redirect('/admin/roles?role_id=' . $id);
    }

    /**
     * Actualiza la información y metadatos de un rol (Nombre visible, descripción, icono)
     */
    public function updateRoleInfo(int $id)
    {
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_POST['is_ajax']) && $_POST['is_ajax'] == '1')
               || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);

        if (!Auth::hasRole('admin') && !Auth::hasPermission('roles_update')) {
            $msg = 'No dispone de permisos para modificar roles (Permiso requerido: roles_update).';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            Session::flash('error', $msg);
            View::redirect('/admin/roles?role_id=' . $id);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
                exit;
            }
            View::redirect('/admin/roles?role_id=' . $id);
        }

        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            $msg = 'Token de seguridad inválido. Por favor recargue la página.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            Session::flash('error', $msg);
            View::redirect('/admin/roles?role_id=' . $id);
        }

        $db = Database::getInstance();
        $this->ensureRoleColumnsExist($db);
        $role = $db->fetch("SELECT * FROM roles WHERE id = ?", [$id]);

        if (!$role) {
            $msg = 'Rol no encontrado en el sistema.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            Session::flash('error', $msg);
            View::redirect('/admin/roles');
        }

        $displayName = trim($_POST['display_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? 'fa-solid fa-user-gear');

        if (empty($displayName)) {
            $displayName = ucfirst($role['name']);
        }
        if (empty($icon)) {
            $icon = 'fa-solid fa-user-gear';
        }

        try {
            $db->execute(
                "UPDATE roles SET display_name = ?, description = ?, icon = ? WHERE id = ?",
                [$displayName, $description, $icon, $id]
            );

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => "¡Información del rol '{$displayName}' actualizada correctamente!",
                    'role' => [
                        'id' => (int)$id,
                        'name' => $role['name'],
                        'label' => $displayName,
                        'icon' => $icon,
                        'desc' => $description ?: 'Rol de usuario configurado.',
                        'is_admin' => ($role['name'] === 'admin')
                    ]
                ]);
                exit;
            }

            Session::flash('success', "¡Información del rol '{$displayName}' actualizada correctamente!");
            View::redirect('/admin/roles?role_id=' . $id);
        } catch (\Throwable $e) {
            error_log('[AdminRoleController] updateRoleInfo error: ' . $e->getMessage());
            $msg = 'Ocurrió un error al actualizar los datos del rol.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            Session::flash('error', $msg);
            View::redirect('/admin/roles?role_id=' . $id);
        }
    }

    /**
     * Elimina un rol personalizado (no protegido y sin usuarios asociados)
     */
    public function deleteRole(int $id)
    {
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_POST['is_ajax']) && $_POST['is_ajax'] == '1')
               || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);

        if (!Auth::hasRole('admin') && !Auth::hasPermission('roles_delete')) {
            $msg = 'No dispone de permisos para eliminar roles (Permiso requerido: roles_delete).';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            Session::flash('error', $msg);
            View::redirect('/admin/roles');
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
                exit;
            }
            View::redirect('/admin/roles');
        }

        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            $msg = 'Token de seguridad inválido. Por favor recargue la página.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            Session::flash('error', $msg);
            View::redirect('/admin/roles');
        }

        $db = Database::getInstance();
        $role = $db->fetch("SELECT * FROM roles WHERE id = ?", [$id]);

        if (!$role) {
            $msg = 'Rol no encontrado en el sistema.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            Session::flash('error', $msg);
            View::redirect('/admin/roles');
        }

        // 1. Proteger roles predeterminados del sistema
        if (in_array($role['name'], self::PROTECTED_ROLES, true)) {
            $msg = "El rol '{$role['name']}' es un rol protegido fundamental del sistema clínico y no puede ser eliminado.";
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            Session::flash('error', $msg);
            View::redirect('/admin/roles?role_id=' . $id);
        }

        // 2. Verificar si tiene usuarios asignados
        $userCountRow = $db->fetch("SELECT COUNT(*) as cnt FROM user_roles WHERE role_id = ?", [$id]);
        $userCount = $userCountRow ? (int)$userCountRow['cnt'] : 0;
        if ($userCount > 0) {
            $msg = "No es posible eliminar este rol porque actualmente tiene {$userCount} usuario(s) asignado(s). Reasigne a los usuarios antes de eliminarlo.";
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            Session::flash('error', $msg);
            View::redirect('/admin/roles?role_id=' . $id);
        }

        $db->beginTransaction();
        try {
            $db->execute("DELETE FROM role_permissions WHERE role_id = ?", [$id]);
            $db->execute("DELETE FROM roles WHERE id = ?", [$id]);
            $db->commit();

            $roleLabel = !empty($role['display_name']) ? $role['display_name'] : ucfirst($role['name']);

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => "¡El rol '{$roleLabel}' ha sido eliminado exitosamente!",
                    'deleted_id' => $id
                ]);
                exit;
            }

            Session::flash('success', "¡El rol '{$roleLabel}' ha sido eliminado exitosamente!");
            View::redirect('/admin/roles');
        } catch (\Throwable $e) {
            $db->rollback();
            error_log('[AdminRoleController] deleteRole error: ' . $e->getMessage());
            $msg = 'Ocurrió un error al intentar eliminar el rol en la base de datos.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            }
            Session::flash('error', $msg);
            View::redirect('/admin/roles?role_id=' . $id);
        }
    }
}
