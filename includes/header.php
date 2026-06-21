<?php
require_once __DIR__ . '/auth.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PNK Inmobiliaria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="img/logo_fondo.png">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index.php">
                <img src="img/logo_fondo.png" alt="PNK Inmobiliaria" height="60" style="border-radius: 6px;">
                PNK Inmobiliaria
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarMain">
                <div class="navbar-nav ms-auto align-items-center gap-1">
                    <a class="nav-link px-3 rounded menu-hover" href="index.php">Inicio</a>
                    
                    <?php if (esta_autenticado()): ?>
                        <a class="nav-link px-3 rounded menu-hover" href="dashboard.php">Mi Panel</a>
                        
                        <div class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle px-3 rounded menu-hover" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                <li class="px-3 py-1 text-muted small">Rol: <?php echo htmlspecialchars($_SESSION['usuario_tipo']); ?></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="login.php?action=logout"><i class="bi bi-box-arrow-right me-1"></i>Cerrar Sesión</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a class="nav-link px-3 rounded menu-hover" href="login.php">Acceder</a>
                        <div class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle px-3 rounded menu-hover" href="#" role="button" data-bs-toggle="dropdown">Registro</a>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                <li><a class="dropdown-item" href="registro_propietario.php">Propietario</a></li>
                                <li><a class="dropdown-item" href="registro_gestor.php">Gestor Freelance</a></li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
