<?php

/**
 * Redirecciona todas las peticiones de la raíz al Front Controller real.
 * Esto es muy útil en servidores de hosting compartido (cPanel) donde
 * el .htaccess de la raíz a veces causa errores 500.
 */

// Cargamos el controlador principal de la carpeta public
require_once __DIR__ . '/public/index.php';
