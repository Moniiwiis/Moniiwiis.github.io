<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/utils.php';

// Si ya está logueado, redirigir
if (esta_autenticado()) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rut = trim($_POST['rut'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
    $correo = trim($_POST['correo'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $sexo = $_POST['sexo'] ?? '';
    $telefono = trim($_POST['telefono'] ?? '');
    $nro_registro_bbr = trim($_POST['nro_registro_bbr'] ?? '');

    // Validar campos obligatorios
    if (empty($rut) || empty($nombre) || empty($fecha_nacimiento) || empty($correo) || empty($password) || empty($confirm_password) || empty($sexo) || empty($telefono) || empty($nro_registro_bbr)) {
        $error = 'Por favor, complete todos los campos obligatorios (*).';
    } elseif (!validarRut($rut)) {
        $error = 'El RUT ingresado no es válido. Verifique el formato y dígito verificador.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo electrónico ingresado no tiene un formato válido.';
    } elseif (strlen($nombre) > 100) {
        $error = 'El nombre completo no puede superar los 100 caracteres.';
    } elseif (strlen($correo) > 100) {
        $error = 'El correo electrónico no puede superar los 100 caracteres.';
    } elseif (strlen($telefono) > 20) {
        $error = 'El teléfono no puede superar los 20 caracteres.';
    } elseif (strlen($nro_registro_bbr) > 50) {
        $error = 'El número de registro de bienes raíces no puede superar los 50 caracteres.';
    } elseif (!validarPasswordRobusta($password)) {
        $error = 'La contraseña debe tener mínimo 8 caracteres, incluyendo al menos una mayúscula, una minúscula, un número y un carácter especial.';
    } elseif ($password !== $confirm_password) {
        $error = 'Las contraseñas ingresadas no coinciden.';
    } else {
        try {
            // Formatear RUT antes de buscar e insertar
            $rut = formatearRut($rut);

            // Verificar si el RUT o Correo ya existen
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE rut = :rut OR correo = :correo");
            $stmt->execute(['rut' => $rut, 'correo' => $correo]);
            if ($stmt->fetch()) {
                $error = 'El RUT o el Correo Electrónico ya se encuentran registrados en el sistema.';
            } else {
                // Insertar usuario
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO usuarios (rut, nombre, fecha_nacimiento, correo, password, sexo, telefono, tipo, estado, nro_registro_bbr) 
                                       VALUES (:rut, :nombre, :fecha_nacimiento, :correo, :password, :sexo, :telefono, 'Propietario', 'Pendiente', :nro_registro_bbr)");
                $stmt->execute([
                    'rut' => $rut,
                    'nombre' => $nombre,
                    'fecha_nacimiento' => $fecha_nacimiento,
                    'correo' => $correo,
                    'password' => $hashed_password,
                    'sexo' => $sexo,
                    'telefono' => $telefono,
                    'nro_registro_bbr' => $nro_registro_bbr
                ]);
                
                $_SESSION['alerta_success'] = 'Registro completado con éxito. Tu cuenta quedará en estado pendiente hasta ser activada por el administrador.';
                header("Location: login.php");
                exit;
            }
        } catch (\PDOException $e) {
            $error = 'Ocurrió un error al procesar el registro. Inténtelo de nuevo más tarde.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- ══════════════════════════════════════════
REGISTRO PROPIETARIO
══════════════════════════════════════════ -->
<div id="propietario" class="py-5">
    <div class="container my-4">
        <div class="form-card">
            <div class="section-title">
                <img src="img/logo_fondo.png" alt="PNK Inmobiliaria" height="50" class="mb-3" style="border-radius: 8px;">
                <h2>Registrarme como PROPIETARIO</h2>
                <p class="text-muted">Complete el formulario. Su cuenta quedará pendiente hasta ser activada por el administrador.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger small mb-4"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success small mb-4"><?php echo htmlspecialchars($success); ?></div>
            <?php else: ?>
                <form method="POST" action="registro_propietario.php">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">RUT <span class="text-danger">*</span></label>
                            <input type="text" name="rut" class="form-control" placeholder="12.345.678-9" required value="<?php echo isset($_POST['rut']) ? htmlspecialchars($_POST['rut']) : ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" class="form-control" placeholder="Ingrese su nombre completo" required value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fecha de Nacimiento <span class="text-danger">*</span></label>
                            <input type="date" name="fecha_nacimiento" class="form-control" required value="<?php echo isset($_POST['fecha_nacimiento']) ? htmlspecialchars($_POST['fecha_nacimiento']) : ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Correo Electrónico <span class="text-danger">*</span></label>
                            <input type="email" name="correo" class="form-control" placeholder="correo@email.com" required value="<?php echo isset($_POST['correo']) ? htmlspecialchars($_POST['correo']) : ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contraseña <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" placeholder="Mínimo 8 caracteres" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirmar Contraseña <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Repita la contraseña" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Sexo <span class="text-danger">*</span></label>
                            <select name="sexo" class="form-select" required>
                                <option value="" disabled selected>Seleccione</option>
                                <option value="Masculino" <?php echo (isset($_POST['sexo']) && $_POST['sexo'] === 'Masculino') ? 'selected' : ''; ?>>Masculino</option>
                                <option value="Femenino" <?php echo (isset($_POST['sexo']) && $_POST['sexo'] === 'Femenino') ? 'selected' : ''; ?>>Femenino</option>
                                <option value="Prefiero no indicar" <?php echo (isset($_POST['sexo']) && $_POST['sexo'] === 'Prefiero no indicar') ? 'selected' : ''; ?>>Prefiero no indicar</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Teléfono Móvil <span class="text-danger">*</span></label>
                            <input type="tel" name="telefono" class="form-control" placeholder="+56 9 1234 5678" required value="<?php echo isset($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : ''; ?>">
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">N° de Propiedad según Registro de Bienes Raíces <span class="text-danger">*</span></label>
                            <input type="text" name="nro_registro_bbr" class="form-control" placeholder="Ej: 458796-1" required value="<?php echo isset($_POST['nro_registro_bbr']) ? htmlspecialchars($_POST['nro_registro_bbr']) : ''; ?>">
                        </div>
                    </div>
                    <div class="status-pending mb-4">
                        <strong>Proceso de activación:</strong> Su cuenta quedará en estado <em>pendiente</em> hasta que el Administrador verifique sus antecedentes. Recibirá un correo con la confirmación.
                    </div>
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary px-5 py-2">Registrarme como Propietario</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const rutInput = document.querySelector('input[name="rut"]');
    if (rutInput) {
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
    }

    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', (e) => {
            const rut = rutInput ? rutInput.value : '';
            const password = document.querySelector('input[name="password"]').value;
            const confirm = document.querySelector('input[name="confirm_password"]').value;
            
            // Validar RUT
            if (!validarRutJs(rut)) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'RUT Inválido',
                    text: 'Por favor, ingrese un RUT chileno válido con el dígito verificador correcto.',
                    confirmButtonColor: '#0f766e'
                });
                return;
            }

            // Validar Robustez
            const robustRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).{8,}$/;
            if (!robustRegex.test(password)) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Contraseña Débil',
                    text: 'La contraseña debe tener mínimo 8 caracteres, e incluir al menos una mayúscula, una minúscula, un número y un carácter especial.',
                    confirmButtonColor: '#0f766e'
                });
                return;
            }

            // Validar coincidencia
            if (password !== confirm) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Contraseñas no coinciden',
                    text: 'Las contraseñas ingresadas deben ser idénticas.',
                    confirmButtonColor: '#0f766e'
                });
                return;
            }
        });
    }

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

<?php if (!empty($error)): ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        Swal.fire({
            icon: 'error',
            title: 'Error de Validación',
            text: '<?php echo addslashes($error); ?>',
            confirmButtonColor: '#0f766e'
        });
    });
</script>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
