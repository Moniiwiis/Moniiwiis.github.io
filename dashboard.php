<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/utils.php';

// Asegurar que el usuario esté logueado
verificar_autenticado();

$msg = $_GET['msg'] ?? '';
$user_error = '';
$my_user_id = $_SESSION['usuario_id'];

// Obtener datos del usuario actual
$stmt_me = $pdo->prepare("SELECT * FROM usuarios WHERE id = :id");
$stmt_me->execute(['id' => $my_user_id]);
$me = $stmt_me->fetch();

if (!$me) {
    header("Location: login.php?action=logout");
    exit;
}

// ── PROCESAR ACCIONES DEL DASHBOARD ────────────────────────────────

// Rechazar Usuario (Solo Admin - POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject_user') {
    if (!es_admin()) {
        header("Location: dashboard.php?msg=acceso_denegado");
        exit;
    }
    $id = (int)$_POST['id'];
    $motivo = trim($_POST['motivo'] ?? '');
    $stmt = $pdo->prepare("UPDATE usuarios SET estado = 'Rechazado', motivo_rechazo = :motivo WHERE id = :id");
    $stmt->execute(['motivo' => $motivo, 'id' => $id]);
    header("Location: dashboard.php?msg=usuario_rechazado");
    exit;
}

// Rechazar Propiedad (Solo Admin - POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject_prop') {
    if (!es_admin()) {
        header("Location: dashboard.php?msg=acceso_denegado");
        exit;
    }
    $id = (int)$_POST['id'];
    $motivo = trim($_POST['motivo'] ?? '');
    $stmt = $pdo->prepare("UPDATE propiedades SET estado = 'Rechazada', motivo_rechazo = :motivo WHERE id = :id");
    $stmt->execute(['motivo' => $motivo, 'id' => $id]);
    header("Location: dashboard.php?msg=propiedad_rechazada");
    exit;
}

// Editar Perfil / Re-postular (Propietario o Gestor - POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $nombre = trim($_POST['nombre'] ?? '');
    $rut = trim($_POST['rut'] ?? '');
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
    $correo = trim($_POST['correo'] ?? '');
    $sexo = $_POST['sexo'] ?? '';
    $telefono = trim($_POST['telefono'] ?? '');
    $nro_registro_bbr = trim($_POST['nro_registro_bbr'] ?? '');

    $tipo = $me['tipo'];
    $estado_actual = $me['estado'];
    $cert_antecedentes = $me['certificado_antecedentes'];

    // Validar campos obligatorios
    if (empty($rut) || empty($nombre) || empty($fecha_nacimiento) || empty($correo) || empty($sexo) || empty($telefono)) {
        $user_error = 'Por favor, complete todos los campos obligatorios (*)';
    } elseif (!validarRut($rut)) {
        $user_error = 'El RUT ingresado no es válido.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $user_error = 'El correo electrónico ingresado no tiene un formato válido.';
    } elseif ($tipo === 'Propietario' && empty($nro_registro_bbr)) {
        $user_error = 'Debe ingresar el N° de Registro de Bienes Raíces.';
    } else {
        // Verificar RUT o Correo duplicados en otros usuarios
        $rut_formateado = formatearRut($rut);
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE (rut = :rut OR correo = :correo) AND id != :id");
        $stmt->execute(['rut' => $rut_formateado, 'correo' => $correo, 'id' => $my_user_id]);
        if ($stmt->fetch()) {
            $user_error = 'El RUT o el Correo ya están asignados a otro usuario.';
        } else {
            // Procesar subida de archivo (solo si es Gestor Freelance y sube uno nuevo)
            if ($tipo === 'Gestor Freelance' && isset($_FILES['certificado']) && $_FILES['certificado']['error'] === UPLOAD_ERR_OK) {
                // Eliminar archivo anterior si existe
                if (!empty($cert_antecedentes)) {
                    @unlink(__DIR__ . '/uploads/certificados/' . $cert_antecedentes);
                }
                $orig_name = basename($_FILES['certificado']['name']);
                $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
                if (in_array($ext, ['pdf', 'doc', 'docx', 'jpg', 'png', 'jpeg'])) {
                    $new_name = uniqid('cert_' . $my_user_id . '_', true) . '.' . $ext;
                    move_uploaded_file($_FILES['certificado']['tmp_name'], __DIR__ . '/uploads/certificados/' . $new_name);
                    $cert_antecedentes = $new_name;
                }
            }

            // Si estaba Rechazado, al editar se vuelve a enviar a Pendiente y se limpia el motivo de rechazo
            $nuevo_estado = ($estado_actual === 'Rechazado') ? 'Pendiente' : $estado_actual;
            $nuevo_motivo = ($estado_actual === 'Rechazado') ? null : $me['motivo_rechazo'];

            $stmt = $pdo->prepare("UPDATE usuarios SET 
                                    rut = :rut,
                                    nombre = :nombre,
                                    fecha_nacimiento = :fecha_nacimiento,
                                    correo = :correo,
                                    sexo = :sexo,
                                    telefono = :telefono,
                                    nro_registro_bbr = :nro_registro_bbr,
                                    certificado_antecedentes = :cert,
                                    estado = :estado,
                                    motivo_rechazo = :motivo
                                   WHERE id = :id");
            $stmt->execute([
                'rut' => $rut_formateado,
                'nombre' => $nombre,
                'fecha_nacimiento' => $fecha_nacimiento,
                'correo' => $correo,
                'sexo' => $sexo,
                'telefono' => $telefono,
                'nro_registro_bbr' => ($tipo === 'Propietario') ? $nro_registro_bbr : null,
                'cert' => $cert_antecedentes,
                'estado' => $nuevo_estado,
                'motivo' => $nuevo_motivo,
                'id' => $my_user_id
            ]);

            // Actualizar variables de sesión si cambiaron
            $_SESSION['usuario_nombre'] = $nombre;
            $_SESSION['usuario_correo'] = $correo;
            $_SESSION['usuario_rut'] = $rut_formateado;

            header("Location: dashboard.php?msg=perfil_actualizado");
            exit;
        }
    }
}

// 1. Aprobar Usuario (Solo Admin)
if (isset($_GET['action']) && $_GET['action'] === 'approve_user' && isset($_GET['id'])) {
    if (!es_admin()) {
        header("Location: dashboard.php?msg=acceso_denegado");
        exit;
    }
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("UPDATE usuarios SET estado = 'Activo' WHERE id = :id");
    $stmt->execute(['id' => $id]);
    header("Location: dashboard.php?msg=usuario_aprobado");
    exit;
}

// 2. Eliminar Usuario (Solo Admin)
if (isset($_GET['action']) && $_GET['action'] === 'delete_user' && isset($_GET['id'])) {
    if (!es_admin()) {
        header("Location: dashboard.php?msg=acceso_denegado");
        exit;
    }
    $id = (int)$_GET['id'];
    
    // Obtener archivo adjunto para borrarlo
    $stmt = $pdo->prepare("SELECT certificado_antecedentes FROM usuarios WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $usr_file = $stmt->fetch();
    
    if ($usr_file && !empty($usr_file['certificado_antecedentes'])) {
        $file_path = __DIR__ . '/uploads/certificados/' . $usr_file['certificado_antecedentes'];
        if (file_exists($file_path)) {
            @unlink($file_path);
        }
    }
    
    // Eliminar de base de datos
    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = :id");
    $stmt->execute(['id' => $id]);
    header("Location: dashboard.php?msg=usuario_eliminado");
    exit;
}

// 3. Crear Usuario desde el Dashboard (Solo Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'add_user') {
    if (!es_admin()) {
        header("Location: dashboard.php?msg=acceso_denegado");
        exit;
    }
    $nombre = trim($_POST['nombre'] ?? '');
    $rut = trim($_POST['rut'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $tipo = $_POST['tipo'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($nombre) || empty($rut) || empty($correo) || empty($tipo) || empty($password)) {
        $user_error = 'Por favor, complete todos los campos para agregar al usuario.';
    } elseif (!validarRut($rut)) {
        $user_error = 'El RUT ingresado no es válido.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $user_error = 'El correo electrónico ingresado no tiene un formato válido.';
    } elseif (strlen($nombre) > 100) {
        $user_error = 'El nombre completo no puede superar los 100 caracteres.';
    } elseif (strlen($correo) > 100) {
        $user_error = 'El correo electrónico no puede superar los 100 caracteres.';
    } elseif (!validarPasswordRobusta($password)) {
        $user_error = 'La contraseña debe tener mínimo 8 caracteres, e incluir al menos una mayúscula, una minúscula, un número y un carácter especial.';
    } else {
        $rut = formatearRut($rut);
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE rut = :rut OR correo = :correo");
        $stmt->execute(['rut' => $rut, 'correo' => $correo]);
        if ($stmt->fetch()) {
            $user_error = 'El RUT o el Correo ya están registrados.';
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (rut, nombre, fecha_nacimiento, correo, password, sexo, telefono, tipo, estado) 
                                   VALUES (:rut, :nombre, CURDATE(), :correo, :password, 'Prefiero no indicar', '+56 9 1234 5678', :tipo, 'Activo')");
            $stmt->execute([
                'rut' => $rut,
                'nombre' => $nombre,
                'correo' => $correo,
                'password' => $hashed,
                'tipo' => $tipo
            ]);
            header("Location: dashboard.php?msg=usuario_creado");
            exit;
        }
    }
}

// 4. Aprobar Propiedad (Solo Admin)
if (isset($_GET['action']) && $_GET['action'] === 'approve_prop' && isset($_GET['id'])) {
    if (!es_admin()) {
        header("Location: dashboard.php?msg=acceso_denegado");
        exit;
    }
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("UPDATE propiedades SET estado = 'Activa' WHERE id = :id");
    $stmt->execute(['id' => $id]);
    header("Location: dashboard.php?msg=propiedad_aprobada");
    exit;
}

// 5. Eliminar Propiedad (Admin o Dueño de la propiedad)
if (isset($_GET['action']) && $_GET['action'] === 'delete_prop' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Obtener propiedad y verificar dueño
    $stmt = $pdo->prepare("SELECT usuario_id FROM propiedades WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $prop_data = $stmt->fetch();
    
    if (!$prop_data) {
        header("Location: dashboard.php?msg=propiedad_no_encontrada");
        exit;
    }
    
    if (!es_admin() && $prop_data['usuario_id'] != $my_user_id) {
        header("Location: dashboard.php?msg=acceso_denegado");
        exit;
    }
    
    // Obtener imágenes asociadas
    $stmt = $pdo->prepare("SELECT ruta_imagen FROM propiedades_imagenes WHERE propiedad_id = :id");
    $stmt->execute(['id' => $id]);
    $imgs = $stmt->fetchAll();
    
    foreach ($imgs as $img) {
        $file_path = __DIR__ . '/' . $img['ruta_imagen'];
        if (strpos($img['ruta_imagen'], 'uploads/propiedades/') === 0 && file_exists($file_path)) {
            @unlink($file_path);
        }
    }
    
    // Eliminar propiedad (imagenes se eliminan por CASCADE en BD)
    $stmt = $pdo->prepare("DELETE FROM propiedades WHERE id = :id");
    $stmt->execute(['id' => $id]);
    header("Location: dashboard.php?msg=propiedad_eliminada");
    exit;
}

// ── CALCULAR ESTADÍSTICAS REALES SEGÚN ROL ─────────────────────────────
if (es_admin()) {
    $count_prop_activas = $pdo->query("SELECT COUNT(*) FROM propiedades WHERE estado = 'Activa'")->fetchColumn();
    $count_propietarios = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo = 'Propietario' AND estado = 'Activo'")->fetchColumn();
    $count_gestores = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo = 'Gestor Freelance' AND estado = 'Activo'")->fetchColumn();
    
    $count_pendientes_usr = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE estado = 'Pendiente'")->fetchColumn();
    $count_pendientes_prop = $pdo->query("SELECT COUNT(*) FROM propiedades WHERE estado = 'Pendiente'")->fetchColumn();
    $count_total_pendientes = $count_pendientes_usr + $count_pendientes_prop;
} else {
    // Propietario o Gestor Freelance
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM propiedades WHERE estado = 'Activa' AND usuario_id = :uid");
    $stmt->execute(['uid' => $my_user_id]);
    $count_prop_activas = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM propiedades WHERE estado = 'Pendiente' AND usuario_id = :uid");
    $stmt->execute(['uid' => $my_user_id]);
    $count_prop_pendientes = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM propiedades WHERE estado = 'Rechazada' AND usuario_id = :uid");
    $stmt->execute(['uid' => $my_user_id]);
    $count_prop_rechazadas = $stmt->fetchColumn();
    
    $count_total_prop = $count_prop_activas + $count_prop_pendientes + $count_prop_rechazadas;
}

// ── CONSULTAR TABLAS DE USUARIOS (Solo Admin) ─────────────────────────
if (es_admin()) {
    $usuarios_pendientes = $pdo->query("SELECT id, rut, nombre, fecha_nacimiento, correo, sexo, telefono, tipo, estado, nro_registro_bbr, certificado_antecedentes, fecha_registro FROM usuarios WHERE estado = 'Pendiente'")->fetchAll();
    $propietarios_activos = $pdo->query("SELECT id, rut, nombre, fecha_nacimiento, correo, sexo, telefono, tipo, estado, nro_registro_bbr, certificado_antecedentes, fecha_registro FROM usuarios WHERE tipo = 'Propietario' AND estado = 'Activo'")->fetchAll();
    $gestores_activos = $pdo->query("SELECT id, rut, nombre, fecha_nacimiento, correo, sexo, telefono, tipo, estado, nro_registro_bbr, certificado_antecedentes, fecha_registro FROM usuarios WHERE tipo = 'Gestor Freelance' AND estado = 'Activo'")->fetchAll();
    $usuarios_rechazados = $pdo->query("SELECT id, rut, nombre, fecha_nacimiento, correo, sexo, telefono, tipo, estado, nro_registro_bbr, certificado_antecedentes, fecha_registro, motivo_rechazo FROM usuarios WHERE estado = 'Rechazado'")->fetchAll();
} else {
    $usuarios_pendientes = [];
    $propietarios_activos = [];
    $gestores_activos = [];
    $usuarios_rechazados = [];
}

// ── CONSULTAR TABLAS DE PROPIEDADES ────────────────────────────────
function obtenerPropiedadesDashboard($pdo, $estado, $user_id = null) {
    $sql = "SELECT p.*, GROUP_CONCAT(pi.ruta_imagen ORDER BY pi.es_principal DESC, pi.id ASC) as imagenes_str 
            FROM propiedades p 
            LEFT JOIN propiedades_imagenes pi ON p.id = pi.propiedad_id 
            WHERE p.estado = :estado";
    $params = ['estado' => $estado];
    
    if ($user_id !== null) {
        $sql .= " AND p.usuario_id = :uid";
        $params['uid'] = $user_id;
    }
    
    $sql .= " GROUP BY p.id ORDER BY p.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

if (es_admin()) {
    $propiedades_pendientes = obtenerPropiedadesDashboard($pdo, 'Pendiente');
    $propiedades_activas = obtenerPropiedadesDashboard($pdo, 'Activa');
    $propiedades_rechazadas = obtenerPropiedadesDashboard($pdo, 'Rechazada');
} else {
    $propiedades_pendientes = obtenerPropiedadesDashboard($pdo, 'Pendiente', $my_user_id);
    $propiedades_activas = obtenerPropiedadesDashboard($pdo, 'Activa', $my_user_id);
    $propiedades_rechazadas = obtenerPropiedadesDashboard($pdo, 'Rechazada', $my_user_id);
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- ══════════════════════════════════════════
DASHBOARD
══════════════════════════════════════════ -->
<div id="dashboard" class="py-4">
    <div class="container mt-4 mb-5">
        <!-- Header -->
        <div class="d-flex align-items-center justify-content-between mb-4 p-3 bg-white rounded-3 shadow-sm">
            <div class="d-flex align-items-center gap-3">
                <div style="width:42px;height:42px;background:var(--blue);border-radius:50%;display:flex;align-items:center;justify-content:center">
                    <i class="bi bi-person-fill text-white"></i>
                </div>
                <div>
                    <div class="fw-bold">Bienvenido: <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></div>
                    <small class="text-muted"><?php echo htmlspecialchars($_SESSION['usuario_tipo']); ?> del sistema</small>
                </div>
            </div>
            <div class="d-flex gap-2">
                <?php if (!es_admin()): ?>
                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalEditarPerfil">
                        <i class="bi bi-person-gear me-1"></i>Editar mi Perfil
                    </button>
                <?php endif; ?>
                <a href="login.php?action=logout" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i>Cerrar Sesión
                </a>
            </div>
        </div>

        <!-- Alerta de Usuario Rechazado -->
        <?php if ($me['estado'] === 'Rechazado'): ?>
            <div class="alert alert-danger p-4 mb-4 rounded-3 shadow-sm border-start border-danger border-4">
                <h5 class="alert-heading d-flex align-items-center gap-2 text-danger fw-bold">
                    <i class="bi bi-exclamation-octagon-fill fs-4"></i>
                    Tu Cuenta de Usuario ha sido Rechazada
                </h5>
                <p class="mb-3 text-dark">
                    Tu registro como <strong><?php echo htmlspecialchars($me['tipo']); ?></strong> fue rechazado por el Administrador.
                </p>
                <div class="bg-white p-3 rounded border border-danger-subtle mb-3">
                    <strong>Motivo del Rechazo:</strong>
                    <p class="mb-0 text-muted mt-1"><?php echo nl2br(htmlspecialchars($me['motivo_rechazo'] ?? 'No se especificó un motivo.')); ?></p>
                </div>
                <hr class="border-danger-subtle">
                <p class="mb-0">
                    <button class="btn btn-danger btn-sm text-white px-3" data-bs-toggle="modal" data-bs-target="#modalEditarPerfil">
                        <i class="bi bi-pencil-square me-1"></i>Corregir mis Datos y Re-postular
                    </button>
                </p>
            </div>
        <?php endif; ?>

        <!-- Mensajes del sistema -->
        <?php if ($msg === 'usuario_aprobado'): ?>
            <div class="alert alert-success small">Usuario aprobado y activado correctamente.</div>
        <?php elseif ($msg === 'usuario_eliminado'): ?>
            <div class="alert alert-warning small">El usuario y sus antecedentes fueron eliminados del sistema.</div>
        <?php elseif ($msg === 'usuario_creado'): ?>
            <div class="alert alert-success small">Nuevo usuario agregado exitosamente con estado Activo.</div>
        <?php elseif ($msg === 'propiedad_aprobada'): ?>
            <div class="alert alert-success small">La propiedad ha sido aprobada y ahora es pública en el buscador.</div>
        <?php elseif ($msg === 'propiedad_eliminada'): ?>
            <div class="alert alert-warning small">La propiedad y sus imágenes fueron eliminadas del sistema.</div>
        <?php endif; ?>

        <?php if (!empty($user_error)): ?>
            <div class="alert alert-danger small"><?php echo htmlspecialchars($user_error); ?></div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="row g-3 mb-4">
            <?php if (es_admin()): ?>
                <div class="col-md-3">
                    <div class="stat-card" style="background:linear-gradient(135deg,#0f766e,#0d9488)">
                        <i class="bi bi-house-fill fs-3"></i>
                        <h3 class="mt-1 mb-0"><?php echo $count_prop_activas; ?></h3>
                        <small>Propiedades Activas</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card" style="background:linear-gradient(135deg,#1e3a5f,#2563eb)">
                        <i class="bi bi-people-fill fs-3"></i>
                        <h3 class="mt-1 mb-0"><?php echo $count_propietarios; ?></h3>
                        <small>Propietarios</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card" style="background:linear-gradient(135deg,#7c3aed,#a855f7)">
                        <i class="bi bi-briefcase-fill fs-3"></i>
                        <h3 class="mt-1 mb-0"><?php echo $count_gestores; ?></h3>
                        <small>Gestores Activos</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card" style="background:linear-gradient(135deg,#b45309,#f59e0b)">
                        <i class="bi bi-clock-history fs-3"></i>
                        <h3 class="mt-1 mb-0"><?php echo $count_total_pendientes; ?></h3>
                        <small>Pendientes (<?php echo $count_pendientes_usr; ?> Usr / <?php echo $count_pendientes_prop; ?> Prop)</small>
                    </div>
                </div>
            <?php else: ?>
                <!-- Para Propietarios y Gestores -->
                <div class="col-md-3">
                    <div class="stat-card" style="background:linear-gradient(135deg,#0f766e,#0d9488)">
                        <i class="bi bi-house-check-fill fs-3"></i>
                        <h3 class="mt-1 mb-0"><?php echo $count_prop_activas; ?></h3>
                        <small>Mis Propiedades Activas</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card" style="background:linear-gradient(135deg,#b45309,#f59e0b)">
                        <i class="bi bi-clock fs-3"></i>
                        <h3 class="mt-1 mb-0"><?php echo $count_prop_pendientes; ?></h3>
                        <small>Mis Propiedades Pendientes</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card" style="background:linear-gradient(135deg,#991b1b,#dc2626)">
                        <i class="bi bi-x-circle-fill fs-3"></i>
                        <h3 class="mt-1 mb-0"><?php echo $count_prop_rechazadas; ?></h3>
                        <small>Mis Propiedades Inactivas</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card" style="background:linear-gradient(135deg,#1e293b,#475569)">
                        <i class="bi bi-collection-fill fs-3"></i>
                        <h3 class="mt-1 mb-0"><?php echo $count_total_prop; ?></h3>
                        <small>Total Publicadas por Mí</small>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if (es_admin()): ?>
            <!-- Módulos (Solo Administrador) -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="dashboard-card" onclick="mostrarTab('tab-usuarios')">
                        <i class="bi bi-people"></i>
                        <h5>Mantenedor de Usuarios</h5>
                        <p class="text-muted small mb-0">Gestiona propietarios y gestores (<?php echo count($usuarios_pendientes); ?> Pendientes)</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="dashboard-card" onclick="mostrarTab('tab-propiedades')">
                        <i class="bi bi-house-door"></i>
                        <h5>Mantenedor de Propiedades</h5>
                        <p class="text-muted small mb-0">Administra los inmuebles publicados (<?php echo count($propiedades_pendientes); ?> Pendientes)</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- CRUD Usuarios (Solo Administrador) -->
        <?php if (es_admin()): ?>
        <div id="tab-usuarios" style="display:none">
            <div class="bg-white rounded-3 shadow-sm p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Gestión de Usuarios</h5>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalRegistroUsuario">
                        <i class="bi bi-plus-lg me-1"></i>Agregar Usuario
                    </button>
                </div>

                <!-- Sub-tabs Usuarios -->
                <ul class="nav nav-pills mb-3 bg-light p-1 rounded-3" id="userSubTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active py-2 px-4" data-bs-toggle="pill" data-bs-target="#user-pending" type="button">
                            Pendientes <span class="badge bg-warning text-dark ms-1"><?php echo count($usuarios_pendientes); ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-2 px-4" data-bs-toggle="pill" data-bs-target="#user-propietarios" type="button">
                            Propietarios <span class="badge bg-secondary ms-1"><?php echo count($propietarios_activos); ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-2 px-4" data-bs-toggle="pill" data-bs-target="#user-gestores" type="button">
                            Gestores Freelance <span class="badge bg-secondary ms-1"><?php echo count($gestores_activos); ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-2 px-4" data-bs-toggle="pill" data-bs-target="#user-rechazados" type="button">
                            Rechazados <span class="badge bg-danger text-white ms-1"><?php echo count($usuarios_rechazados); ?></span>
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="userSubTabsContent">
                    <!-- Tabla Pendientes -->
                    <div class="tab-pane fade show active" id="user-pending" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>RUT</th>
                                        <th>Nombre</th>
                                        <th>Correo</th>
                                        <th>Tipo</th>
                                        <th>Fecha Registro</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($usuarios_pendientes)): ?>
                                        <tr><td colspan="6" class="text-center py-3 text-muted">No hay usuarios pendientes de aprobación.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($usuarios_pendientes as $usr): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($usr['rut']); ?></td>
                                                <td><?php echo htmlspecialchars($usr['nombre']); ?></td>
                                                <td><?php echo htmlspecialchars($usr['correo']); ?></td>
                                                <td><span class="badge <?php echo ($usr['tipo'] === 'Propietario') ? 'bg-success' : 'bg-info'; ?>"><?php echo htmlspecialchars($usr['tipo']); ?></span></td>
                                                <td><?php echo date('d-m-Y H:i', strtotime($usr['fecha_registro'])); ?></td>
                                                <td>
                                                     <div class="d-flex gap-1">
                                                         <button class="btn btn-info btn-sm text-white" onclick='verUsuario(<?php echo htmlspecialchars(json_encode($usr), ENT_QUOTES, "UTF-8"); ?>)'><i class="bi bi-eye me-1"></i>Ver</button>
                                                         <a class="btn btn-success btn-sm confirmar-accion" href="dashboard.php?action=approve_user&id=<?php echo $usr['id']; ?>" data-mensaje="¿Está seguro de activar y habilitar a este usuario en el sistema?"><i class="bi bi-check-lg me-1"></i>Aceptar</a>
                                                         <button class="btn btn-danger btn-sm btn-rechazar-user" data-id="<?php echo $usr['id']; ?>" data-nombre="<?php echo htmlspecialchars($usr['nombre'], ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-x-lg me-1"></i>Rechazar</button>
                                                     </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tabla Propietarios Activos -->
                    <div class="tab-pane fade" id="user-propietarios" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>RUT</th>
                                        <th>Nombre</th>
                                        <th>Correo</th>
                                        <th>N° RBR</th>
                                        <th>Teléfono</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($propietarios_activos)): ?>
                                        <tr><td colspan="6" class="text-center py-3 text-muted">No hay propietarios activos registrados.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($propietarios_activos as $usr): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($usr['rut']); ?></td>
                                                <td><?php echo htmlspecialchars($usr['nombre']); ?></td>
                                                <td><?php echo htmlspecialchars($usr['correo']); ?></td>
                                                <td><code><?php echo htmlspecialchars($usr['nro_registro_bbr']); ?></code></td>
                                                <td><?php echo htmlspecialchars($usr['telefono']); ?></td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <button class="btn btn-info btn-sm text-white" title="Ver Info"
                                                            onclick='verUsuario(<?php echo htmlspecialchars(json_encode($usr), ENT_QUOTES, "UTF-8"); ?>)'><i class="bi bi-eye"></i></button>
                                                        <a class="btn btn-warning btn-sm text-white" title="Editar" href="editar_usuario.php?id=<?php echo $usr['id']; ?>"><i class="bi bi-pencil"></i></a>
                                                        <a class="btn btn-danger btn-sm confirmar-accion" title="Eliminar" href="dashboard.php?action=delete_user&id=<?php echo $usr['id']; ?>" data-mensaje="¿Está seguro de eliminar a este propietario del sistema? Se perderá su cuenta."><i class="bi bi-trash"></i></a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tabla Gestores Activos -->
                    <div class="tab-pane fade" id="user-gestores" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>RUT</th>
                                        <th>Nombre</th>
                                        <th>Correo</th>
                                        <th>Teléfono</th>
                                        <th>Certificado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($gestores_activos)): ?>
                                        <tr><td colspan="6" class="text-center py-3 text-muted">No hay gestores activos registrados.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($gestores_activos as $usr): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($usr['rut']); ?></td>
                                                <td><?php echo htmlspecialchars($usr['nombre']); ?></td>
                                                <td><?php echo htmlspecialchars($usr['correo']); ?></td>
                                                <td><?php echo htmlspecialchars($usr['telefono']); ?></td>
                                                <td>
                                                    <?php if(!empty($usr['certificado_antecedentes'])): ?>
                                                        <a href="uploads/certificados/<?php echo htmlspecialchars($usr['certificado_antecedentes']); ?>" target="_blank" class="btn btn-outline-secondary btn-sm py-0"><i class="bi bi-file-earmark-pdf me-1"></i>Ver archivo</a>
                                                    <?php else: ?>
                                                        <span class="text-muted small">No adjuntó</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <button class="btn btn-info btn-sm text-white" title="Ver Info"
                                                            onclick='verUsuario(<?php echo htmlspecialchars(json_encode($usr), ENT_QUOTES, "UTF-8"); ?>)'><i class="bi bi-eye"></i></button>
                                                        <a class="btn btn-warning btn-sm text-white" title="Editar" href="editar_usuario.php?id=<?php echo $usr['id']; ?>"><i class="bi bi-pencil"></i></a>
                                                        <a class="btn btn-danger btn-sm confirmar-accion" title="Eliminar" href="dashboard.php?action=delete_user&id=<?php echo $usr['id']; ?>" data-mensaje="¿Está seguro de eliminar a este gestor freelance?"><i class="bi bi-trash"></i></a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tabla Usuarios Rechazados -->
                    <div class="tab-pane fade" id="user-rechazados" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>RUT</th>
                                        <th>Nombre</th>
                                        <th>Correo</th>
                                        <th>Tipo</th>
                                        <th>Motivo de Rechazo</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($usuarios_rechazados)): ?>
                                        <tr><td colspan="6" class="text-center py-3 text-muted">No hay usuarios rechazados.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($usuarios_rechazados as $usr): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($usr['rut']); ?></td>
                                                <td><?php echo htmlspecialchars($usr['nombre']); ?></td>
                                                <td><?php echo htmlspecialchars($usr['correo']); ?></td>
                                                <td><span class="badge bg-danger"><?php echo htmlspecialchars($usr['tipo']); ?></span></td>
                                                <td class="text-danger small">
                                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                                    <?php echo htmlspecialchars($usr['motivo_rechazo'] ?? 'No especificado'); ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <button class="btn btn-info btn-sm text-white" title="Ver Info"
                                                            onclick='verUsuario(<?php echo htmlspecialchars(json_encode($usr), ENT_QUOTES, "UTF-8"); ?>)'><i class="bi bi-eye"></i></button>
                                                        <a class="btn btn-warning btn-sm text-white" title="Editar" href="editar_usuario.php?id=<?php echo $usr['id']; ?>"><i class="bi bi-pencil"></i></a>
                                                        <a class="btn btn-danger btn-sm confirmar-accion" title="Eliminar" href="dashboard.php?action=delete_user&id=<?php echo $usr['id']; ?>" data-mensaje="¿Está seguro de eliminar a este usuario rechazado?"><i class="bi bi-trash"></i></a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- CRUD Propiedades -->
        <div id="tab-propiedades" style="display:none">
            <div class="bg-white rounded-3 shadow-sm p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Gestión de Propiedades</h5>
                    <a class="btn btn-primary btn-sm" href="agregar_propiedad.php">
                        <i class="bi bi-plus-lg me-1"></i>Agregar Propiedad
                    </a>
                </div>

                <!-- Sub-tabs Propiedades -->
                <ul class="nav nav-pills mb-3 bg-light p-1 rounded-3" id="propSubTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active py-2 px-4" data-bs-toggle="pill" data-bs-target="#prop-pendientes" type="button">
                            Pendientes <span class="badge bg-warning text-dark ms-1"><?php echo count($propiedades_pendientes); ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-2 px-4" data-bs-toggle="pill" data-bs-target="#prop-activas" type="button">
                            Activas <span class="badge bg-secondary ms-1"><?php echo count($propiedades_activas); ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-2 px-4" data-bs-toggle="pill" data-bs-target="#prop-rechazadas" type="button">
                            Rechazadas <span class="badge bg-danger text-white ms-1"><?php echo count($propiedades_rechazadas); ?></span>
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="propSubTabsContent">
                    <!-- Propiedades Pendientes -->
                    <div class="tab-pane fade show active" id="prop-pendientes" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Título</th>
                                        <th>Tipo</th>
                                        <th>Ubicación</th>
                                        <th>Precio</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($propiedades_pendientes)): ?>
                                        <tr><td colspan="6" class="text-center py-3 text-muted">No hay propiedades pendientes de aprobación.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($propiedades_pendientes as $prop): 
                                            $imagenes = !empty($prop['imagenes_str']) ? explode(',', $prop['imagenes_str']) : [];
                                            $comodidades_arr = !empty($prop['comodidades']) ? array_map('trim', explode(',', $prop['comodidades'])) : [];
                                            
                                            $precio_clp_fmt = '$' . number_format($prop['precio_clp'], 0, ',', '.');
                                            $precio_uf_fmt = 'UF ' . number_format($prop['precio_uf'], 0, ',', '.');
                                            
                                            $dorm = !is_null($prop['dormitorios']) ? $prop['dormitorios'] : '-';
                                            $ban = !is_null($prop['banos']) ? $prop['banos'] : '-';
                                            $const = !empty($prop['area_construida']) ? $prop['area_construida'] : '-';
                                            $terr = !empty($prop['area_terreno']) ? $prop['area_terreno'] : '-';
                                        ?>
                                            <tr>
                                                <td><code><?php echo htmlspecialchars($prop['codigo']); ?></code></td>
                                                <td><?php echo htmlspecialchars($prop['titulo']); ?></td>
                                                <td><?php echo htmlspecialchars($prop['tipo']); ?></td>
                                                <td><?php echo htmlspecialchars($prop['comuna'] . ', ' . $prop['provincia']); ?></td>
                                                <td><strong><?php echo $precio_clp_fmt; ?></strong> <span class="text-muted small">/ <?php echo $precio_uf_fmt; ?></span></td>
                                                <td>
                                                     <div class="d-flex gap-1">
                                                         <button class="btn btn-info btn-sm text-white" onclick="abrirDetalle('<?php echo $prop['codigo']; ?>', '<?php echo htmlspecialchars($prop['titulo'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo $precio_clp_fmt; ?>', '<?php echo $precio_uf_fmt; ?>', '<?php echo $dorm; ?>', '<?php echo $ban; ?>', '<?php echo $const; ?>', '<?php echo $terr; ?>', <?php echo htmlspecialchars(json_encode($imagenes)); ?>, '<?php echo htmlspecialchars($prop['descripcion'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo htmlspecialchars(json_encode($comodidades_arr)); ?>)"><i class="bi bi-eye me-1"></i>Ver</button>
                                                         <?php if (es_admin()): ?>
                                                             <a class="btn btn-success btn-sm confirmar-accion" href="dashboard.php?action=approve_prop&id=<?php echo $prop['id']; ?>" data-mensaje="¿Está seguro de publicar y activar esta propiedad en el catálogo público?"><i class="bi bi-check-lg me-1"></i>Aceptar</a>
                                                             <button class="btn btn-danger btn-sm btn-rechazar-prop" data-id="<?php echo $prop['id']; ?>" data-titulo="<?php echo htmlspecialchars($prop['titulo'], ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-x-lg me-1"></i>Rechazar</button>
                                                         <?php endif; ?>
                                                     </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Propiedades Activas -->
                    <div class="tab-pane fade" id="prop-activas" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Título</th>
                                        <th>Tipo</th>
                                        <th>Ubicación</th>
                                        <th>Precio</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($propiedades_activas)): ?>
                                        <tr><td colspan="6" class="text-center py-3 text-muted">No hay propiedades activas.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($propiedades_activas as $prop): 
                                            $imagenes = !empty($prop['imagenes_str']) ? explode(',', $prop['imagenes_str']) : [];
                                            $comodidades_arr = !empty($prop['comodidades']) ? array_map('trim', explode(',', $prop['comodidades'])) : [];
                                            
                                            $precio_clp_fmt = '$' . number_format($prop['precio_clp'], 0, ',', '.');
                                            $precio_uf_fmt = 'UF ' . number_format($prop['precio_uf'], 0, ',', '.');
                                            
                                            $dorm = !is_null($prop['dormitorios']) ? $prop['dormitorios'] : '-';
                                            $ban = !is_null($prop['banos']) ? $prop['banos'] : '-';
                                            $const = !empty($prop['area_construida']) ? $prop['area_construida'] : '-';
                                            $terr = !empty($prop['area_terreno']) ? $prop['area_terreno'] : '-';
                                        ?>
                                            <tr>
                                                <td><code><?php echo htmlspecialchars($prop['codigo']); ?></code></td>
                                                <td><?php echo htmlspecialchars($prop['titulo']); ?></td>
                                                <td><?php echo htmlspecialchars($prop['tipo']); ?></td>
                                                <td><?php echo htmlspecialchars($prop['comuna'] . ', ' . $prop['provincia']); ?></td>
                                                <td><strong><?php echo $precio_clp_fmt; ?></strong> <span class="text-muted small">/ <?php echo $precio_uf_fmt; ?></span></td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <button class="btn btn-info btn-sm text-white" title="Leer Info"
                                                            onclick="abrirDetalle('<?php echo $prop['codigo']; ?>', '<?php echo htmlspecialchars($prop['titulo'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo $precio_clp_fmt; ?>', '<?php echo $precio_uf_fmt; ?>', '<?php echo $dorm; ?>', '<?php echo $ban; ?>', '<?php echo $const; ?>', '<?php echo $terr; ?>', <?php echo htmlspecialchars(json_encode($imagenes)); ?>, '<?php echo htmlspecialchars($prop['descripcion'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo htmlspecialchars(json_encode($comodidades_arr)); ?>)"><i class="bi bi-eye"></i></button>
                                                        <a class="btn btn-warning btn-sm text-white" title="Editar" href="editar_propiedad.php?id=<?php echo $prop['id']; ?>"><i class="bi bi-pencil"></i></a>
                                                        <a class="btn btn-danger btn-sm confirmar-accion" title="Eliminar" href="dashboard.php?action=delete_prop&id=<?php echo $prop['id']; ?>" data-mensaje="¿Está seguro de eliminar definitivamente esta propiedad activa y sus imágenes?"><i class="bi bi-trash"></i></a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Propiedades Rechazadas -->
                    <div class="tab-pane fade" id="prop-rechazadas" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Título</th>
                                        <th>Tipo</th>
                                        <th>Ubicación</th>
                                        <th>Precio</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($propiedades_rechazadas)): ?>
                                        <tr><td colspan="6" class="text-center py-3 text-muted">No hay propiedades rechazadas o inactivas.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($propiedades_rechazadas as $prop): 
                                            $imagenes = !empty($prop['imagenes_str']) ? explode(',', $prop['imagenes_str']) : [];
                                            $comodidades_arr = !empty($prop['comodidades']) ? array_map('trim', explode(',', $prop['comodidades'])) : [];
                                            
                                            $precio_clp_fmt = '$' . number_format($prop['precio_clp'], 0, ',', '.');
                                            $precio_uf_fmt = 'UF ' . number_format($prop['precio_uf'], 0, ',', '.');
                                            
                                            $dorm = !is_null($prop['dormitorios']) ? $prop['dormitorios'] : '-';
                                            $ban = !is_null($prop['banos']) ? $prop['banos'] : '-';
                                            $const = !empty($prop['area_construida']) ? $prop['area_construida'] : '-';
                                            $terr = !empty($prop['area_terreno']) ? $prop['area_terreno'] : '-';
                                        ?>
                                            <tr>
                                                <td><code><?php echo htmlspecialchars($prop['codigo']); ?></code></td>
                                                <td>
                                                    <?php echo htmlspecialchars($prop['titulo']); ?>
                                                    <?php if (!empty($prop['motivo_rechazo'])): ?>
                                                        <div class="text-danger small mt-1">
                                                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                                            <strong>Motivo del rechazo:</strong> <?php echo htmlspecialchars($prop['motivo_rechazo']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($prop['tipo']); ?></td>
                                                <td><?php echo htmlspecialchars($prop['comuna'] . ', ' . $prop['provincia']); ?></td>
                                                <td><strong><?php echo $precio_clp_fmt; ?></strong> <span class="text-muted small">/ <?php echo $precio_uf_fmt; ?></span></td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <button class="btn btn-info btn-sm text-white" title="Leer Info"
                                                            onclick="abrirDetalle('<?php echo $prop['codigo']; ?>', '<?php echo htmlspecialchars($prop['titulo'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo $precio_clp_fmt; ?>', '<?php echo $precio_uf_fmt; ?>', '<?php echo $dorm; ?>', '<?php echo $ban; ?>', '<?php echo $const; ?>', '<?php echo $terr; ?>', <?php echo htmlspecialchars(json_encode($imagenes)); ?>, '<?php echo htmlspecialchars($prop['descripcion'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo htmlspecialchars(json_encode($comodidades_arr)); ?>)"><i class="bi bi-eye"></i></button>
                                                        <a class="btn btn-warning btn-sm text-white" title="Editar" href="editar_propiedad.php?id=<?php echo $prop['id']; ?>"><i class="bi bi-pencil"></i></a>
                                                        <a class="btn btn-danger btn-sm confirmar-accion" title="Eliminar" href="dashboard.php?action=delete_prop&id=<?php echo $prop['id']; ?>" data-mensaje="¿Está seguro de eliminar definitivamente esta propiedad rechazada y sus imágenes?"><i class="bi bi-trash"></i></a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL REGISTRO USUARIO -->
<div class="modal fade" id="modalRegistroUsuario" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title text-white">Registrar Nuevo Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="POST" action="dashboard.php">
                    <input type="hidden" name="form_action" value="add_user">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Nombre Completo</label>
                            <input type="text" name="nombre" class="form-control" placeholder="Ej: Juan Pérez" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">RUT</label>
                            <input type="text" name="rut" class="form-control" placeholder="12.345.678-9" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Correo</label>
                            <input type="email" name="correo" class="form-control" placeholder="correo@ejemplo.com" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Tipo de Usuario</label>
                            <select name="tipo" class="form-select" required>
                                <option>Propietario</option>
                                <option>Gestor Freelance</option>
                                <option>Administrador</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Contraseña Temporal</label>
                            <input type="password" name="password" class="form-control" placeholder="********" required>
                        </div>
                    </div>
                    <div class="mt-4 text-center">
                        <button type="submit" class="btn btn-primary px-5">Registrar Usuario</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL VER USUARIO -->
<div class="modal fade" id="modalVerUsuario" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title text-white">Información del Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="list-group list-group-flush">
                    <div class="list-group-item d-flex justify-content-between">
                        <strong>ID en BD:</strong> <span id="ver-user-id"></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <strong>Nombre:</strong> <span id="ver-user-nombre"></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <strong>RUT:</strong> <span id="ver-user-rut"></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <strong>Fecha de Nacimiento:</strong> <span id="ver-user-fecha-nacimiento"></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <strong>Sexo:</strong> <span id="ver-user-sexo"></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <strong>Correo:</strong> <span id="ver-user-correo"></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <strong>Teléfono:</strong> <span id="ver-user-telefono"></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <strong>Tipo:</strong> <span id="ver-user-tipo"></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <strong>Estado:</strong> <span id="ver-user-estado"></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between" id="ver-user-bbr-container">
                        <strong>N° Registro Bienes Raíces:</strong> <span id="ver-user-bbr"></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between" id="ver-user-certificado-container">
                        <strong>Certificado Antecedentes:</strong> <span id="ver-user-certificado"></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between">
                        <strong>Fecha Registro:</strong> <span id="ver-user-fecha-registro"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DETALLE PROP (Para previsualización en el dashboard) -->
<div class="modal fade" id="modalDetalle" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle de Propiedad</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <!-- Carrusel de imágenes -->
                        <div id="carouselDetalle" class="carousel slide" data-bs-ride="carousel">
                            <div class="carousel-inner rounded-3" id="det-carrusel-inner"
                                style="height:220px; overflow:hidden;">
                                <!-- Las imágenes se inyectan acá -->
                            </div>
                            <button class="carousel-control-prev" type="button" data-bs-target="#carouselDetalle"
                                data-bs-slide="prev">
                                <span class="carousel-control-prev-icon bg-dark rounded-circle p-2"
                                    aria-hidden="true" style="opacity:0.7;"></span>
                                <span class="visually-hidden">Anterior</span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#carouselDetalle"
                                data-bs-slide="next">
                                <span class="carousel-control-next-icon bg-dark rounded-circle p-2"
                                    aria-hidden="true" style="opacity:0.7;"></span>
                                <span class="visually-hidden">Siguiente</span>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6 d-flex flex-column justify-content-between pt-3 pt-md-0">
                        <div>
                            <small class="text-muted" id="det-cod"></small>
                            <h4 id="det-nombre" class="mt-1"></h4>
                            <p class="text-muted small" id="det-fecha"></p>
                        </div>
                        <div>
                            <h4 style="color:var(--teal)" id="det-precio"></h4>
                            <h6 class="text-muted" id="det-uf"></h6>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="row g-3 mb-3">
                    <div class="col-6 col-md-3 text-center">
                        <div class="bg-light rounded-3 p-3">
                            <i class="bi bi-door-open fs-4" style="color:var(--teal)"></i>
                            <p class="mb-0 small mt-1">Dormitorios</p>
                            <strong id="det-dorm"></strong>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 text-center">
                        <div class="bg-light rounded-3 p-3">
                            <i class="bi bi-droplet fs-4" style="color:var(--teal)"></i>
                            <p class="mb-0 small mt-1">Baños</p>
                            <strong id="det-banos"></strong>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 text-center">
                        <div class="bg-light rounded-3 p-3">
                            <i class="bi bi-aspect-ratio fs-4" style="color:var(--teal)"></i>
                            <p class="mb-0 small mt-1">Área Constr.</p>
                            <strong id="det-construida"></strong>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 text-center">
                        <div class="bg-light rounded-3 p-3">
                            <i class="bi bi-map fs-4" style="color:var(--teal)"></i>
                            <p class="mb-0 small mt-1">Área Terr.</p>
                            <strong id="det-terreno"></strong>
                        </div>
                    </div>
                </div>
                <p class="text-muted mb-3" id="det-desc"></p>
                <h6 class="mb-2">Características:</h6>
                <div class="mb-3" id="det-caracteristicas">
                    <!-- Características inyectadas -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL EDITAR PERFIL -->
<div class="modal fade" id="modalEditarPerfil" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title text-white">Editar mi Perfil / Re-postular</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="POST" action="dashboard.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($me['nombre'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">RUT <span class="text-danger">*</span></label>
                            <input type="text" name="rut" class="form-control" value="<?php echo htmlspecialchars($me['rut'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha de Nacimiento <span class="text-danger">*</span></label>
                            <input type="date" name="fecha_nacimiento" class="form-control" value="<?php echo htmlspecialchars($me['fecha_nacimiento'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Correo <span class="text-danger">*</span></label>
                            <input type="email" name="correo" class="form-control" value="<?php echo htmlspecialchars($me['correo'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Teléfono <span class="text-danger">*</span></label>
                            <input type="text" name="telefono" class="form-control" value="<?php echo htmlspecialchars($me['telefono'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Sexo <span class="text-danger">*</span></label>
                            <select name="sexo" class="form-select" required>
                                <option value="Masculino" <?php echo (($me['sexo'] ?? '') === 'Masculino') ? 'selected' : ''; ?>>Masculino</option>
                                <option value="Femenino" <?php echo (($me['sexo'] ?? '') === 'Femenino') ? 'selected' : ''; ?>>Femenino</option>
                                <option value="Prefiero no indicar" <?php echo (($me['sexo'] ?? '') === 'Prefiero no indicar') ? 'selected' : ''; ?>>Prefiero no indicar</option>
                            </select>
                        </div>
                        
                        <?php if (($me['tipo'] ?? '') === 'Propietario'): ?>
                            <div class="col-md-12">
                                <label class="form-label">N° Registro Bienes Raíces <span class="text-danger">*</span></label>
                                <input type="text" name="nro_registro_bbr" class="form-control" value="<?php echo htmlspecialchars($me['nro_registro_bbr'] ?? ''); ?>" required>
                            </div>
                        <?php elseif (($me['tipo'] ?? '') === 'Gestor Freelance'): ?>
                            <div class="col-md-12">
                                <label class="form-label">Certificado de Antecedentes (PDF / Imagen)</label>
                                <input type="file" name="certificado" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <?php if (!empty($me['certificado_antecedentes'])): ?>
                                    <div class="form-text">
                                        Archivo actual: <a href="uploads/certificados/<?php echo htmlspecialchars($me['certificado_antecedentes']); ?>" target="_blank">Ver actual</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="mt-4 text-center">
                        <button type="submit" class="btn btn-primary px-5">Guardar Cambios y Postular</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Inicializar abriendo la pestaña por defecto según el rol
    window.addEventListener('DOMContentLoaded', () => {
        mostrarTab('<?php echo es_admin() ? "tab-usuarios" : "tab-propiedades"; ?>');
        
        // Autoformatear RUT en cualquier input que tenga nombre "rut"
        document.querySelectorAll('input[name="rut"]').forEach(rutInput => {
            rutInput.addEventListener('input', () => {
                let valor = rutInput.value.replace(/[^0-9kK]/g, '');
                if (valor.length < 2) return;
                let cuerpo = valor.slice(0, -1);
                let dv = valor.slice(-1).toUpperCase();
                let cuerpoFormateado = '';
                while (cuerpo.length > 3) {
                    cuerpoFormateado = '.' + cuerpo.slice(-3) + cuerpoFormateado;
                    cuerpo = cuerpo.slice(0, -3);
                }
                cuerpoFormateado = cuerpo + cuerpoFormateado;
                rutInput.value = cuerpoFormateado + '-' + dv;
            });
        });

        // Validaciones en formulario de modal de creación de usuarios (Solo para Admin)
        const modalForm = document.querySelector('#modalRegistroUsuario form');
        if (modalForm) {
            modalForm.addEventListener('submit', (e) => {
                const rutVal = modalForm.querySelector('input[name="rut"]').value;
                const passwordVal = modalForm.querySelector('input[name="password"]').value;
                
                if (!validarRutJs(rutVal)) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'RUT Inválido',
                        text: 'El RUT ingresado no es válido.',
                        confirmButtonColor: '#0f766e'
                    });
                    return;
                }

                const robustRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).{8,}$/;
                if (!robustRegex.test(passwordVal)) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Contraseña Débil',
                        text: 'La contraseña temporal debe tener mínimo 8 caracteres, e incluir al menos una mayúscula, una minúscula, un número y un carácter especial.',
                        confirmButtonColor: '#0f766e'
                    });
                    return;
                }
            });
        }

        // Lógica de rechazo de usuarios (SweetAlert2 con motivo)
        document.querySelectorAll('.btn-rechazar-user').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const nombre = this.getAttribute('data-nombre');
                
                Swal.fire({
                    title: 'Rechazar Usuario',
                    text: `Indique la razón del rechazo para ${nombre}:`,
                    input: 'textarea',
                    inputPlaceholder: 'Escriba aquí los motivos (ej: RUT incorrecto, falta certificado, etc.)...',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Rechazar',
                    cancelButtonText: 'Cancelar',
                    inputValidator: (value) => {
                        if (!value.trim()) {
                            return 'Debe ingresar una razón para el rechazo.';
                        }
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = 'dashboard.php';
                        
                        const inputAction = document.createElement('input');
                        inputAction.type = 'hidden';
                        inputAction.name = 'action';
                        inputAction.value = 'reject_user';
                        form.appendChild(inputAction);
                        
                        const inputId = document.createElement('input');
                        inputId.type = 'hidden';
                        inputId.name = 'id';
                        inputId.value = id;
                        form.appendChild(inputId);
                        
                        const inputMotivo = document.createElement('input');
                        inputMotivo.type = 'hidden';
                        inputMotivo.name = 'motivo';
                        inputMotivo.value = result.value;
                        form.appendChild(inputMotivo);
                        
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });
        });

        // Lógica de rechazo de propiedades (SweetAlert2 con motivo)
        document.querySelectorAll('.btn-rechazar-prop').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const titulo = this.getAttribute('data-titulo');
                
                Swal.fire({
                    title: 'Rechazar Propiedad',
                    text: `Indique la razón del rechazo para "${titulo}":`,
                    input: 'textarea',
                    inputPlaceholder: 'Escriba aquí los motivos (ej: precio incorrecto, fotos de mala calidad, etc.)...',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Rechazar',
                    cancelButtonText: 'Cancelar',
                    inputValidator: (value) => {
                        if (!value.trim()) {
                            return 'Debe ingresar una razón para el rechazo.';
                        }
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = 'dashboard.php';
                        
                        const inputAction = document.createElement('input');
                        inputAction.type = 'hidden';
                        inputAction.name = 'action';
                        inputAction.value = 'reject_prop';
                        form.appendChild(inputAction);
                        
                        const inputId = document.createElement('input');
                        inputId.type = 'hidden';
                        inputId.name = 'id';
                        inputId.value = id;
                        form.appendChild(inputId);
                        
                        const inputMotivo = document.createElement('input');
                        inputMotivo.type = 'hidden';
                        inputMotivo.name = 'motivo';
                        inputMotivo.value = result.value;
                        form.appendChild(inputMotivo);
                        
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });
        });

        function validarRutJs(rut) {
            const cleanRut = rut.replace(/\./g, '').replace('-', '');
            if (cleanRut.length < 2) return false;
            const cuerpo = cleanRut.slice(0, -1);
            let dv = cleanRut.slice(-1).toUpperCase();
            if (!/^[0-9]+$/.test(cuerpo)) return false;
            
            let suma = 0;
            let multiplicador = 2;
            for (let i = cuerpo.length - 1; i >= 0; i--) {
                suma += parseInt(cuerpo[i]) * multiplicador;
                multiplicador = multiplicador === 7 ? 2 : multiplicador + 1;
            }
            const valorEsperado = 11 - (suma % 11);
            const dvEsperado = valorEsperado === 11 ? '0' : valorEsperado === 10 ? 'K' : valorEsperado.toString();
            return dv === dvEsperado;
        }
    });
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
