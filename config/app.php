<?php
/**
 * Application Configuration
 *
 * FIX-SEC-01: debug ahora se controla por variable de entorno APP_ENV.
 * Establecer APP_ENV=development en el servidor local para activar debug.
 *
 * FASE 4: cdn_url — Si se configura una URL de CDN (ej. CloudFront, Cloudflare),
 * todos los assets estáticos serán prefijados automáticamente vía View::asset().
 * Dejar vacío ('') para servir assets desde el propio servidor.
 */
return [
    'name'      => 'Portal Cmevi',
    'version'   => '1.0.0',
    'base_url'  => \App\Helpers\Env::get('APP_URL', ''),
    'timezone'  => 'America/Guayaquil',
    'locale'    => 'es',
    // FIX-SEC-01: debug=false en producción por defecto
    'debug'     => (\App\Helpers\Env::get('APP_ENV') === 'development'),
    // FASE 4: URL base del CDN para servir assets estáticos (JS, CSS, imágenes).
    // Ejemplo producción: 'https://cdn.portalcmevi.com'
    // Ejemplo CloudFront: 'https://d1234abc.cloudfront.net'
    // Dejar vacío ('') para servir desde el servidor local.
    'cdn_url'   => \App\Helpers\Env::get('CDN_URL', ''),
    // FIX-SEC: Subidas fuera del directorio público
    'upload_dir'=> __DIR__ . '/../storage/uploads/',
    'max_upload_size' => 5 * 1024 * 1024, // 5MB
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'pdf', 'gif', 'webp'],
    // FIX-SEC-03: Tipos MIME reales permitidos para validación server-side
    'allowed_mimes' => [
        'image/jpeg',
        'image/pjpeg',
        'image/jpg',
        'image/png',
        'image/x-png',
        'image/gif',
        'image/webp',
        'application/pdf',
        'application/x-pdf',
        'application/acrobat',
        'applications/vnd.pdf',
        'text/pdf',
    ],
];

