<?php
/**
 * Database Configuration
 * Designed for MySQL with PostgreSQL migration compatibility
 *
 * FIX-SEC-03: Las credenciales se leen desde variables de entorno.
 * Para desarrollo local, el archivo .env.local puede definirlas o se
 * usan los fallback indicados.
 *
 * CREAR USUARIO DEDICADO en MySQL antes de usar en producción:
 *   CREATE USER 'portal_salud_user'@'127.0.0.1' IDENTIFIED BY 'TuContraseñaFuerte';
 *   GRANT SELECT, INSERT, UPDATE, DELETE ON portal_salud.* TO 'portal_salud_user'@'127.0.0.1';
 *   FLUSH PRIVILEGES;
 */
return [
    'driver'   => 'mysql',
    'host'     => \App\Helpers\Env::get('DB_HOST', '127.0.0.1'),
    'port'     => (int)\App\Helpers\Env::get('DB_PORT', 3306),
    'database' => \App\Helpers\Env::get('DB_DATABASE', 'portal_salud'),
    // FIX-SEC-03: Nunca usar root en producción. Usar usuario con mínimos privilegios.
    'username' => \App\Helpers\Env::get('DB_USERNAME', 'portal_salud_app'),
    'password' => \App\Helpers\Env::get('DB_PASSWORD', ''),
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'options'   => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => true, // Pool de conexiones para alta concurrencia
    ],
];
