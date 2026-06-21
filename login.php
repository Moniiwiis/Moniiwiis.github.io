<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

// Cerrar sesión si se solicita
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header("Location: login.php?msg=sesion_cerrada");
    exit;
}

// Si ya está logueado, redirigir
if (esta_autenticado()) {
    if (es_admin()) {
        header("Location: dashboard.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

$error = '';
$success = '';

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($correo) || empty($password)) {
        $error = 'Por favor, complete todos los campos.';
    } else {
        // Buscar usuario en la base de datos
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE correo = :correo");
        $stmt->execute(['correo' => $correo]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Verificar estado de la cuenta
            if ($user['estado'] === 'Pendiente') {
                $error = 'Tu cuenta aún está pendiente de activación por el administrador. Recibirás una notificación por correo.';
            } else {
                // Iniciar sesión exitosa
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['usuario_nombre'] = $user['nombre'];
                $_SESSION['usuario_correo'] = $user['correo'];
                $_SESSION['usuario_tipo'] = $user['tipo'];
                $_SESSION['usuario_rut'] = $user['rut'];

                // Redirigir según el rol
                if ($user['tipo'] === 'Administrador') {
                    header("Location: dashboard.php");
                } else {
                    header("Location: index.php");
                }
                exit;
            }
        } else {
            $error = 'Usuario o contraseña incorrectos.';
        }
    }
}

// Mensajes de redirección
$msg = $_GET['msg'] ?? '';
if ($msg === 'debes_iniciar_sesion') {
    $error = 'Debes iniciar sesión para acceder al panel de administración.';
} elseif ($msg === 'sesion_cerrada') {
    $success = 'Sesión cerrada correctamente. ¡Vuelve pronto!';
} elseif ($msg === 'acceso_denegado') {
    $error = 'Acceso denegado. No tienes permisos de administrador para ingresar a esta sección.';
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- ══════════════════════════════════════════
LOGIN
══════════════════════════════════════════ -->
<div id="login-section" class="py-5">
    <div class="container">
        <div class="login-card my-4">
            <div class="text-center mb-4">
                <div style="font-size:3rem">&#128274;</div>
                <h3>Iniciar Sesión</h3>
                <p class="text-muted small">Accede a tu cuenta PNK Inmobiliaria</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger small"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success small"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div id="login-form">
                <form method="POST" action="login.php">
                    <div class="mb-3">
                        <label class="form-label">Usuario / Correo</label>
                        <input type="email" name="correo" class="form-control" placeholder="correo@email.com" required value="<?php echo isset($_POST['correo']) ? htmlspecialchars($_POST['correo']) : ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña</label>
                        <input type="password" name="password" class="form-control" placeholder="********" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 mb-3">Ingresar</button>
                </form>
                <div class="text-center">
                    <span class="back-link" onclick="toggleRecuperar(true)">¿Olvidaste tu contraseña? Recuperar</span>
                </div>
            </div>
            <div id="recuperar-form" style="display:none">
                <div class="alert alert-info small">Ingresa tu correo y te enviaremos instrucciones para restablecer tu contraseña.</div>
                <div class="mb-3">
                    <label class="form-label">Correo Electrónico</label>
                    <input type="email" class="form-control" placeholder="correo@email.com">
                </div>
                <button class="btn btn-primary w-100 mb-3" onclick="Swal.fire({ icon: 'success', title: 'Correo Enviado', text: 'Instrucciones enviadas al correo especificado.', confirmButtonColor: '#0f766e' }).then(() => { toggleRecuperar(false); });">Enviar instrucciones</button>
                <div class="text-center">
                    <span class="back-link" onclick="toggleRecuperar(false)">&#8592; Volver al login</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($error)): ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        Swal.fire({
            icon: 'error',
            title: 'Atención',
            text: '<?php echo addslashes($error); ?>',
            confirmButtonColor: '#0f766e'
        });
    });
</script>
<?php endif; ?>

<?php if (!empty($success)): ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        Swal.fire({
            icon: 'success',
            title: '¡Operación Exitosa!',
            text: '<?php echo addslashes($success); ?>',
            confirmButtonColor: '#0f766e'
        });
    });
</script>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
