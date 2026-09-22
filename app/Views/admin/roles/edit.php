<?php
// Redirección directa al módulo unificado de roles y permisos
header('Location: ' . $baseUrl . '/admin/roles?role_id=' . ($role['id'] ?? 2));
exit;
