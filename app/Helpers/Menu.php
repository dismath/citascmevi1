<?php
namespace App\Helpers;

class Menu
{
    /**
     * Obtiene la estructura del menú filtrada según el rol y los permisos del usuario activo
     */
    public static function getNavigation(string $baseUrl = ''): array
    {
        $role = Session::get('user_role') ?? '';

        if ($role === 'doctor') {
            return self::getDoctorNavigation($baseUrl);
        }

        if ($role === 'patient') {
            return self::getPatientNavigation($baseUrl);
        }

        return self::getAdminNavigation($baseUrl);
    }

    /**
     * Estructura de menú para Médico
     */
    private static function getDoctorNavigation(string $baseUrl): array
    {
        $docTodayCount = 0;
        try {
            $db = Database::getInstance();
            $email = Session::get('user_email');
            $doc = $db->fetch("SELECT d.id FROM doctors d JOIN users u ON d.user_id = u.id WHERE u.email = ?", [$email]);
            if ($doc) {
                $cnt = $db->fetch("SELECT COUNT(*) as cnt FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = CURDATE() AND status IN ('confirmed','in_progress')", [$doc['id']]);
                $docTodayCount = $cnt ? (int)$cnt['cnt'] : 0;
            }
        } catch (\Throwable $e) {
            error_log('[Menu::getDoctorNavigation] ' . $e->getMessage());
        }

        return [
            [
                'section' => null,
                'items' => [
                    [
                        'title' => 'Dashboard',
                        'url' => $baseUrl . '/doctor/dashboard',
                        'path' => '/doctor/dashboard',
                        'icon' => 'fa-solid fa-chart-line',
                    ]
                ]
            ],
            [
                'section' => 'Mi Consulta',
                'items' => [
                    [
                        'title' => 'Mis Citas',
                        'url' => $baseUrl . '/doctor/appointments',
                        'path' => '/doctor/appointments',
                        'icon' => 'fa-regular fa-calendar-check',
                        'badge' => $docTodayCount > 0 ? (string)$docTodayCount : null,
                        'badge_class' => 'sidebar-notif-badge'
                    ],
                    [
                        'title' => 'Mis Pacientes',
                        'url' => $baseUrl . '/doctor/patients/attended',
                        'path' => '/doctor/patients/attended',
                        'icon' => 'fa-solid fa-users',
                    ],
                    [
                        'title' => 'Mi Horario',
                        'url' => $baseUrl . '/doctor/schedule',
                        'path' => '/doctor/schedule',
                        'icon' => 'fa-regular fa-clock',
                    ]
                ]
            ],
            [
                'section' => 'Configuración',
                'items' => [
                    [
                        'title' => 'Mi Perfil',
                        'url' => $baseUrl . '/doctor/profile',
                        'path' => '/doctor/profile',
                        'icon' => 'fa-solid fa-user-doctor',
                    ]
                ]
            ]
        ];
    }

    /**
     * Estructura de menú para Paciente
     */
    private static function getPatientNavigation(string $baseUrl): array
    {
        return [
            [
                'section' => null,
                'items' => [
                    [
                        'title' => 'Dashboard',
                        'url' => $baseUrl . '/patient/dashboard',
                        'path' => '/patient/dashboard',
                        'icon' => 'fa-solid fa-chart-line',
                    ]
                ]
            ],
            [
                'section' => 'Mi Salud',
                'items' => [
                    [
                        'title' => 'Mi Historial',
                        'url' => $baseUrl . '/patient/records',
                        'path' => '/patient/records',
                        'icon' => 'fa-solid fa-notes-medical',
                    ],
                    [
                        'title' => 'Agendar Cita',
                        'url' => $baseUrl . '/',
                        'path' => '/booking',
                        'icon' => 'fa-solid fa-calendar-plus',
                    ]
                ]
            ],
            [
                'section' => 'Configuración',
                'items' => [
                    [
                        'title' => 'Mi Perfil',
                        'url' => $baseUrl . '/patient/profile',
                        'path' => '/patient/profile',
                        'icon' => 'fa-solid fa-user-gear',
                    ]
                ]
            ]
        ];
    }

    /**
     * Estructura de menú dinámica basada en permisos para perfiles administrativos y clínicos
     */
    private static function getAdminNavigation(string $baseUrl): array
    {
        $isAdmin = Auth::hasRole('admin');

        $sections = [];

        // 1. Dashboard general
        if ($isAdmin || Auth::hasPermission('dashboard_read')) {
            $sections[] = [
                'section' => null,
                'items' => [
                    [
                        'title' => 'Dashboard',
                        'url' => $baseUrl . '/admin/dashboard',
                        'path' => '/admin/dashboard',
                        'icon' => 'fa-solid fa-chart-line',
                    ]
                ]
            ];
        }

        // 2. Gestión Médica
        $medicalItems = [];
        if ($isAdmin || Auth::hasPermission('appointments_read')) {
            $medicalItems[] = [
                'title' => 'Citas',
                'url' => $baseUrl . '/admin/appointments',
                'path' => '/admin/appointments',
                'icon' => 'fa-regular fa-calendar-check',
            ];
        }
        if ($isAdmin || Auth::hasPermission('orders_read')) {
            $medicalItems[] = [
                'title' => 'Órdenes',
                'url' => $baseUrl . '/admin/orders',
                'path' => '/admin/orders',
                'icon' => 'fa-solid fa-file-invoice-dollar',
            ];
        }
        if ($isAdmin || Auth::hasPermission('patients_read')) {
            $medicalItems[] = [
                'title' => 'Pacientes',
                'url' => $baseUrl . '/admin/patients',
                'path' => '/admin/patients',
                'icon' => 'fa-solid fa-users',
            ];
        }
        if ($isAdmin || Auth::hasPermission('doctors_read')) {
            $medicalItems[] = [
                'title' => 'Médicos',
                'url' => $baseUrl . '/admin/doctors',
                'path' => '/admin/doctors',
                'icon' => 'fa-solid fa-user-doctor',
            ];
        }
        if ($isAdmin || Auth::hasPermission('schedules_read')) {
            $medicalItems[] = [
                'title' => 'Horarios',
                'url' => $baseUrl . '/admin/schedules',
                'path' => '/admin/schedules',
                'icon' => 'fa-regular fa-clock',
            ];
        }

        if (!empty($medicalItems)) {
            $sections[] = [
                'section' => 'Gestión Médica',
                'items' => $medicalItems
            ];
        }

        // 3. Catálogos
        $catalogItems = [];
        if ($isAdmin || Auth::hasPermission('specialties_read')) {
            $catalogItems[] = [
                'title' => 'Especialidades',
                'url' => $baseUrl . '/admin/specialties',
                'path' => '/admin/specialties',
                'icon' => 'fa-solid fa-stethoscope',
            ];
        }
        if ($isAdmin || Auth::hasPermission('services_read')) {
            $catalogItems[] = [
                'title' => 'Catálogo de Servicios',
                'url' => $baseUrl . '/admin/services',
                'path' => '/admin/services',
                'icon' => 'fa-solid fa-microscope',
            ];
        }

        if (!empty($catalogItems)) {
            $sections[] = [
                'section' => 'Catálogos',
                'items' => $catalogItems
            ];
        }

        // 4. Sistema y Seguridad
        $systemItems = [];
        if ($isAdmin || Auth::hasPermission('staff_read')) {
            $systemItems[] = [
                'title' => 'Colaboradores',
                'url' => $baseUrl . '/admin/staff',
                'path' => '/admin/staff',
                'icon' => 'fa-solid fa-id-badge',
            ];
        }
        if ($isAdmin || Auth::hasPermission('users_read')) {
            $systemItems[] = [
                'title' => 'Usuarios Sistema',
                'url' => $baseUrl . '/admin/users',
                'path' => '/admin/users',
                'icon' => 'fa-solid fa-user-shield',
            ];
        }
        if ($isAdmin || Auth::hasPermission('roles_read')) {
            $systemItems[] = [
                'title' => 'Roles & Permisos',
                'url' => $baseUrl . '/admin/roles',
                'path' => '/admin/roles',
                'icon' => 'fa-solid fa-key',
            ];
        }

        $canSettings = $isAdmin 
            || Auth::hasPermission('settings_read') 
            || Auth::hasPermission('settings_general_read') 
            || Auth::hasPermission('settings_email_read') 
            || Auth::hasPermission('settings_security_read') 
            || Auth::hasPermission('settings_catalogs_read') 
            || Auth::hasPermission('settings_company_read');

        if ($canSettings) {
            $systemItems[] = [
                'title' => 'Configuración',
                'url' => $baseUrl . '/admin/settings',
                'path' => '/admin/settings',
                'icon' => 'fa-solid fa-gear',
            ];
        }

        if ($isAdmin || Auth::hasPermission('backups_read')) {
            $systemItems[] = [
                'title' => 'Respaldos BD',
                'url' => $baseUrl . '/admin/backups',
                'path' => '/admin/backups',
                'icon' => 'fa-solid fa-database',
            ];
        }

        if (!empty($systemItems)) {
            $sections[] = [
                'section' => 'Sistema y Seguridad',
                'items' => $systemItems
            ];
        }

        // 5. Mi Cuenta (Siempre disponible para usuarios del portal)
        $sections[] = [
            'section' => 'Mi Cuenta',
            'items' => [
                [
                    'title' => 'Mi Perfil',
                    'url' => $baseUrl . '/admin/profile',
                    'path' => '/admin/profile',
                    'icon' => 'fa-solid fa-user-gear',
                ]
            ]
        ];

        return $sections;
    }

    /**
     * Renderiza el menú en formato HTML para la barra lateral (Sidebar)
     */
    public static function renderSidebar(string $baseUrl = ''): string
    {
        $sections = self::getNavigation($baseUrl);
        $html = '';

        foreach ($sections as $sec) {
            if (!empty($sec['section'])) {
                $html .= '<li class="sidebar-section">' . htmlspecialchars($sec['section']) . '</li>' . "\n";
            }

            foreach ($sec['items'] as $item) {
                $url = htmlspecialchars($item['url']);
                $path = htmlspecialchars($item['path']);
                $icon = htmlspecialchars($item['icon']);
                $title = htmlspecialchars($item['title']);

                $badgeHtml = '';
                $extraStyle = '';
                if (!empty($item['badge'])) {
                    $badgeClass = htmlspecialchars($item['badge_class'] ?? 'badge');
                    $badgeHtml = ' <span class="' . $badgeClass . '">' . htmlspecialchars($item['badge']) . '</span>';
                    $extraStyle = ' style="position: relative;"';
                }

                $html .= '<li><a href="' . $url . '" data-path="' . $path . '"' . $extraStyle . '><i class="' . $icon . '"></i> ' . $title . $badgeHtml . '</a></li>' . "\n";
            }
        }

        return $html;
    }

    /**
     * Obtiene la primera URL accesible para un usuario que no tiene acceso al Dashboard
     */
    public static function getFirstAccessibleUrl(string $baseUrl = ''): string
    {
        $role = Session::get('user_role') ?? '';
        if ($role === 'doctor') {
            return $baseUrl . '/doctor/dashboard';
        }
        if ($role === 'patient') {
            return $baseUrl . '/patient/dashboard';
        }

        $sections = self::getAdminNavigation($baseUrl);
        foreach ($sections as $sec) {
            foreach ($sec['items'] as $item) {
                // Si el item no es el dashboard y está accesible, retornarlo
                if ($item['path'] !== '/admin/dashboard') {
                    return $item['url'];
                }
            }
        }

        return $baseUrl . '/admin/profile';
    }
}
