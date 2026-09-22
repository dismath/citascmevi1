<!DOCTYPE html>
<html lang="<?= htmlspecialchars($appConfig['locale'] ?? 'es') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($title) ? htmlspecialchars($title) . ' - ' : '' ?><?= htmlspecialchars($companyName ?? $appConfig['name'] ?? 'Portal Salud') ?></title>
    <link rel="stylesheet" href="<?= $baseUrl ?>/css/stylem.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha384-iw3OoTErCYJJB9mCa8LNS2hbsQ7M3C0EpIsO/H5+EGAkPGc6rk+V8i04oW/K5xq0" crossorigin="anonymous">
</head>
<body>
    <div class="page-wrapper">
        <nav class="navbar">
            <div class="navbar-inner">
                <a href="<?= $baseUrl ?>/" class="navbar-brand" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                    <?php if (!empty($companyLogo)): ?>
                        <img src="<?= $baseUrl ?>/<?= htmlspecialchars($companyLogo) ?>" alt="Logo" style="max-height: 32px; max-width: 44px; object-fit: contain; border-radius: 4px;">
                    <?php else: ?>
                        <i class="fa-solid fa-notes-medical"></i>
                    <?php endif; ?>
                    <span><?= htmlspecialchars($companyName ?? $appConfig['name'] ?? 'Portal Salud') ?></span>
                </a>
                <ul class="navbar-nav">
                    <li><a href="<?= $baseUrl ?>/">Agendar Cita</a></li>
                    <?php if (\App\Helpers\Session::isLoggedIn()): ?>
                        <li><a href="<?= $baseUrl ?>/admin/dashboard">Panel Admin</a></li>
                        <li><a href="<?= $baseUrl ?>/logout" class="btn btn-sm btn-secondary">Salir</a></li>
                    <?php else: ?>
                        <li><a href="<?= $baseUrl ?>/login">Iniciar Sesión</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </nav>
        <main class="main-content">
            <?php
            $success = \App\Helpers\Session::getFlash('success');
            $error = \App\Helpers\Session::getFlash('error');
            $info = \App\Helpers\Session::getFlash('info');
            if ($success): ?>
                <div class="container">
                    <div class="alert alert-success">
                        <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="container">
                    <div class="alert alert-error">
                        <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($info): ?>
                <div class="container">
                    <div class="alert alert-info">
                        <i class="fa-solid fa-circle-info"></i> <?= htmlspecialchars($info) ?>
                    </div>
                </div>
            <?php endif; ?>
            <?= $content ?>
        </main>
        <footer class="footer">
            <div class="container">
                <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($companyName ?? $appConfig['name'] ?? 'Portal Salud') ?>. Todos los derechos reservados.</p>
            </div>
        </footer>
    </div>
    <script>const BASE_URL = '<?= $baseUrl ?>';</script>
    <script src="<?= $baseUrl ?>/js/wizard.js"></script>
    <script src="<?= $baseUrl ?>/js/form-validation.js"></script>
</body>
</html>
